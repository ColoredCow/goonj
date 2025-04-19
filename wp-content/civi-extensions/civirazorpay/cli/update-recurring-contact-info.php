<?php

/**
 * @file
 * CLI Script to Import Razorpay Subscriptions into CiviCRM.
 *
 * Usage:
 *   php import-from-razorpay.php.
 */

use Civi\Api4\ContributionRecur;
use Civi\Payment\System;
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

    error_log('subscription: ' . print_r($subscription, TRUE));
    die;

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
    $notes = $subscription['notes'] ?? NULL;
    $mobile = $notes['mobile'] ?? NULL;
    $email = $notes['email'] ?? NULL;
    $name = $notes['name'] ?? NULL;

    $contributionRecur = ContributionRecur::get(FALSE)
      ->addSelect('contact_id')
      ->addJoin('Contribution AS contribution', 'LEFT')
      ->addWhere('processor_id', '=', $subscriptionId)
      ->addWhere('contribution.source', '=', 'Imported from Razorpay')
      ->execute()->first();

    $contactId = $contributionRecur['contact_id'];

    if ($contactID) {
      echo "Contact found/created successfully. Contact ID: $contactID\n";
      return $contactID;
    }
    echo "Could not identify a unique contact. Logged for manual intervention.\n";

    return NULL;
  }

  /**
   * Log manual intervention cases.
   */
  private function logManualIntervention($message, $params) {
    echo "Manual Intervention Required: $message\n";
    \Civi::log('razorpay')->warning($message, $params);
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
