<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;

/**
 * Goonjcustom.CollectionCampCron API specification (optional)
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
 * Goonjcustom.CollectionCampCron API.
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
    ->addSelect('Logistics_Coordination.Email_Sent', 'Logistics_Coordination.Camp_to_be_attended_by', 'Collection_Camp_Intent_Details.End_Date', 'Camp_Outcome.Last_Reminder_Sent')
    ->addWhere('Camp_Outcome.Rate_the_camp', 'IS NULL')
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('Collection_Camp_Intent_Details.End_Date', '<=', $endOfDay)
    ->setLimit(25)
    ->execute();
  foreach ($collectionCamps as $camp) {
    try {
      $campAttendedById = $camp['Logistics_Coordination.Camp_to_be_attended_by'];
      $endDate = new \DateTime($camp['Collection_Camp_Intent_Details.End_Date']);
      $lastReminderSent = new \DateTime($camp['Reminders.Last_Reminder_Sent']);
      $hoursSinceCampEnd = $today->diff($endDate)->h;
      $hoursSinceLastReminder = $today->diff($lastReminderSent)->h;

      // Check if 48 hours have passed since camp end and outcome form is not filled.
      if ($hoursSinceCampEnd >= 48 && !$camp['Outcome_Form.Submitted']) {
        // Send the reminder email.
        CollectionCampService::sendReminderEmail($camp);

        // Update the Last_Reminder_Sent field.
        EckEntity::update('Collection_Camp', FALSE)
          ->addValue('Reminders.Last_Reminder_Sent', $today->format('Y-m-d H:i:s'))
          ->addWhere('id', '=', $camp['id'])
          ->execute();
      }
      catch (\Exception $e) {
        \Civi::log()->info('Error processing camp reminder', [
          'id' => $camp['id'],
          'error' => $e->getMessage(),
        ]);
      }
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'collection_camp_outcome_reminder_cron');
}
