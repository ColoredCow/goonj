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

// Step 1: Get all Collection_Camp IDs
$collectionCamps = EckEntity::get('Collection_Camp', TRUE)
  ->addSelect('id')
  ->addWhere('subtype:name', '=', 'Collection_Camp')
  ->execute();

foreach ($collectionCamps as $camp) {
  $campId = $camp['id'];
  error_log("Processing campId: " . $campId);

  $materialContributors = [];
  $monetaryContributors = [];

  // Step 2: Get all Activities (Material Contributions) for this Collection Camp
  $activities = Activity::get(FALSE)
    ->addSelect('source_contact_id')
    ->addWhere('Material_Contribution.Collection_Camp', '=', $campId)
    ->execute();

  foreach ($activities as $activity) {
    if (!empty($activity['source_contact_id'])) {
      $materialContributors[$activity['source_contact_id']] = true;
    }
  }

  // Step 3: Get all Contributions (Monetary Contributions) for this Collection Camp
  $contributions = Contribution::get(FALSE)
    ->addSelect('contact_id')
    ->addWhere('Contribution_Details.Source.id', '=', $campId)
    ->execute();

  foreach ($contributions as $contribution) {
    if (!empty($contribution['contact_id'])) {
      $monetaryContributors[$contribution['contact_id']] = true;
    }
  }

  // Step 4: Calculate unique combined contributors
  $combinedContributors = array_unique(array_merge(
    array_keys($materialContributors),
    array_keys($monetaryContributors)
  ));

  $combinedCount = count($combinedContributors);

  echo "Camp ID $campId: $combinedCount unique combined contributors\n";

  // Step 5: Update Collection_Camp entity
  EckEntity::update('Collection_Camp', FALSE)
    ->addValue('Camp_Outcome.Product_Sale_Amount', $combinedCount)
    ->addWhere('id', '=', $campId)
    ->execute();
}

echo "Combined contributor update complete.\n";
