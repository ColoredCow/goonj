<?php

/**
 * @file
 */

use Civi\Api4\Activity;
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

  $fromEmail = OptionValue::get(FALSE)
    ->addSelect('label')
    ->addWhere('option_group_id:name', '=', 'from_email_address')
    ->addWhere('is_default', '=', TRUE)
    ->execute()->single();

  foreach ($collectionCamps as $camp) {
    try {
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

      // Get initiator email.
      $initiatorEmail = Email::get(TRUE)
        ->addWhere('contact_id', '=', $initiatorId)
        ->execute()->single();

      $contactEmailId = $initiatorEmail['email'];

      $initiator = Contact::get(TRUE)
        ->addWhere('id', '=', $initiatorId)
        ->execute()->single();
      $organizingContactName = $initiator['display_name'];

      // Get recipient email.
      $email = Email::get(TRUE)
        ->addWhere('contact_id', '=', $recipientId)
        ->execute()->single();

      $emailId = $email['email'];

      $contact = Contact::get(TRUE)
        ->addWhere('id', '=', $recipientId)
        ->execute()->single();

      $contactName = $contact['display_name'];

      // Send email if the end date is today or earlier.
      if ($endDateFormatted <= $todayFormatted) {
        $mailParams = [
          'subject' => 'Your Feedback and experience on the recent collection camp',
          'from' => $fromEmail['label'],
          'toEmail' => $contactEmailId,
          'replyTo' => $fromEmail['label'],
          'html' => goonjcustom_collection_camp_volunteer_feedback_email_html($organizingContactName, $collectionCampId),
        ];
        $result = CRM_Utils_Mail::send($mailParams);
      }

      // Process activities.
      $activities = Activity::get(FALSE)
        ->addSelect('id')
        ->addWhere('Material_Contribution.Collection_Camp', '=', $collectionCampId)
        ->execute();

      $contributorCount = count($activities);

      $results = EckEntity::update('Collection_Camp', FALSE)
        ->addValue('Camp_Outcome.Number_of_Contributors', $contributorCount)
        ->addWhere('id', '=', $collectionCampId)
        ->execute();

      // Send completion notification.
      if ($endDateFormatted <= $todayFormatted) {
        $mailParams = [
          'subject' => 'Important Forms to Complete During and After the Camp',
          'from' => 'urban.ops@goonj.org',
          'toEmail' => $emailId,
          'replyTo' => 'urban.ops@goonj.org',
          'html' => goonjcustom_collection_camp_email_html($contactName, $collectionCampId, $recipientId, $collectionCampGoonjOffice),
        ];
        $result = CRM_Utils_Mail::send($mailParams);
      }
    }
    catch (Exception $e) {
      \Civi::log()->info("Error processing camp ID $collectionCampId: " . $e->getMessage());
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
      <p>Thank you for attending the camp. There are two forms that require your attention during and after the camp:</p>
      <ol>
        <li>Dispatch Form – <a href=\"$campVehicleDispatchFormUrl\">[link]</a><br>
        Please complete this form from the camp location once the vehicle is being loaded and ready for dispatch to the Goonj's processing center.</li>
        <li>Camp Outcome Form – <a href=\"$campOutcomeFormUrl\">[link]</a><br>
        This feedback form should be filled out after the camp/drive ends, once you have an overview of the event's outcomes.</li>
      </ol>
      <p>We appreciate your cooperation.</p>
      <p>Warm Regards,<br>Urban Relations Team</p>";

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
      <p>Thank you for stepping up and organising the recent collection drive! Your time, effort, and enthusiasm made all the difference and we hope that it was meaningful effort for you as well.</p>
      <p>To help us improve, we’d love to hear your thoughts and experiences.Kindly take a few minutes to fill out our feedback form. Your input will be valuable to us.</p>
      <p><a href=\"$campVolunteerFeedback\">Feedback Form Link</a></p>
      <p>Feel free to share any highlights, suggestions, or challenges you faced. We're eager to learn how we can make it better together !</p>
      <p>We look forward to continuing this journey together !</p>
      <p>Warm Regards,<br>Team Goonj</p>";

  return $html;
}
