<?php

/**
 * @file
 * CLI Script to Update Razorpay Subscriptions into CiviCRM.
 *
 * Usage:
 *   php update-recurring-contact-info.php.
 */

use Civi\Api4\Contribution;
use Civi\Api4\Address;
use Civi\Api4\Phone;
use Civi\Api4\ContributionRecur;
use Civi\Payment\System;
use Civi\Api4\PaymentProcessor;

require_once __DIR__ . '/../lib/razorpay/Razorpay.php';

const RP_UPDATE_SUBSCRIPTIONS_LIMIT = 100;
const RP_API_MAX_RETRIES = 3;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

/**
 *
 */
class RazorpaySubscriptionUpdater {

  private $api;
  private $skip = 0;
  private $totalImported = 0;
  private $retryCount = 0;
  private $isTest;
  private $processor;
  private $processorID;

  public function __construct() {
    civicrm_initialize();

    $this->isTest = FALSE;

    $processorConfig = PaymentProcessor::get(FALSE)
      ->addWhere('payment_processor_type_id:name', '=', 'Razorpay')
      ->addWhere('is_test', '=', $this->isTest)
      ->execute()->single();

    $this->processor = System::singleton()->getByProcessor($processorConfig);
    $this->processorID = $this->processor->getID();
    $this->api = $this->processor->initializeApi();
  }

  /**
   * Start the subscription update process.
   */
  public function run($limit = NULL): void {
    echo "=== Update Razorpay Subscriptions data into CiviCRM ===\n";

    while (TRUE) {
      try {
        echo "Fetching subscriptions (skip: $this->skip, count: " . $limit . ")\n";

        $subscriptions = $this->fetchSubscriptions($limit);

        if (empty($subscriptions)) {
          echo "No more subscriptions to Update. Total imported: $this->totalImported\n";
          break;
        }

        foreach ($subscriptions as $subscription) {
          $this->processSubscription($subscription);
          $this->totalImported++;
        }

        $this->skip += $limit;
        $this->retryCount = 0;

      }
      catch (Exception $e) {
        $this->handleRetry($e);
      }
    }
    echo "=== Import Completed. Total Subscriptions Imported: $this->totalImported ===\n";
  }

  /**
   * Fetch subscriptions from Razorpay API.
   *
   * @return array
   */
  private function fetchSubscriptions($limit): array {
    $options = [
      'count' => $limit,
      'skip' => $this->skip,
    // Only fetch active subscriptions.
      'status' => 'active',
    ];

    $response = $this->api->subscription->all($options);
    $responseArray = $response->toArray();

    return $responseArray['items'] ?? [];
  }

  /**
   * Handle an individual Razorpay subscription.
   *
   * @param array $subscription
   */
  private function processSubscription(array $subscription): void {
    echo "Processing Subscription ID: {$subscription['id']}\n";
    echo "Status: {$subscription['status']}\n";
    echo "Subscription ID: {$subscription['id']}\n";

    try {
      $contactID = $this->handleCustomerDataAndFindContact($subscription);

      if (!$contactID) {
        throw new Exception("No valid contact could be associated with subscription {$subscription['id']}");
      }

    }
    catch (Exception $e) {
      echo "Error processing subscription {$subscription['id']}: " . $e->getMessage() . "\n";
    }
  }

  /**
   * Handle customer data associated with the subscription.
   *
   * @param array $subscription
   */
  private function handleCustomerDataAndFindContact(array $subscription) {
    $subscriptionId = $subscription['id'] ?? NULL;
    $notes = $subscription['notes'] ?? [];
    $email = $notes['email'] ?? NULL;
    $name = $notes['name'] ?? NULL;
    $mobile = $notes['mobile'] ?? NULL;
    $address = $notes['address1'] ?? NULL;
    $panCard = $notes['PAN Card'] ?? NULL;

    try {
      // Step 1: Fetch first recurring contribution to get contact ID.
      $firstRecur = ContributionRecur::get(FALSE)
        ->addSelect('contact_id', 'contribution.id')
        ->addJoin('Contribution AS contribution', 'LEFT')
        ->addWhere('processor_id', '=', $subscriptionId)
        ->addWhere('contribution.source', '=', 'Imported from Razorpay')
        ->execute()
        ->first();

      $contactId = $firstRecur['contact_id'] ?? NULL;

      if ($contactId) {
        echo "Contact found successfully. Contact ID: $contactId\n";

        try {
          $this->updateDetailsOnContact($contactId, $mobile, $address, $panCard);
        }
        catch (\Exception $e) {
          \Civi::log()->error('Error updating contact details: ' . $e->getMessage());
        }

        // Step 2: Now fetch all contributions and update PAN card.
        try {
          $this->updateDetailsOnImportedContributions($subscriptionId, $panCard);
        }
        catch (\Exception $e) {
          \Civi::log()->error('Error updating PAN card: ' . $e->getMessage());
        }

        return $contactId;
      }

    }
    catch (\Exception $e) {
      \Civi::log()->error('Error fetching recurring contribution: ' . $e->getMessage());
    }

    echo "Could not identify a unique contact. Logged for manual intervention.\n";
    return NULL;
  }

  /**
   *
   */
  public function updateDetailsOnImportedContributions($subscriptionId, $panCard) {
    $allRecurs = ContributionRecur::get(FALSE)
      ->addSelect('contact_id', 'contribution.id')
      ->addJoin('Contribution AS contribution', 'LEFT')
      ->addWhere('processor_id', '=', $subscriptionId)
      ->addWhere('contribution.source', '=', 'Imported from Razorpay')
      ->execute();

    foreach ($allRecurs as $record) {
      $this->updatePanCard([$record], $panCard);
    }
  }

  /**
   *
   */
  public function updatePanCard($contributionRecurs, $panCard) {
    foreach ($contributionRecurs as $item) {
      $contributionId = $item['contribution.id'] ?? NULL;

      if ($contributionId) {
        try {
          Contribution::update(FALSE)
            ->addWhere('id', '=', $contributionId)
            ->addWhere('is_test', '=', $this->isTest)
            ->addValue('Contribution_Details.PAN_Card_Number', $panCard)
            ->execute();

          echo "Updated PAN Card for Contribution ID: $contributionId\n";
        }
        catch (\Exception $e) {
          \Civi::log()->error("Failed to update PAN for contribution ID $contributionId: " . $e->getMessage());
        }
      }
    }
  }

  /**
   *
   */
  public function updateDetailsOnContact($contactId, $mobile, $address, $panCard) {
    // Update or create phone.
    try {
      $existingPhone = Phone::get(FALSE)
        ->addWhere('contact_id', '=', $contactId)
        ->execute()
        ->first();

      if ($existingPhone) {
        Phone::update(FALSE)
          ->addValue('phone', $mobile)
          ->addWhere('id', '=', $existingPhone['id'])
          ->execute();
        echo "Updated phone for contact ID: $contactId\n";
      }
      else {
        Phone::create(FALSE)
          ->addValue('contact_id', $contactId)
          ->addValue('location_type_id:name', 'Main')
          ->addValue('is_primary', TRUE)
          ->addValue('phone', $mobile)
          ->execute();
        echo "Created phone for contact ID: $contactId\n";
      }
    }
    catch (\Exception $e) {
      \Civi::log()->error('Failed to update or create phone number: ' . $e->getMessage());
    }

    // Update or create address.
    try {
      $existingAddress = Address::get(FALSE)
        ->addWhere('contact_id', '=', $contactId)
        ->execute()
        ->first();

      if ($existingAddress) {
        Address::update(FALSE)
          ->addValue('street_address', $address)
          ->addWhere('id', '=', $existingAddress['id'])
          ->execute();
        echo "Updated address for contact ID: $contactId\n";
      }
      else {
        Address::create(FALSE)
          ->addValue('contact_id', $contactId)
          ->addValue('location_type_id:name', 'Main')
          ->addValue('is_primary', TRUE)
          ->addValue('street_address', $address)
          ->execute();
        echo "Created address for contact ID: $contactId\n";
      }
    }
    catch (\Exception $e) {
      \Civi::log()->error('Failed to update or create address: ' . $e->getMessage());
    }

  }

  /**
   * Handle retry logic on failure.
   *
   * @param Exception $e
   */
  private function handleRetry(Exception $e): void {
    $this->retryCount++;
    echo "Error fetching subscriptions: " . $e->getMessage() . "\n";

    if ($this->retryCount >= RP_API_MAX_RETRIES) {
      echo "Maximum retries reached. Exiting...\n";
      exit(1);
    }

    echo "Retrying... ($this->retryCount/" . RP_API_MAX_RETRIES . ")\n";
    sleep(2);

    $this->run(RP_UPDATE_SUBSCRIPTIONS_LIMIT);
  }

}


try {
  $importer = new RazorpaySubscriptionUpdater();
  $importer->run(RP_UPDATE_SUBSCRIPTIONS_LIMIT);
}
catch (\Exception $e) {
  print "Error: " . $e->getMessage() . "\n\n" . $e->getTraceAsString();
}
