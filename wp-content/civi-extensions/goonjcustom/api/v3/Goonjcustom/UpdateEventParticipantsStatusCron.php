<?php

/**
 * @file
 */

use Civi\Api4\Event;
use Civi\Api4\Participant;

// Use Civi\Api4\Participant;.

/**
 * Goonjcustom.GoonjInititatedEventsParticipantStatusUpdate API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_update_event_participants_status_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.GoonjInititatedEventsOutcomeCron API.
 *
 * @param array $params
 *
 * @return array API result descriptor
 *
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_goonjcustom_update_event_participants_status_cron($params) {
  $returnValues = [];
  $today = new DateTimeImmutable();
  $thresholdDate = $today->modify('-72 hours')->format('Y-m-d H:i:s');

  $events = Event::get(TRUE)
    ->addSelect('end_date')
    ->addWhere('Goonj_Events_Feedback.Event_No_Show_Triggered', '=', FALSE)
    ->addWhere('end_date', '<=', $thresholdDate)
    ->setLimit(25)
    ->execute();

  foreach ($events as $event) {
    $eventsDetails = Event::get(TRUE)
      ->addSelect('participant.status_id:name', 'participant.created_id', 'title', 'loc_block_id.address_id', 'Goonj_Events_Feedback.Last_Reminder_Sent', 'end_date')
      ->addJoin('Participant AS participant', 'LEFT')
      ->addWhere('participant.status_id', '=', 1)
      ->addWhere('id', '=', $event['id'])
      ->execute();

    $participantArray = $eventsDetails->getArrayCopy();
    foreach ($participantArray as $participant) {
      try {
        $results = Participant::update(TRUE)
          ->addValue('status_id', 3)
          ->addWhere('contact_id', '=', $participant['participant.created_id'])
          ->addWhere('event_id', '=', $participant['id'])
          ->execute();
      }
      catch (\Exception $e) {
        \Civi::log()->error('Error updating participant status', [
          'participant_id' => $participant['participant.created_id'],
          'error' => $e->getMessage(),
        ]);
      }
    }
    Event::update(TRUE)
      ->addValue('Goonj_Events_Feedback.Event_No_Show_Triggered', TRUE)
      ->addWhere('id', '=', $event['id'])
      ->execute();

  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'update_event_participants_status_cron');
}
