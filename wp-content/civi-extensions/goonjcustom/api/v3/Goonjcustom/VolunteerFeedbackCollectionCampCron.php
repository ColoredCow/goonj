<?php

/**
 * @file
 */

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;

/**
 * Goonjcustom.VolunteerFeedbackCollectionCampCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_volunteer_feedback_collection_camp_cron_spec(&$spec) {
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
function civicrm_api3_goonjcustom_volunteer_feedback_collection_camp_cron($params) {
  $returnValues = [];
  $optionValues = OptionValue::get(FALSE)
    ->addWhere('option_group_id:name', '=', 'eck_sub_types')
    ->addWhere('name', '=', 'Collection_Camp')
    ->addWhere('grouping', '=', 'Collection_Camp')
    ->setLimit(1)
    ->execute()->single();

  $collectionCampSubtype = $optionValues['value'];
  $today = new DateTime();
  $today->setTime(23, 59, 59);
  $endOfDay = $today->format('Y-m-d H:i:s');
  $todayFormatted = $today->format('Y-m-d');

  $collectionCamps = EckEntity::get('Collection_Camp', TRUE)
    ->addSelect('Collection_Camp_Intent_Details.End_Date', 'Logistics_Coordination.Feedback_Email_Sent', 'Collection_Camp_Core_Details.Contact_Id', 'Collection_Camp_Intent_Details.Location_Area_of_camp')
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('subtype', '=', $collectionCampSubtype)
    ->addWhere('Collection_Camp_Intent_Details.End_Date', '<=', $endOfDay)
    ->addWhere('Collection_Camp_Intent_Details.Camp_Status', '!=', 'aborted')
    ->execute();

  [$defaultFromName, $defaultFromEmail] = CRM_Core_BAO_Domain::getNameAndEmail();
  $from = "\"$defaultFromName\" <$defaultFromEmail>";

  foreach ($collectionCamps as $camp) {
    try {
      $endDate = new DateTime($camp['Collection_Camp_Intent_Details.End_Date']);
      $collectionCampId = $camp['id'];
      $endDateFormatted = $endDate->format('Y-m-d');
      $feedbackEmailSent = $camp['Logistics_Coordination.Feedback_Email_Sent'];
      $initiatorId = $camp['Collection_Camp_Core_Details.Contact_Id'];
      $campAddress = $camp['Collection_Camp_Intent_Details.Location_Area_of_camp'];

      // Get recipient email and name.
      $campAttendedBy = Contact::get(TRUE)
        ->addSelect('email.email', 'display_name')
        ->addJoin('Email AS email', 'LEFT')
        ->addWhere('id', '=', $initiatorId)
        ->execute()->single();

      $contactEmailId = $campAttendedBy['email.email'];
      $organizingContactName = $campAttendedBy['display_name'];

      // Send email if the end date is today or earlier.
      if (!$feedbackEmailSent && $endDateFormatted <= $todayFormatted) {
        $mailParams = [
          'subject' => 'Thank You for Organizing the Camp! Share Your Feedback.',
          'from' => $from,
          'toEmail' => $contactEmailId,
          'replyTo' => $from,
          'html' => goonjcustom_volunteer_feedback_collection_camp_email_html($organizingContactName, $collectionCampId, $campAddress),
        ];
        $feedbackEmailSendResult = CRM_Utils_Mail::send($mailParams);

        if ($feedbackEmailSendResult) {
          EckEntity::update('Collection_Camp', TRUE)
            ->addValue('Logistics_Coordination.Feedback_Email_Sent', 1)
            ->addWhere('id', '=', $collectionCampId)
            ->execute();
        }
      }

    }
    catch (Exception $e) {
      \Civi::log()->info("Error processing camp ID $collectionCampId: " . $e->getMessage());
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'volunteer_feedback_collection_camp_cron');
}

/**
 *
 */
function goonjcustom_volunteer_feedback_collection_camp_email_html($organizingContactName, $collectionCampId, $campAddress) {
  $homeUrl = \CRM_Utils_System::baseCMSURL();

  // URL for the volunteer feedback form.
  $campVolunteerFeedback = $homeUrl . 'volunteer-camp-feedback/#?Eck_Collection_Camp1=' . $collectionCampId;

  $html = "
      <p>Dear $organizingContactName,</p>
      <p>Thank you for stepping up and organising the recent collection drive at <strong>$campAddress</strong>! Your time, effort, and enthusiasm made all the difference, and we hope that it was a meaningful effort for you as well.</p>
      <p>To help us improve, we’d love to hear your thoughts and experiences. Kindly take a few minutes to fill out our feedback form. Your input will be valuable to us:</p>
      <p><a href=\"$campVolunteerFeedback\">Feedback Form Link</a></p>
      <p>Feel free to share any highlights, suggestions, or challenges you faced. We're eager to learn how we can make it better together!</p>
      <p>We look forward to continuing this journey together!</p>
      <p>Warm Regards,<br>Team Goonj</p>";

  return $html;
}
