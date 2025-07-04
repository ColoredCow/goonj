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

// Step 1: Get all Collection_event IDs
$events = \Civi\Api4\Event::get(FALSE)
->addSelect('id')
->execute();

foreach ($events as $event) {
  $eventId = $event['id'];
  error_log("Processing eventId: " . $eventId);

  $materialContributors = [];
  $monetaryContributors = [];

  // Step 2: Get all Activities (Material Contributions) for this Collection event
  $activities = Activity::get(FALSE)
    ->addSelect('source_contact_id')
    ->addWhere('Material_Contribution.Event', '=', $eventId)
    ->execute();

  foreach ($activities as $activity) {
    if (!empty($activity['source_contact_id'])) {
      $materialContributors[$activity['source_contact_id']] = true;
    }
  }

  // Step 3: Get all Contributions (Monetary Contributions) for this Collection event
  $contributions = Contribution::get(FALSE)
    ->addSelect('contact_id')
    ->addWhere('Contribution_Details.Events', '=', $eventId)
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

  echo "event ID $eventId: $combinedCount unique combined contributors\n";

  // Step 5: Update Collection_event entity
\Civi\Api4\Event::update(FALSE)
->addValue('Goonj_Events_Outcome.Cash_Contribution', $combinedCount)
->addWhere('id', '=', $eventId)
->execute();
}


echo "Combined contributor update complete.\n";
