<?php

use Civi\Api4\EckEntity;
use Civi\Api4\contribution;

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
  ->addWhere('subtype:name', 'IN', ['Collection_Camp', 'Dropping_Center', 'Institution_Collection_Camp', 'Goonj_Activities', 'Institution_Dropping_Center', 'Institution_Goonj_Activities'])
  ->execute();

foreach ($collectionCamps as $camp) {
  $campId = $camp['id'];
  error_log("campId: " . print_r($campId, TRUE));
  // Step 2: Get all contributions for this Collection Camp

    $contributions = \Civi\Api4\Contribution::get(FALSE)
  ->addSelect('contact_id')
  ->addWhere('Contribution_Details.Source.id', '=', $campId)
  ->addWhere('contribution_status_id:name', '=', 'Completed')
  ->execute();

   
  // Step 3: Extract unique source_contact_ids
  $uniqueContacts = [];
  foreach ($contributions as $contribution) {
    if (!empty($contribution['contact_id'])) {
      $uniqueContacts[$contribution['contact_id']] = true;
    }
  }
  error_log("uniqueContacts: " . print_r($uniqueContacts, TRUE));

  $uniqueCount = count($uniqueContacts);

  echo "Camp ID $campId: $uniqueCount unique monetary contributors\n";

  // Step 4: Update Collection_Camp entity
  EckEntity::update('Collection_Camp', FALSE)
    ->addValue('Core_Contribution_Details.Number_of_unique_monetary_contributorsn', $uniqueCount)
    ->addWhere('id', '=', $campId)
    ->execute();
}

echo "Update complete.\n";

