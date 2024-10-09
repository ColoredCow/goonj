<?php

/**
 * @file
 */

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
< ? php

/**
 * Cron job to send reminder emails to volunteers who haven't filled the feedback form.
 */
function civicrm_api3_goonjcustom_volunteer_feedback_reminder_cron($params) {
  $returnValues = [];
  $today = new DateTimeImmutable();
  $endOfDay = $today->setTime(23, 59, 59)->format('Y-m-d H:i:s');

  // Fetch camps that have completed and volunteers have not filled the feedback form.
  $volunteerCamps = EckEntity::get('Collection_Camp', TRUE)
    ->addSelect('Volunteer_Camp_Feedback.Last_Reminder_Sent', 'Collection_Camp_Intent_Details.Location_Area_of_camp', 'Collection_Camp_Intent_Details.End_Date', 'Collection_Camp_Core_Details.Contact_Id')
    ->addWhere('Volunteer_Camp_Feedback.Give_Rating_to_your_camp', 'IS NULL')
    ->addWhere('Logistics_Coordination.Feedback_Email_Sent', '=', 1)
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('Collection_Camp_Intent_Details.End_Date', '<=', $endOfDay)
    ->execute();

  [$defaultFromName, $defaultFromEmail] = CRM_Core_BAO_Domain::getNameAndEmail();
  $from = "\"$defaultFromName\" <$defaultFromEmail>";

  foreach ($volunteerCamps as $camp) {
    try {
      $volunteerContactId = $camp['Collection_Camp_Core_Details.Contact_Id'];
      // Get recipient email and name.
      $campAttendedBy = Contact::get(TRUE)
        ->addSelect('email.email', 'display_name')
        ->addJoin('Email AS email', 'LEFT')
        ->addWhere('id', '=', $volunteerContactId)
        ->execute()->single();

      $volunteerEmailId = $campAttendedBy['email.email'];
      $organizingContactName = $campAttendedBy['display_name'];

      $endDate = new \DateTime($camp['Collection_Camp_Intent_Details.End_Date']);
      $collectionCampId = $camp['id'];
      $campAddress = $camp['Collection_Camp_Intent_Details.Location_Area_of_camp'];

      $lastReminderSent = $camp['Volunteer_Camp_Feedback.Last_Reminder_Sent'] ? new \DateTime($camp['Volunteer_Camp_Feedback.Last_Reminder_Sent']) : NULL;

      // Calculate hours since camp ended.
      $hoursSinceCampEnd = $today->diff($endDate)->h + ($today->diff($endDate)->days * 24);

      // Check if feedback form is not filled and 24 hours have passed since camp end.
      if ($hoursSinceCampEnd >= 24 && ($lastReminderSent === NULL)) {
        // Send the first reminder email to the volunteer.
        CollectionCampOutcomeService::sendVolunteerFeedbackReminderEmail($volunteerEmailId, $from, $campAddress, $collectionCampId, $endDate);

        // Update the Last_Reminder_Sent field in the database to avoid duplicate reminders.
        EckEntity::update('Collection_Camp', TRUE)
          ->addWhere('id', '=', $camp['id'])
          ->addValue('Volunteer_Camp_Feedback.Last_Reminder_Sent', $today->format('Y-m-d H:i:s'))
          ->execute();
      }
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
