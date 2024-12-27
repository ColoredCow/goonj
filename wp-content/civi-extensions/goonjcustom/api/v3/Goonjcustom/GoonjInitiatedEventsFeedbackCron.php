<?php

/**
 * @file
 */

use Civi\Api4\Event;
use Civi\GoonjInitiatedEventsService;

/**
 * Goonjcustom.GoonjInititatedEventsFeedbackCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_goonj_initiated_events_feedback_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.GoonjInititatedEventsFeedbackCron API.
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
function civicrm_api3_goonjcustom_goonj_initiated_events_feedback_cron($params) {
  $returnValues = [];
  $today = new DateTimeImmutable();
  $endOfDay = $today->setTime(23, 59, 59)->format('Y-m-d H:i:s');

  $events = Event::get(TRUE)
    ->addClause('OR', ['Goonj_Events_Feedback.Last_Reminder_Sent', '=', FALSE], ['Goonj_Events_Feedback.Last_Reminder_Sent', 'IS NULL'])
    ->addWhere('end_date', '<=', $endOfDay)
    ->setLimit(25)
    ->execute();

  foreach ($events as $event) {
    $eventsDetails = Event::get(TRUE)
      ->addSelect('participant.status_id:name', 'participant.created_id', 'title', 'loc_block_id.address_id', 'Goonj_Events_Feedback.Last_Reminder_Sent', 'end_date')
      ->addJoin('Participant AS participant', 'LEFT')
      ->addWhere('participant.status_id', '=', 2)
      ->addWhere('id', '=', 102)
      ->setLimit(25)
      ->execute();

    $eventsArray = $eventsDetails->getArrayCopy();

    try {
      GoonjInitiatedEventsService::sendGoonjInitiatedFeedbackEmail($eventsArray);
    }
    catch (\Exception $e) {
      \Civi::log()->info('Error Goonj Events Feedback Cron', [
        'id' => $event['id'],
        'error' => $e->getMessage(),
      ]);
    }

    return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'goonj_initiated_events_feedback_cron');
  }
}
