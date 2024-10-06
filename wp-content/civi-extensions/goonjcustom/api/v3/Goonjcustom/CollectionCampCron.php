<?php

/**
 * @file
 */

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
  $endOfDay = $today->format('Y-m-d H:i:s');

  $collectionCamps = EckEntity::get('Collection_Camp', TRUE)
    ->addSelect(
      'title',
      'Logistics_Coordination.Camp_to_be_attended_by',
      'Collection_Camp_Intent_Details.Start_Date',
      'Logistics_Coordination.Email_Sent',
      'Collection_Camp_Intent_Details.Goonj_Office',
      'Collection_Camp_Intent_Details.Location_Area_of_camp',
      'Collection_Camp_Core_Details.Contact_Id',
    )
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('subtype', '=', $collectionCampSubtype)
    ->addWhere('Collection_Camp_Intent_Details.Start_Date', '<=', $endOfDay)
    ->addWhere('Logistics_Coordination.Camp_to_be_attended_by', 'IS NOT EMPTY')
    ->execute();

  [$defaultFromName, $defaultFromEmail] = CRM_Core_BAO_Domain::getNameAndEmail();
  $from = "\"$defaultFromName\" <$defaultFromEmail>";

  foreach ($collectionCamps as $camp) {
    try {
      CollectionCampService::sendLogisticsEmail($camp);
      CollectionCampService::updateContributorCount($camp);

    }
    catch (Exception $e) {
      \Civi::log()->info('Error processing camp', [
        'id' => $id,
        'error' => $e->getMessage(),
      ]);
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
  $campVehicleDispatchFormUrl = $homeUrl . 'camp-vehicle-dispatch-form/#?Camp_Vehicle_Dispatch.Collection_Camp=' . $collectionCampId . '&Camp_Vehicle_Dispatch.Filled_by=' . $campAttendedById . '&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent=' . $collectionCampGoonjOffice . '&Eck_Collection_Camp1=' . $collectionCampId;
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
