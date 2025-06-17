<?php

use Civi\Api4\EckEntity;
use Civi\Api4\Activity;

// Exit if not run via CLI
if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

define('CIVICRM_SETTINGS_PATH', getenv('CIVICRM_SETTINGS_PATH'));
require_once CIVICRM_SETTINGS_PATH;
require_once 'CRM/Core/Config.php';
CRM_Core_Config::singleton();


echo "Starting contributor count update...\n";

// Step 1: Get all Collection_Camp IDs
$collectionCamps = EckEntity::get('Collection_Camp', TRUE)
  ->addSelect('id')
  ->addWhere('subtype:name', '=', 'Collection_Camp')
  ->execute();

foreach ($collectionCamps as $camp) {
  $campId = $camp['id'];
  error_log("campId: " . print_r($campId, TRUE));
  // Step 2: Get all Activities for this Collection Camp
  $activities = Activity::get(FALSE)
    ->addSelect('source_contact_id')
    ->addWhere('Material_Contribution.Collection_Camp', '=', $campId)
    ->execute();

   
  // Step 3: Extract unique source_contact_ids
  $uniqueContacts = [];
  foreach ($activities as $activity) {
    if (!empty($activity['source_contact_id'])) {
      $uniqueContacts[$activity['source_contact_id']] = true;
    }
  }
  error_log("uniqueContacts: " . print_r($uniqueContacts, TRUE));

  $uniqueCount = count($uniqueContacts);

  echo "Camp ID $campId: $uniqueCount unique cash contributors\n";

  // Step 4: Update Collection_Camp entity
  EckEntity::update('Collection_Camp', FALSE)
    ->addValue('Camp_Outcome.Number_of_Material_Contributors', $uniqueCount)
    ->addWhere('id', '=', $campId)
    ->execute();
}

echo "Update complete.\n";

