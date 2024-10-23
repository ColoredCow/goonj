<?php

/**
 * @file
 */

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;

/**
 * Goonjcustom.FeedbackDroppingCenterCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_feedback_dropping_center_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.DroppingCenterCron API.
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
function civicrm_api3_goonjcustom_feedback_dropping_center_cron($params) {
  $returnValues = [];

  // Retrieve the Status option value.
  $optionValues = OptionValue::get(FALSE)
    ->addWhere('option_group_id:name', '=', 'eck_sub_types')
    ->addWhere('name', '=', 'Status')
    ->addWhere('grouping', '=', 'Dropping_Center_Meta')
    ->setLimit(1)
    ->execute()->single();

  $statusName = $optionValues['value'];

  $droppingCenterMetas = EckEntity::get('Dropping_Center_Meta', TRUE)
    ->addSelect('Status.Status:name', 'Status.Closing_Date', 'Dropping_Center_Meta.Dropping_Center', 'Dropping_Center_Meta.Dropping_Center.Collection_Camp_Core_Details.Contact_Id', 'Status.Feedback_Email_Delivered:name')
    ->addWhere('subtype', '=', $statusName)
    ->addWhere('Status.Status:name', '=', 'Parmanently_Closed')
    ->execute();

  [$defaultFromName, $defaultFromEmail] = CRM_Core_BAO_Domain::getNameAndEmail();
  $from = "\"$defaultFromName\" <$defaultFromEmail>";

  try {
    foreach ($droppingCenterMetas as $meta) {
      $droppingCenterId = $meta['Dropping_Center_Meta.Dropping_Center'];
      $initiatorId = $meta['Dropping_Center_Meta.Dropping_Center.Collection_Camp_Core_Details.Contact_Id'];
      $status = $meta['Status.Feedback_Email_Delivered:name'];

      // Get recipient email and name.
      $campAttendedBy = Contact::get(TRUE)
        ->addSelect('email.email', 'display_name')
        ->addJoin('Email AS email', 'LEFT')
        ->addWhere('id', '=', $initiatorId)
        ->execute()->single();

      $contactEmailId = $campAttendedBy['email.email'];
      $organizingContactName = $campAttendedBy['display_name'];
      if (!$status) {
        $mailParams = [
          'subject' => 'Your Feedback on Managing the Goonj Dropping Center',
          'from' => $from,
          'toEmail' => $contactEmailId,
          'replyTo' => $from,
          'html' => sendFeedbackEmail($organizingContactName, $droppingCenterId),
        ];
        $feedbackEmailSendResult = CRM_Utils_Mail::send($mailParams);

        if ($feedbackEmailSendResult) {
          EckEntity::update('Dropping_Center_Meta', TRUE)
            ->addValue('Status.Feedback_Email_Delivered:name', 1)
            ->addWhere('Dropping_Center_Meta.Dropping_Center', '=', $droppingCenterId)
            ->execute();
        }

      }
    }
  }
  catch (Exception $e) {
    error_log("Error processing: " . $e->getMessage());
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'feedback_dropping_center_cron');
}

/**
 * Generate HTML for feedback email.
 *
 * @param string $organizingContactName
 * @param int $droppingCenterId
 *
 * @return string HTML content for email
 */
function sendFeedbackEmail($organizingContactName, $droppingCenterId) {
  $homeUrl = \CRM_Utils_System::baseCMSURL();

  // URL for the  feedback form.
  $volunteerFeedback = $homeUrl . 'volunteer-feedback/#?Eck_Collection_Camp1=' . $droppingCenterId;

  $html = "
    <p>Dear $organizingContactName,</p>
  
    <p>Thank you for being such an outstanding representative of Goonj! 
    Your dedication, time, and passion are truly making a difference as we work to bring essential materials to remote villages across the country.</p>
  
    <p>As part of our commitment to constant improvement, we would greatly appreciate hearing about your experience managing the Dropping Center. 
    If you could spare a few moments to complete our feedback form, your input would be invaluable to us!</p>
  
    <p><a href='$volunteerFeedback'>Click here to access the feedback form.</a></p>
  
    <p>We encourage you to share any highlights, suggestions, or challenges you’ve encountered. Together, we can refine and enhance this process even further.</p>
  
    <p>We look forward to continuing this important journey with you!</p>
  
    <p>Warm Regards,<br>
    Team Goonj</p>
  ";

  return $html;
}
