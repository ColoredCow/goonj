<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\CollectionCampOutcomeService;

/**
 * Goonjcustom.CollectionCampOutcomeReminderCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_collection_camp_outcome_reminder_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.CollectionCampOutcomeReminderCron API.
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
function civicrm_api3_goonjcustom_collection_camp_outcome_reminder_cron($params) {
  $returnValues = [];
  $today = new DateTimeImmutable();
  $endOfDay = $today->setTime(23, 59, 59)->format('Y-m-d H:i:s');

  // Fetch camps that have completed but the outcome form is not yet filled.
  $collectionCamps = EckEntity::get('Collection_Camp', TRUE)
    ->addSelect(
      'Logistics_Coordination.Email_Sent',
      'Logistics_Coordination.Camp_to_be_attended_by',
      'Collection_Camp_Intent_Details.End_Date',
      'Camp_Outcome.Last_Reminder_Sent',
      'Camp_Outcome.Rate_the_camp',
      'title',
      'Collection_Camp_Intent_Details.Location_Area_of_camp',
    )
    ->addWhere('Camp_Outcome.Rate_the_camp', 'IS NULL')
    ->addWhere('Logistics_Coordination.Email_Sent', '=', 1)
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('Collection_Camp_Intent_Details.End_Date', '<=', $endOfDay)
    ->execute();

  [$defaultFromName, $defaultFromEmail] = CRM_Core_BAO_Domain::getNameAndEmail();
  $from = "\"$defaultFromName\" <$defaultFromEmail>";

  foreach ($collectionCamps as $camp) {
    try {
      $campAttendedById = $camp['Logistics_Coordination.Camp_to_be_attended_by'];
      $endDateForCollectionCamp = $camp['Collection_Camp_Intent_Details.End_Date'];
      $endDate = new \DateTime($camp['Collection_Camp_Intent_Details.End_Date']);
      $collectionCampId = $camp['id'];
      $campCode = $camp['title'];
      $campAddress = $camp['Collection_Camp_Intent_Details.Location_Area_of_camp'];

      $lastReminderSent = $camp['Camp_Outcome.Last_Reminder_Sent'] ? new \DateTime($camp['Camp_Outcome.Last_Reminder_Sent']) : NULL;

      // Calculate hours since camp ended.
      $hoursSinceCampEnd = $today->diff($endDate)->h + ($today->diff($endDate)->days * 24);

      // Calculate hours since last reminder was sent (if any)
      $hoursSinceLastReminder = $lastReminderSent ? ($today->diff($lastReminderSent)->h + ($today->diff($lastReminderSent)->days * 24)) : NULL;

      // Check if the outcome form is not filled and send the first reminder after 48 hours of camp end.
      if ($hoursSinceCampEnd >= 48) {
        // Send the reminder email if the form is still not filled.
        if ($lastReminderSent === NULL || $hoursSinceLastReminder >= 24) {
          // Send the reminder email.
          CollectionCampOutcomeService::sendOutcomeReminderEmail($campAttendedById, $from, $campCode, $campAddress, $collectionCampId, $endDateForCollectionCamp);

          // Update the Last_Reminder_Sent field in the database.
          EckEntity::update('Collection_Camp', TRUE)
            ->addWhere('id', '=', $camp['id'])
            ->addValue('Camp_Outcome.Last_Reminder_Sent', $today->format('Y-m-d H:i:s'))
            ->execute();
        }
      }
    }
    catch (\Exception $e) {
      \Civi::log()->error('Error processing camp reminder', [
        'id' => $camp['id'],
        'error' => $e->getMessage(),
      ]);
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'collection_camp_outcome_reminder_cron');
}
