<?php

/**
 * @file
 */

use Civi\Api4\Event;
use Civi\Api4\Participant;
use Civi\Api4\Contribution;
use Civi\Api4\Activity;

/**
 * Goonjcustom.GoonjInititatedEventsOutcomeDetailsCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_goonj_events_outcome_details_update_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.GoonjInititatedEventsOutcomeDetailsCron API.
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
function civicrm_api3_goonjcustom_goonj_events_outcome_details_update_cron($params) {
  $returnValues = [];
  $today = new DateTimeImmutable();
  $startOfDay = $today->setTime(0, 0, 0)->format('Y-m-d H:i:s');
  $endOfDay = $today->setTime(23, 59, 59)->format('Y-m-d H:i:s');

  $events = Event::get(TRUE)
    ->addSelect('Goonj_Events_Feedback.Outcome_Email_Sent', 'end_date', 'start_date')
    ->addWhere('Goonj_Events_Outcome.Outcome_Email_Sent', '=', TRUE)
    ->addWhere('end_date', '<=', $endOfDay)
    ->addWhere('end_date', '>=', $startOfDay)
    ->addWhere('event_type_id:name', '!=', 'Rural Planned Visit')
    ->setLimit(25)
    ->execute();

  foreach ($events as $event) {
    $participants = Participant::get(TRUE)
      ->addSelect('contact_id', 'contact_id.created_date')
      ->addWhere('event_id', '=', $event['id'])
      ->addWhere('status_id:name', '=', 'Attended')
      ->execute();
    $participantsArray = $participants->getArrayCopy();

    $contributions = Contribution::get(TRUE)
      ->addSelect('financial_type_id', 'total_amount', 'contact_id')
      ->addWhere('Contribution_Details.Events', '=', $event['id'])
      ->addWhere('is_test', '=', FALSE)
      ->addWhere('financial_type_id:name', '=', 'Donation')
      ->execute();
    $activities = Activity::get(TRUE)
      ->addSelect('source_contact_id', 'source_contact_id.created_date')
      ->addWhere('Material_Contribution.Event', '=', $event['id'])
      ->addWhere('activity_type_id:name', '=', 'Material Contribution')
      ->addWhere('source_contact_id', 'NOT IN', $uniqueContactIds)
      ->setLimit(25)
      ->execute();
    // Extract start and end date of the event.
    $eventStartDate = $event['start_date'];
    $eventEndDate = $event['end_date'];

    // Count unique participants.
    $uniqueContactIds = array_unique(array_column($participantsArray, 'contact_id'));
    $countUniqueParticipants = count($uniqueContactIds) + count($activities);

    // Count contacts created between event start and end date.
    $filteredContacts = array_filter($participantsArray, function ($participant) use ($eventStartDate, $eventEndDate) {
      return isset($participant['created_date']) &&
      $participant['created_date'] >= $eventStartDate &&
      $participant['created_date'] <= $eventEndDate;
    });

    $countCreatedDuringEvent = count($filteredContacts);

    // Get total contribution sum and number of contributions.
    $contributionArray = $contributions->getArrayCopy();
    $totalSum = array_sum(array_column($contributionArray, 'total_amount'));
    $noOfContributions = count($contributions);

    \Civi::log()->info('Event Stats', [
      'Unique Participants Count' => $countUniqueParticipants,
      'Contacts Created During Event' => $countCreatedDuringEvent,
      'Total Contribution Sum' => $totalSum,
      'Number of Contributions' => $noOfContributions,
    ]);
    $results = Event::update(TRUE)
      ->addValue('Goonj_Events_Outcome.Footfall', $countUniqueParticipants)
      ->addValue('Goonj_Events_Outcome.Online_Monetary_Contribution', $totalSum)
      ->addValue('Goonj_Events_Outcome.Number_of_Contributors', $noOfContributions)
      ->addValue('Goonj_Events_Outcome.Number_of_New_Contacts', $countCreatedDuringEvent)
      ->addWhere('id', '=', $event['id'])
      ->execute();
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'goonj_events_outcome_details_update_cron');
}
