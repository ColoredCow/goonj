<?php

use Civi\Api4\EckEntity;
use Civi\Api4\Contribution;

if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

define('CIVICRM_SETTINGS_PATH', getenv('CIVICRM_SETTINGS_PATH'));
require_once CIVICRM_SETTINGS_PATH;
require_once 'CRM/Core/Config.php';
CRM_Core_Config::singleton();

echo "Starting contributor count update...\n";

function getTotalContributionByPaymentMethods($campId, $paymentMethods) {
  $contributions = Contribution::get(FALSE)
    ->addSelect('total_amount')
    ->addWhere('Contribution_Details.Source.id', '=', $campId)
    ->addWhere('contribution_status_id:name', '=', 'Completed')
    ->addWhere('payment_instrument_id:name', 'IN', (array) $paymentMethods)
    ->execute();

  $total = 0;
  foreach ($contributions as $contribution) {
    $total += $contribution['total_amount'];
  }

  return $total;
}

$collectionCamps = EckEntity::get('Collection_Camp', TRUE)
  ->addSelect('id')
  ->addWhere('subtype:name', 'IN', ['Collection_Camp', 'Dropping_Center', 'Institution_Collection_Camp', 'Institution_Dropping_Center', 'Goonj_Activities', 'Institution_Goonj_Activities'])
  ->execute();

foreach ($collectionCamps as $camp) {
  $campId = $camp['id'];
  echo "Processing camp ID: $campId\n";
  error_log("Processing campId: $campId");

  $onlineTotal = getTotalContributionByPaymentMethods($campId, ['Credit Card']);
  $cashTotal = getTotalContributionByPaymentMethods($campId, ['Cash', 'Check']);

  echo "→ Online Monetary Contribution: ₹$onlineTotal\n";
  echo "→ Cash Contribution: ₹$cashTotal\n";
  error_log("Online total: ₹$onlineTotal | Cash total: ₹$cashTotal");

  EckEntity::update('Collection_Camp', FALSE)
    ->addValue('Camp_Outcome.Online_Monetary_Contribution', $onlineTotal)
    ->addValue('Camp_Outcome.Cash_Contribution', $cashTotal)
    ->addWhere('id', '=', $campId)
    ->execute();

  echo "✓ Updated contributions for camp ID $campId.\n\n";
}

echo "✅ Update complete for all camps.\n";
