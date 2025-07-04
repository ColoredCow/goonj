<?php

use Civi\Api4\EckEntity;
use Civi\Api4\Contribution;

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
  ->addWhere('subtype:name', 'IN', [
    'Collection_Camp',
    'Dropping_Center',
    'Institution_Collection_Camp',
    'Goonj_Activities',
    'Institution_Dropping_Center',
    'Institution_Goonj_Activities'
  ])
  ->execute();

foreach ($collectionCamps as $camp) {
  $campId = $camp['id'];
  error_log("Processing campId: $campId");

  // Step 2: Get all 'Completed' contributions for this camp based on Source.id
  $contributions = Contribution::get(FALSE)
    ->addSelect('id', 'contact_id', 'Contribution_Details.Source.id')
    ->addWhere('Contribution_Details.Source.id', '=', $campId)
    ->addWhere('contribution_status_id:name', '=', 'Completed')
    ->execute();

  error_log("Found " . count($contributions) . " contributions for campId $campId");

  // Step 3: Collect unique contact IDs
  $uniqueContacts = [];

  foreach ($contributions as $contribution) {
    $contactId = $contribution['contact_id'] ?? null;
    if (!empty($contactId)) {
      $uniqueContacts[$contactId] = true;
    }
  }

  $uniqueCount = count($uniqueContacts);
  error_log("Unique contributors for campId $campId: $uniqueCount");

  // Step 4: Update the Collection_Camp entity with the count
  EckEntity::update('Collection_Camp', FALSE)
    ->addValue('Core_Contribution_Details.Number_of_unique_monetary_contributors', $uniqueCount)
    ->addWhere('id', '=', $campId)
    ->execute();

  echo "Updated camp ID $campId: $uniqueCount unique contributors.\n";
}

echo "Update complete.\n";
