<?php

/**
 * @file
 */

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;

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
    ->addSelect('Logistics_Coordination.Camp_to_be_attended_by', 'Collection_Camp_Intent_Details.End_Date', 'Logistics_Coordination.Email_Sent')
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('subtype', '=', $collectionCampSubtype)
    ->addWhere('Collection_Camp_Intent_Details.End_Date', '<=', $endOfDay)
    ->addWhere('Logistics_Coordination.Camp_to_be_attended_by', 'IS NOT EMPTY')
    ->execute();

  [$defaultFromName, $defaultFromEmail] = CRM_Core_BAO_Domain::getNameAndEmail();
  $from = "\"$defaultFromName\" <$defaultFromEmail>";

  foreach ($collectionCamps as $camp) {
    try {
      $campAttendedById = $camp['Logistics_Coordination.Camp_to_be_attended_by'];
      $endDate = new DateTime($camp['Collection_Camp_Intent_Details.End_Date']);
      $collectionCampId = $camp['id'];
      $endDateFormatted = $endDate->format('Y-m-d');
      $logisticEmailSent = $camp['Logistics_Coordination.Email_Sent'];

      $collectionCamp = EckEntity::get('Collection_Camp', TRUE)
        ->addSelect('Collection_Camp_Intent_Details.Goonj_Office', 'Collection_Camp_Intent_Details.Location_Area_of_camp', 'title', 'Collection_Camp_Core_Details.Contact_Id')
        ->addWhere('id', '=', $collectionCampId)
        ->execute()->single();

      $collectionCampGoonjOffice = $collectionCamp['Collection_Camp_Intent_Details.Goonj_Office'];
      $initiatorId = $collectionCamp['Collection_Camp_Core_Details.Contact_Id'];
      $campAddress = $collectionCamp['Collection_Camp_Intent_Details.Location_Area_of_camp'];
      $campCode = $collectionCamp['title'];

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
      if (!$logisticEmailSent && $endDateFormatted <= $todayFormatted) {
        // Get recipient email and name.
        $campAttendedBy = Contact::get(TRUE)
          ->addSelect('email.email', 'display_name')
          ->addJoin('Email AS email', 'LEFT')
          ->addWhere('id', '=', $initiatorId)
          ->execute()->single();

        $emailId = $campAttendedBy['email.email'];
        $contactName = $campAttendedBy['display_name'];

        $mailParams = [
          'subject' => 'Collection Camp Completion Notification: ' . $campCode . ' at ' . $campAddress,
          'from' => $from,
          'toEmail' => $emailId,
          'replyTo' => $from,
          'html' => goonjcustom_collection_camp_email_html($contactName, $collectionCampId, $campAttendedById, $collectionCampGoonjOffice, $campCode, $campAddress),
        ];
        $completionEmailSendResult = CRM_Utils_Mail::send($mailParams);

        if ($completionEmailSendResult) {
          EckEntity::update('Collection_Camp', TRUE)
            ->addValue('Logistics_Coordination.Email_Sent', 1)
            ->addWhere('id', '=', $collectionCampId)
            ->execute();
        }
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
function goonjcustom_collection_camp_email_html($contactName, $collectionCampId, $campAttendedById, $collectionCampGoonjOffice, $campCode, $campAddress) {
  $homeUrl = \CRM_Utils_System::baseCMSURL();
  // Construct the full URLs for the forms.
  $campVehicleDispatchFormUrl = $homeUrl . 'camp-vehicle-dispatch-form/#?Camp_Vehicle_Dispatch.Collection_Camp_Intent_Id=' . $collectionCampId . '&Camp_Vehicle_Dispatch.Filled_by=' . $campAttendedById . '&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent=' . $collectionCampGoonjOffice . '&Eck_Collection_Camp1=' . $collectionCampId;
  $campOutcomeFormUrl = $homeUrl . '/camp-outcome-form/#?Eck_Collection_Camp1=' . $collectionCampId . '&Camp_Outcome.Filled_By=' . $campAttendedById;

  $html = "
      <p>Dear $contactName,</p>
      <p>Thank you for attending the camp <strong>$campCode</strong> at <strong>$campAddress</strong>. There are two forms that require your attention during and after the camp:</p>
      <ol>
          <li><a href=\"$campVehicleDispatchFormUrl\">Dispatch Form</a><br>
          Please complete this form from the camp location once the vehicle is being loaded and ready for dispatch to the Goonj's processing center.</li>
          <li><a href=\"$campOutcomeFormUrl\">Camp Outcome Form</a><br>
          This feedback form should be filled out after the camp/drive ends, once you have an overview of the event's outcomes.</li>
      </ol>
      <p>We appreciate your cooperation.</p>
      <p>Warm Regards,<br>Urban Relations Team</p>";

  return $html;
}
