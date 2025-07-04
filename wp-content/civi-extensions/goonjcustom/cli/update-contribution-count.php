<?php

use Civi\Api4\EckEntity;
use Civi\Api4\Contribution;

// ✅ Ensure it runs from CLI only
if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

// ✅ Load CiviCRM configuration
define('CIVICRM_SETTINGS_PATH', getenv('CIVICRM_SETTINGS_PATH'));
require_once CIVICRM_SETTINGS_PATH;
require_once 'CRM/Core/Config.php';
CRM_Core_Config::singleton();

echo "🔄 Starting online & cash contribution totals update...\n";

// ✅ Function to calculate total amount for given camp & payment methods
function getTotalContributionByPaymentMethods($campId, array $paymentMethods): float {
  $contributions = Contribution::get(FALSE)
    ->addSelect('total_amount')
    ->addWhere('Contribution_Details.Source.id', '=', $campId)
    ->addWhere('contribution_status_id:name', '=', 'Completed')
    ->addWhere('payment_instrument_id:name', 'IN', $paymentMethods)
    ->execute();

  $total = 0.0;
  foreach ($contributions as $contribution) {
    $total += (float) ($contribution['total_amount'] ?? 0);
  }

  return $total;
}

// ✅ Fetch all collection camps of valid types
$collectionCamps = EckEntity::get('Collection_Camp', FALSE)
  ->addSelect('id')
  ->addWhere('subtype:name', 'IN', [
    'Collection_Camp',
    'Dropping_Center',
    'Institution_Collection_Camp',
    'Institution_Dropping_Center',
    'Goonj_Activities',
    'Institution_Goonj_Activities'
  ])
  ->execute();

// ✅ Loop through camps and update contributions
foreach ($collectionCamps as $camp) {
  $campId = $camp['id'];
  echo "🔍 Processing camp ID: $campId\n";
  error_log("Processing campId: $campId");

  $onlineTotal = getTotalContributionByPaymentMethods($campId, ['Credit Card']);
  $cashTotal   = getTotalContributionByPaymentMethods($campId, ['Cash', 'Check']);

  echo "💳 Online Monetary Contribution: ₹" . number_format($onlineTotal, 2) . "\n";
  echo "💵 Cash/Cheque Contribution: ₹" . number_format($cashTotal, 2) . "\n";
  error_log("Online total: ₹$onlineTotal | Cash total: ₹$cashTotal");

  // ✅ Update the Collection_Camp entity with totals
  EckEntity::update('Collection_Camp', FALSE)
    ->addValue('Core_Contribution_Details.Total_online_monetary_contributions', $onlineTotal)
    ->addValue('Core_Contribution_Details.Total_cash_cheque_monetary_contributions', $cashTotal)
    ->addWhere('id', '=', $campId)
    ->execute();

  echo "✅ Updated totals for camp ID $campId.\n\n";
}

echo "🏁 Update complete for all camps.\n";
