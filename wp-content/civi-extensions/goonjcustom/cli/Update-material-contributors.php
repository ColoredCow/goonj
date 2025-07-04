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

// Define subtype => activity field map
$subtypeToActivityField = [
  'Collection_Camp' => 'Material_Contribution.Collection_Camp',
  'Dropping_Center' => 'Material_Contribution.Dropping_Center',
  'Institution_Collection_Camp' => 'Material_Contribution.Institution_Collection_Camp',
  'Institution_Dropping_Center' => 'Material_Contribution.Institution_Dropping_Center',
];

$subtypes = array_keys($subtypeToActivityField);

// Step 1: Get all relevant camps
$collectionCamps = EckEntity::get('Collection_Camp', TRUE)
  ->addSelect('id', 'subtype:name')
  ->addWhere('subtype:name', 'IN', $subtypes)
  ->execute();

foreach ($collectionCamps as $camp) {
  $campId = $camp['id'];
  $subtype = $camp['subtype:name'];

  // Get correct activity field for this subtype
  if (!isset($subtypeToActivityField[$subtype])) {
    error_log("Unknown subtype '$subtype' for camp ID $campId. Skipping...");
    continue;
  }

  $activityField = $subtypeToActivityField[$subtype];
  error_log("Processing Camp ID: $campId (Subtype: $subtype, Filter: $activityField)");

  // Step 2: Get activities linked by correct field
  $activities = Activity::get(FALSE)
    ->addSelect('source_contact_id')
    ->addWhere($activityField, '=', $campId)
    ->execute();

  // Step 3: Unique source_contact_ids
  $uniqueContacts = [];
  foreach ($activities as $activity) {
    if (!empty($activity['source_contact_id'])) {
      $uniqueContacts[$activity['source_contact_id']] = true;
    }
  }

  $uniqueCount = count($uniqueContacts);
  echo "Camp ID $campId ($subtype): $uniqueCount unique material contributors\n";

  // Step 4: Update contributor count
  EckEntity::update('Collection_Camp', FALSE)
    ->addValue('Core_Contribution_Details.Number_of_unique_material_contributors', $uniqueCount)
    ->addWhere('id', '=', $campId)
    ->execute();
}

echo "Contributor count update complete.\n";
