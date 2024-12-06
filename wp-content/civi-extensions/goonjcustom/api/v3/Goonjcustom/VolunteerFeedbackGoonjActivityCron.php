<?php

/**
 * @file
 */

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;
use Civi\GoonjActivitiesService;


/**
 * Goonjcustom.VolunteerFeedbackCollectionCampCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_volunteer_feedback_goonj_activity_cron_spec(&$spec) {
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
function civicrm_api3_goonjcustom_volunteer_feedback_goonj_activity_cron($params) {
  $returnValues = [];
  $optionValues = OptionValue::get(FALSE)
    ->addWhere('option_group_id:name', '=', 'eck_sub_types')
    ->addWhere('name', '=', 'Goonj_activities')
    ->addWhere('grouping', '=', 'Collection_Camp')
    ->execute()->single();

  $collectionCampSubtype = $optionValues['value'];
  $today = new DateTime();
  $today->setTime(23, 59, 59);
  $endOfDay = $today->format('Y-m-d H:i:s');
  $todayFormatted = $today->format('Y-m-d');

  $collectionCamps = EckEntity::get('Collection_Camp', TRUE)
    ->addSelect('Goonj_Activities.End_Date', 'Logistics_Coordination.Feedback_Email_Sent', 'Collection_Camp_Core_Details.Contact_Id', 'Goonj_Activities.Where_do_you_wish_to_organise_the_activity_', 'Goonj_Activities.Select_Volunteer_Feedback_Form')
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('subtype', '=', $collectionCampSubtype)
    ->addWhere('Collection_Camp_Intent_Details.End_Date', '<=', $endOfDay)
    ->execute();


  foreach ($collectionCamps as $camp) {
    try {
      GoonjActivitiesService::sendActivityVolunteerFeedbackEmail($camp);
    }
    catch (Exception $e) {
      \Civi::log()->info("Error processing camp ID $collectionCampId: " . $e->getMessage());
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'volunteer_feedback_goonj_activity_cron');
}
