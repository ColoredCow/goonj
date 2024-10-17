<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\HelperService;
use Civi\CollectionCampVolunteerFeedbackService;

/**
 * Goonjcustom.VolunteerFeedbackReminderCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_volunteer_feedback_reminder_cron_spec(&$spec) {
  // No parameters required for the Goonjcustom volunteer feedback reminder cron.
}

/**
 * Goonjcustom.VolunteerFeedbackReminderCron API.
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

/**
 * Cron job to send reminder emails to volunteers who haven't filled the feedback form.
 */
function civicrm_api3_goonjcustom_volunteer_feedback_reminder_cron($params) {
  $returnValues = [];
  $now = new DateTimeImmutable();
  $endOfDay = $now->setTime(23, 59, 59)->format('Y-m-d H:i:s');

  // Get the default "from" email.
  $from = HelperService::getDefaultFromEmail();

  // Fetch camps that have completed and volunteers have not filled the feedback form.
  $campsNeedReminder = EckEntity::get('Collection_Camp', TRUE)
    ->addSelect('Volunteer_Camp_Feedback.Last_Reminder_Sent', 'Collection_Camp_Intent_Details.Location_Area_of_camp', 'Collection_Camp_Intent_Details.End_Date', 'Collection_Camp_Core_Details.Contact_Id', 'Collection_Camp_Intent_Details.Camp_Status')
    ->addWhere('Volunteer_Camp_Feedback.Give_Rating_to_your_camp', 'IS NULL')
    ->addWhere('Logistics_Coordination.Feedback_Email_Sent', '=', 1)
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('Collection_Camp_Intent_Details.End_Date', '<=', $endOfDay)
    ->addWhere('Collection_Camp_Intent_Details.Camp_Status', '!=', 'aborted')
    ->execute();

  foreach ($campsNeedReminder as $camp) {
    try {
      CollectionCampVolunteerFeedbackService::processVolunteerFeedbackReminder($camp, $now, $from);
    }
    catch (\Exception $e) {
      \Civi::log()->error('Error processing volunteer feedback reminder', [
        'id' => $camp['id'],
        'error' => $e->getMessage(),
      ]);
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'volunteer_feedback_reminder_cron');
}
