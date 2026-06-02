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

  // Feedback now lives in its own Eck entity (Collection_Source_Feedback)
  // linked back to the camp via Collection_Camp_Code. Exclude any camp that
  // either already has submitted feedback OR has already been sent a reminder
  // (Last_Reminder_Sent set on the feedback row).
  $campIdsToSkip = EckEntity::get('Collection_Source_Feedback', FALSE)
    ->addSelect('Collection_Source_Feedback.Collection_Camp_Code')
    ->addClause('OR',
      ['Collection_Source_Feedback.Rate_Your_Camp_Experience_1_Lowest_10_Highest_', 'IS NOT NULL'],
      ['Collection_Source_Feedback.Last_Reminder_Sent', 'IS NOT NULL']
    )
    ->execute()
    ->column('Collection_Source_Feedback.Collection_Camp_Code');

  // Floor on End_Date so the cron does not blast reminders for historical camps
  // when it resumes after being broken for a while. Only camps that ended on or
  // after this cutoff are eligible for a reminder.
  $reminderEligibleFrom = '2026-05-20 00:00:00';

  // checkPermissions=FALSE because cron has no user context; with TRUE, ACL
  // filters out every row (matches other Goonj crons in this directory).
  $campsQuery = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect('Collection_Camp_Intent_Details.Location_Area_of_camp', 'Collection_Camp_Intent_Details.End_Date', 'Collection_Camp_Core_Details.Contact_Id')
    ->addWhere('Logistics_Coordination.Feedback_Email_Sent', '=', 1)
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('Collection_Camp_Intent_Details.End_Date', '>=', $reminderEligibleFrom)
    ->addWhere('Collection_Camp_Intent_Details.End_Date', '<=', $endOfDay)
    // Camp_Status IS NULL for most camps; SQL "NULL != 'aborted'" evaluates to
    // NULL and would drop those rows, so make the exclusion explicitly NULL-safe.
    ->addClause('OR',
      ['Collection_Camp_Intent_Details.Camp_Status', 'IS NULL'],
      ['Collection_Camp_Intent_Details.Camp_Status', '!=', 'aborted']
    );

  if (!empty($campIdsToSkip)) {
    $campsQuery->addWhere('id', 'NOT IN', $campIdsToSkip);
  }

  $campsNeedReminder = $campsQuery->execute();

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
