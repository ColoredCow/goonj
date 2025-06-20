<?php

use Civi\Api4\Contribution;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

define('CIVICRM_SETTINGS_PATH', getenv('CIVICRM_SETTINGS_PATH'));
require_once CIVICRM_SETTINGS_PATH;
require_once 'CRM/Core/Config.php';
CRM_Core_Config::singleton();

echo "Starting contributor count update...\n";

function getTotalContributionByPaymentMethods($eventId, $paymentMethods) {
  $contributions = Contribution::get(FALSE)
    ->addSelect('total_amount')
    ->addWhere('Contribution_Details.Events', '=', $eventId)
    ->addWhere('contribution_status_id:name', '=', 'Completed')
    ->addWhere('payment_instrument_id:name', 'IN', (array) $paymentMethods)
    ->execute();

  $total = 0;
  foreach ($contributions as $contribution) {
    $total += $contribution['total_amount'];
  }

  return $total;
}

$events = \Civi\Api4\Event::get(FALSE)
->addSelect('id')
->execute();

foreach ($events as $event) {
  $eventId = $event['id'];
  echo "Processing event ID: $eventId\n";
  error_log("Processing eventId: $eventId");

  $onlineTotal = getTotalContributionByPaymentMethods($eventId, ['Credit Card']);
  $cashTotal = getTotalContributionByPaymentMethods($eventId, ['Cash', 'Check']);

  echo "→ Online Monetary Contribution: ₹$onlineTotal\n";
  echo "→ Cash Contribution: ₹$cashTotal\n";
  error_log("Online total: ₹$onlineTotal | Cash total: ₹$cashTotal");

  \Civi\Api4\Event::update(FALSE)
    ->addValue('Goonj_Events_Outcome.Online_Monetary_Contribution', $onlineTotal)
    ->addValue('Goonj_Events_Outcome.Cash_Contribution', $cashTotal)
    ->addWhere('id', '=', $eventId)
    ->execute();

  echo "✓ Updated contributions for event ID $eventId.\n\n";
}

echo "✅ Update complete for all events.\n";
