<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;

define('CIVICRM_SETTINGS_PATH', '/Users/shubhambelwal/Sites/goonj/wp-content/uploads/civicrm/civicrm.settings.php');
require_once CIVICRM_SETTINGS_PATH;
require_once 'CRM/Core/Config.php';
CRM_Core_Config::singleton();


if (php_sapi_name() !== 'cli') {
  exit("❌ This script can only be run from the command line.\n");
}

echo "🔄 Starting Dropping Center Status Sync...\n";

/**
 *
 */
function syncAllCurrentStatusesFromDroppingCenterMeta() {
  echo "📦 Fetching latest statuses from Dropping_Center_Meta...\n";

  $allMetas = EckEntity::get('Dropping_Center_Meta', TRUE)
    ->addSelect('Status.Status', 'Dropping_Center_Meta.Institution_Dropping_Center')
    ->addWhere('Dropping_Center_Meta.Institution_Dropping_Center', 'IS NOT NULL')
    ->addOrderBy('created_date', 'DESC')
    ->execute();

  $latestStatuses = [];

  foreach ($allMetas as $meta) {
    $droppingCenterId = $meta['Dropping_Center_Meta.Institution_Dropping_Center'];
    $status = $meta['Status.Status'];

    // Only pick the first (most recent) status per dropping center.
    if (!isset($latestStatuses[$droppingCenterId])) {
      $latestStatuses[$droppingCenterId] = $status;
    }
  }

  echo "✅ Found " . count($latestStatuses) . " unique Dropping Centers with latest status.\n";

  foreach ($latestStatuses as $droppingCenterId => $status) {
    try {
      $results = EckEntity::update('Collection_Camp', FALSE)
        ->addValue('Institution_Dropping_Center_Intent.Current_Status', $status)
        ->addWhere('id', '=', $droppingCenterId)
        ->execute();

      $count = count($results);
      echo "✅ Updated Dropping Center ID [$droppingCenterId] to Status [$status] — $count record(s) affected.\n";
    }
    catch (\Exception $e) {
      echo "❌ Error updating Dropping Center ID [$droppingCenterId]: " . $e->getMessage() . "\n";
    }
  }

  echo "🏁 Sync complete.\n";
}

syncAllCurrentStatusesFromDroppingCenterMeta();
