InstitutionPocFeedbackGoonjActivityCron.php
<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;
use Civi\InstitutionGoonjActivitiesService;

/**
 * Goonjcustom.InstitutionPocFeedbackGoonjActivityCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_institution_poc_feedback_goonj_activity_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.GoonjActivitiesCron API.
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
function civicrm_api3_goonjcustom_institution_poc_feedback_goonj_activity_cron($params) {
  $returnValues = [];
  $optionValues = OptionValue::get(FALSE)
      ->addWhere('option_group_id:name', '=', 'eck_sub_types')
      ->addWhere('name', '=', 'Institution_Goonj_Activities')
      ->addWhere('grouping', '=', 'Collection_Camp')
      ->execute()->single();

  $collectionCampSubtype = $optionValues['value'];
  $today = new DateTime();
  $today->setTime(23, 59, 59);
  $endOfDay = $today->format('Y-m-d H:i:s');
  $todayFormatted = $today->format('Y-m-d');


  $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect('Institution_Goonj_Activities.End_Date', 'Logistics_Coordination.Feedback_Email_Sent', 'Institution_Goonj_Activities.Institution_POC', 'Institution_Goonj_Activities.Where_do_you_wish_to_organise_the_activity_', 'Institution_Goonj_Activities.Select_Institute_POC_Feedback_Form')
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('subtype', '=', $collectionCampSubtype)
    ->addWhere('Institution_Goonj_Activities.End_Date', '<=', $endOfDay)
    ->execute();

  foreach ($collectionCamps as $camp) {
    try {
        InstitutionGoonjActivitiesService::getInstitutionPocActivitiesFeedbackEmailHtml($camp);
    }
    catch (Exception $e) {
      \Civi::log()->info("Error processing camp ID $collectionCampId: " . $e->getMessage());
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'institution_poc_feedback_goonj_activity_cron');
}
