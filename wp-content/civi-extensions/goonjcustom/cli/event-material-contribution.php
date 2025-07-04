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

// Step 1: Get all Collection_event IDs
  $events = \Civi\Api4\Event::get(FALSE)
  ->addSelect('id')
  ->execute();

foreach ($events as $event) {
  $eventId = $event['id'];
  error_log("eventId: " . print_r($eventId, TRUE));
  // Step 2: Get all Activities for this Collection event
  $activities = Activity::get(FALSE)
    ->addSelect('source_contact_id')
    ->addWhere('Material_Contribution.Event', '=', $eventId)
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

  echo "event ID $eventId: $uniqueCount unique cash contributors\n";

  // Step 4: Update Collection_event entity
  \Civi\Api4\Event::update(FALSE)
    ->addValue('Goonj_Events_Outcome.Footfall', $uniqueCount)
    ->addWhere('id', '=', $eventId)
    ->execute();
}

echo "Update complete.\n";

