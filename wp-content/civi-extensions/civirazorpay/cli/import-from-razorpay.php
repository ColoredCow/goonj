<?php

/**
 * @file
 * CLI Script to Import Razorpay Subscriptions into CiviCRM.
 *
 * Usage:
 *   php import-from-razorpay.php.
 */

use Civi\Payment\System;
use Civi\Api4\PaymentProcessor;

require_once __DIR__ . '/../lib/razorpay/Razorpay.php';

const RP_IMPORT_SUBSCRIPTIONS_LIMIT = 100;
const RP_API_MAX_RETRIES = 3;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

civicrm_initialize();

$processorConfig = PaymentProcessor::get(FALSE)
  ->addWhere('payment_processor_type_id:name', '=', 'Razorpay')
  ->addWhere('is_test', '=', TRUE)
  ->execute()->single();

$processor = System::singleton()->getByProcessor($processorConfig);
$api = $processor->initializeApi();

$skip = 0;
$totalImported = 0;
$retryCount = 0;

echo "=== Importing Razorpay Subscriptions into CiviCRM ===\n";

while (TRUE) {
  try {
    echo "Fetching subscriptions (skip: $skip, count: " . RP_IMPORT_SUBSCRIPTIONS_LIMIT . ")\n";

    $options = [
      'count' => RP_IMPORT_SUBSCRIPTIONS_LIMIT,
      'skip' => $skip,
    ];

    $response = $api->subscription->all($options);
    $responseArray = $response->toArray();

    $subscriptions = $responseArray['items'] ?? [];
    $count = $responseArray['count'] ?? 0;

    if (empty($subscriptions) || $count === 0) {
      echo "No more subscriptions to import. Total imported: $totalImported\n";
      break;
    }

    foreach ($subscriptions as $subscription) {
      processSubscription($subscription);
      $totalImported++;
    }

    $skip += RP_IMPORT_SUBSCRIPTIONS_LIMIT;

    $retryCount = 0;

  }
  catch (Exception $e) {
    $retryCount++;
    echo "Error fetching subscriptions: " . $e->getMessage() . "\n";

    if ($retryCount >= RP_API_MAX_RETRIES) {
      echo "Maximum retries reached. Exiting...\n";
      break;
    }

    echo "Retrying... ($retryCount/" . RP_API_MAX_RETRIES . ")\n";
    sleep(2);
  }
}

echo "=== Import Completed. Total Subscriptions Imported: $totalImported ===\n";

/**
 * Process an individual Razorpay subscription and update/create ContributionRecur in CiviCRM.
 *
 * @param array $subscription
 */
function processSubscription(array $subscription): void {
  echo "ID: " . $subscription['id'] . PHP_EOL;
  echo "Status: " . $subscription['status'] . PHP_EOL;
}

/**
 * Map Razorpay subscription status to CiviCRM contribution status.
 *
 * @param string $status
 *
 * @return string
 */
function mapStatus(string $status): string {
  switch ($status) {
    case 'active':
      return 'In Progress';

    case 'cancelled':
      return 'Cancelled';

    case 'completed':
      return 'Completed';

    default:
      return 'Pending';
  }
}
