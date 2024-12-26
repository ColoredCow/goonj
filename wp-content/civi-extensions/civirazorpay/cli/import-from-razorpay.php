<?php

/**
 * @file
 * CLI Script to Import Razorpay Subscriptions into CiviCRM.
 *
 * Usage:
 *   php import-from-razorpay.php.
 */

use Civi\Payment\System;
use Civi\Api4\Email;
use Civi\Api4\Phone;
use Civi\Api4\PaymentProcessor;

require_once __DIR__ . '/../lib/razorpay/Razorpay.php';

const RP_IMPORT_SUBSCRIPTIONS_LIMIT = 100;
const RP_API_MAX_RETRIES = 3;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

/**
 *
 */
class RazorpaySubscriptionImporter {

  private $api;
  private $skip = 0;
  private $totalImported = 0;
  private $retryCount = 0;

  public function __construct() {
    civicrm_initialize();

    $processorConfig = PaymentProcessor::get(FALSE)
      ->addWhere('payment_processor_type_id:name', '=', 'Razorpay')
      ->addWhere('is_test', '=', TRUE)
      ->execute()->single();

    $processor = System::singleton()->getByProcessor($processorConfig);
    $this->api = $processor->initializeApi();
  }

  /**
   * Start the subscription import process.
   */
  public function run($limit = NULL): void {
    echo "=== Importing Razorpay Subscriptions into CiviCRM ===\n";

    // While (TRUE) {.
    try {
      echo "Fetching subscriptions (skip: $this->skip, count: " . $limit . ")\n";

      $subscriptions = $this->fetchSubscriptions($limit);

      if (empty($subscriptions)) {
        echo "No more subscriptions to import. Total imported: $this->totalImported\n";
        // break;
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
    // }
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
    echo "Customer ID: {$subscription['customer_id']}\n";

    try {
      $this->handleCustomerData($subscription);
      // $this->mapSubscriptionToCiviCRM($subscription);
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
  private function handleCustomerData(array $subscription): void {
    $customerId = $subscription['customer_id'] ?? NULL;

    if ($customerId) {
      $customer = $this->api->customer->fetch($customerId);

      $contactID = $this->findContact([
        'name' => $customer->name,
        'email' => $customer->email,
        'phone' => $customer->contact,
      ]);

      echo "CiviCRM contact ID for Razorpay customer $customerId ==> $contactID \n";
      print_r([
        'name' => $customer->name,
        'email' => $customer->email,
        'phone' => $customer->contact,
      ]);
    }
    else {
      echo "No customer data available for subscription {$subscription['id']}\n";
    }
  }

  /**
   *
   */
  public function findContact($params) {
    // Check if both email and phone are missing.
    if (empty($params['email']) && empty($params['phone'])) {
      echo "Neither email nor phone is provided to find the contact!\n";
      return NULL;
    }

    // 1. Search by Email (if available)
    if (!empty($params['email'])) {
      echo "Searching contact by email: " . $params['email'] . "\n";

      $emailResults = Email::get(FALSE)
        ->addWhere('email', '=', $params['email'])
        ->execute();

      if ($emailResults->count() === 1) {
        $emailContact = $emailResults->first();
        echo "Found contact by email. Contact ID: " . $emailContact['contact_id'] . "\n";
        return $emailContact['contact_id'];
      }
      elseif ($emailResults->count() > 1) {
        echo "Multiple contacts found with the same email. Manual resolution needed.\n";
        return NULL;
      }
      else {
        echo "No contact found with the given email. Proceeding to phone search...\n";
      }
    }

    // 2. Fallback: Search by Phone (if available)
    if (!empty($params['phone'])) {
      echo "Searching contact by phone: " . $params['phone'] . "\n";

      $phoneResults = Phone::get(FALSE)
        ->addWhere('phone', '=', $params['phone'])
        ->execute();

      if ($phoneResults->count() === 1) {
        $phoneContact = $phoneResults->first();
        echo "Found contact by phone. Contact ID: " . $phoneContact['contact_id'] . "\n";
        return $phoneContact['contact_id'];
      }
      elseif ($phoneResults->count() > 1) {
        echo "Multiple contacts found with the same phone number. Manual resolution needed.\n";
        return NULL;
      }
      else {
        echo "No contact found with the given phone number.\n";
      }
    }

    // 3. No contact found after both email and phone search
    echo "No contact found via email or phone. Consider creating a new contact.\n";
    return NULL;
  }

  /**
   * Map subscription data to CiviCRM ContributionRecur.
   *
   * @param array $subscription
   */
  private function mapSubscriptionToCiviCRM(array $subscription): void {
    echo "Mapping Subscription {$subscription['id']} to CiviCRM ContributionRecur...\n";

    // Placeholder for actual mapping logic
    // Example:
    // ContributionRecur::create([
    //     'contact_id' => $contactId,
    //     'amount' => $subscription['quantity'] * 100,
    //     'currency' => 'INR',
    //     'start_date' => date('Y-m-d H:i:s', $subscription['start_at']),
    //     'processor_id' => $subscription['id'],
    //     'contribution_status_id:name' => 'In Progress',
    // ])->execute();
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

    $this->run(RP_IMPORT_SUBSCRIPTIONS_LIMIT);
  }

}


try {
  $importer = new RazorpaySubscriptionImporter();
  $importer->run(RP_IMPORT_SUBSCRIPTIONS_LIMIT);
}
catch (\Exception $e) {
  print "Error: " . $e->getMessage() . "\n\n" . $e->getTraceAsString();
}
