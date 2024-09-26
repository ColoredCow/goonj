<?php

/**
 * @file
 */

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Api4\Email;
use Civi\Api4\OptionValue;

/**
 * @file
 */

/**
 * Goonjcustom.CollectionCampCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_collection_camp_cron_spec(&$spec) {
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
function civicrm_api3_goonjcustom_collection_camp_cron($params) {
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
    ->addSelect('Logistics_Coordination.Camp_to_be_attended_by', 'Collection_Camp_Intent_Details.End_Date')
    ->addWhere('subtype', '=', $collectionCampSubtype)
    ->addWhere('Collection_Camp_Intent_Details.End_Date', '<=', $endOfDay)
    ->addWhere('Logistics_Coordination.Camp_to_be_attended_by', 'IS NOT EMPTY')
    ->execute();

  foreach ($collectionCamps as $camp) {
    $recipientId = $camp['Logistics_Coordination.Camp_to_be_attended_by'];
    $endDate = new DateTime($camp['Collection_Camp_Intent_Details.End_Date']);
    $collectionCampId = $camp['id'];
    $endDateFormatted = $endDate->format('Y-m-d');
    $collectionCamp = EckEntity::get('Collection_Camp', TRUE)
      ->addSelect('Collection_Camp_Intent_Details.Goonj_Office', 'Collection_Camp_Core_Details.Contact_Id')
      ->addWhere('id', '=', $collectionCampId)
      ->execute()->single();
    $collectionCampGoonjOffice = $collectionCamp['Collection_Camp_Intent_Details.Goonj_Office'];
    $initiatorId = $collectionCamp['Collection_Camp_Core_Details.Contact_Id'];

    $initiatorEmail = Email::get(TRUE)
      ->addWhere('contact_id', '=', $initiatorId)
      ->execute()->single();

    $contactEmailId = $initiatorEmail['email'];

    $initiator = Contact::get(TRUE)
      ->addWhere('id', '=', $initiatorId)
      ->execute()->single();

    $organizingContactName = $initiator['display_name'];

    $email = Email::get(TRUE)
      ->addWhere('contact_id', '=', $recipientId)
      ->execute()->single();

    $emailId = $email['email'];

    $contact = Contact::get(TRUE)
      ->addWhere('id', '=', $recipientId)
      ->execute()->single();

    $contactName = $contact['display_name'];

    $fromEmail = OptionValue::get(FALSE)
    ->addSelect('label')
    ->addWhere('option_group_id:name', '=', 'from_email_address')
    ->addWhere('is_default', '=', TRUE)
    ->execute()->single();

    // Only send the email if the end date is exactly today.
    if ($endDateFormatted <= $todayFormatted) {
      $mailParams = [
        'subject' => 'Volunteer Feedback Form',
        'from' => $fromEmail['label'],
        'toEmail' => $contactEmailId,
        'replyTo' => $fromEmail['label'],
        'html' => goonjcustom_collection_camp_volunteer_feedback_email_html($organizingContactName, $collectionCampId),
        // 'messageTemplateID' => 76, // Uncomment if using a message template
      ];
      $result = CRM_Utils_Mail::send($mailParams);
    }

    // Only send the email if the end date is lower than today.
    if ($endDateFormatted <= $todayFormatted) {
      $mailParams = [
        'subject' => 'Collections Completion Notification',
        'from' => 'urban.ops@goonj.org',
        'toEmail' => $emailId,
        'replyTo' => 'urban.ops@goonj.org',
        'html' => goonjcustom_collection_camp_email_html($contactName, $collectionCampId, $recipientId, $collectionCampGoonjOffice),
        // 'messageTemplateID' => 76, // Uncomment if using a message template
      ];
      $result = CRM_Utils_Mail::send($mailParams);
    }
  }
  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'collection_camp_cron');
}

/**
 *
 */
function goonjcustom_collection_camp_email_html($contactName, $collectionCampId, $recipientId, $collectionCampGoonjOffice) {
  $homeUrl = \CRM_Utils_System::baseCMSURL();
  // Construct the full URLs for the forms.
  $campVehicleDispatchFormUrl = $homeUrl . 'camp-vehicle-dispatch-form/#?Camp_Vehicle_Dispatch.Collection_Camp_Intent_Id=' . $collectionCampId . '&Camp_Vehicle_Dispatch.Filled_by=' . $recipientId . '&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent=' . $collectionCampGoonjOffice;
  $campOutcomeFormUrl = $homeUrl . '/camp-outcome-form/#?Eck_Collection_Camp1=' . $collectionCampId . '&Camp_Outcome.Filled_By=' . $recipientId;
  $html = "
      <p>Dear $contactName,</p>
      <p>You have been selected as the Goonj user to attend the camp.</p>
      <p>Today the event has ended. Please find below the links for the Camp Vehicle Dispatch Form and the Camp Outcome Form:</p>
      <ul>
        <li><a href=\"$campVehicleDispatchFormUrl\">Camp Vehicle Dispatch Form</a></li>
        <li><a href=\"$campOutcomeFormUrl\">Camp Outcome Form</a></li>
      </ul>
      <p>Warm regards,</p>";
  return $html;
}

/**
 *
 */
function goonjcustom_collection_camp_volunteer_feedback_email_html($organizingContactName, $collectionCampId) {
  $homeUrl = \CRM_Utils_System::baseCMSURL();
  // URL for the volunteer feedback form.
  $campVolunteerFeedback = $homeUrl . 'volunteer-camp-feedback/#?Eck_Collection_Camp1=' . $collectionCampId;
  $html = "
      <p>Dear $organizingContactName,</p>
      <p>Thank you for successfully completing your Collection Camp.</p>
      <p>We would appreciate your feedback. Please use the link below to fill out the Volunteer Camp Feedback Form:</p>
      <ul>
        <li><a href=\"$campVolunteerFeedback\">Volunteer Camp Feedback Form</a></li>
      </ul>
      <p>Warm regards,</p>";
  return $html;
}
