<?php

use Civi\Api4\EckEntity;
use Civi\Api4\Activity;
use Civi\Api4\Contribution;

// Exit if not run via CLI
if (php_sapi_name() !== 'cli') {
  exit("This script can only be run from the command line.\n");
}

define('CIVICRM_SETTINGS_PATH', getenv('CIVICRM_SETTINGS_PATH'));
require_once CIVICRM_SETTINGS_PATH;
require_once 'CRM/Core/Config.php';
CRM_Core_Config::singleton();

echo "Starting combined unique contributor count update...\n";

// Subtype to corresponding Material Contribution activity field
$subtypeToActivityField = [
  'Collection_Camp' => 'Material_Contribution.Collection_Camp',
  'Dropping_Center' => 'Material_Contribution.Dropping_Center',
  'Institution_Collection_Camp' => 'Material_Contribution.Institution_Collection_Camp',
  'Institution_Dropping_Center' => 'Material_Contribution.Institution_Dropping_Center',
];

// List of supported subtypes
$subtypes = array_keys($subtypeToActivityField);

// Step 1: Get all relevant Collection_Camp entities
$collectionCamps = EckEntity::get('Collection_Camp', TRUE)
  ->addSelect('id', 'subtype:name')
  ->addWhere('subtype:name', 'IN', $subtypes)
  ->execute();

foreach ($collectionCamps as $camp) {
  $campId = $camp['id'];
  $subtype = $camp['subtype:name'];

  if (!isset($subtypeToActivityField[$subtype])) {
    error_log("Skipping unknown subtype '$subtype' for camp ID $campId");
    continue;
  }

  $activityField = $subtypeToActivityField[$subtype];
  error_log("Processing campId: $campId (Subtype: $subtype)");

  $materialContributors = [];
  $monetaryContributors = [];

  // Step 2: Get Activities (Material Contributions)
  $activities = Activity::get(FALSE)
    ->addSelect('source_contact_id')
    ->addWhere($activityField, '=', $campId)
    ->execute();

  foreach ($activities as $activity) {
    if (!empty($activity['source_contact_id'])) {
      $materialContributors[$activity['source_contact_id']] = true;
    }
  }

  // Step 3: Get Contributions (Monetary Contributions)
  $contributions = Contribution::get(FALSE)
    ->addSelect('contact_id')
    ->addWhere('Contribution_Details.Source.id', '=', $campId)
    ->addWhere('contribution_status_id:name', '=', 'Completed')
    ->execute();

  foreach ($contributions as $contribution) {
    if (!empty($contribution['contact_id'])) {
      $monetaryContributors[$contribution['contact_id']] = true;
    }
  }

  // Step 4: Combine unique contributor IDs
  $combinedContributors = array_unique(array_merge(
    array_keys($materialContributors),
    array_keys($monetaryContributors)
  ));
  $combinedCount = count($combinedContributors);

  echo "Camp ID $campId ($subtype): $combinedCount unique combined contributors\n";

  // Step 5: Update the ECK entity field
  EckEntity::update('Collection_Camp', FALSE)
    ->addValue('Core_Contribution_Details.Number_of_unique_contributors', $combinedCount) // You can change this field if needed
    ->addWhere('id', '=', $campId)
    ->execute();
}

echo "Combined contributor update complete.\n";
