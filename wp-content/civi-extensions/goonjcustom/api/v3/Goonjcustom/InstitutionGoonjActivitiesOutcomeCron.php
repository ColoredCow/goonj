<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;
use Civi\InstitutionGoonjActivitiesService;

/**
 * Goonjcustom.InstitutionGoonjActivitiesOutcomeCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_institution_goonj_activities_outcome_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.InstitutionGoonjActivitiesOutcomeCron API.
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
function civicrm_api3_goonjcustom_institution_goonj_activities_outcome_cron($params) {
  $returnValues = [];

  $optionValues = OptionValue::get(FALSE)
    ->addWhere('option_group_id:name', '=', 'eck_sub_types')
    ->addWhere('name', '=', 'Institution_Goonj_Activities')
    ->addWhere('grouping', '=', 'Collection_Camp')
    ->execute()->single();

  $collectionCampSubtype = $optionValues['value'];

  $today = new DateTimeImmutable();
  $endOfDay = $today->setTime(23, 59, 59)->format('Y-m-d H:i:s');

  $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect(
      'title',
      'Logistics_Coordination.Camp_to_be_attended_by',
      'Institution_Goonj_Activities.Start_Date',
      'Logistics_Coordination.Email_Sent',
      'Institution_Goonj_Activities.Goonj_Office',
      'Institution_Goonj_Activities.Where_do_you_wish_to_organise_the_activity_',
      'Institution_Goonj_Activities.Institution_POC',
      'Institution_Goonj_Activities.Select_Goonj_POC_Attendee_Outcome_Form'
    )
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('subtype', '=', $collectionCampSubtype)
    ->addWhere('Institution_Goonj_Activities.Start_Date', '<=', $endOfDay)
    ->addWhere('Logistics_Coordination.Camp_to_be_attended_by', 'IS NOT EMPTY')
    ->addClause('OR',
      ['Logistics_Coordination.Email_Sent', 'IS NULL'],
      ['Logistics_Coordination.Email_Sent', '=', 0]
    )
    ->execute();
    

  foreach ($collectionCamps as $camp) {

    try {
        InstitutionGoonjActivitiesService::sendInsitutionActivityLogisticsEmail($camp);
    }
    catch (\Exception $e) {
      \Civi::log()->info('Error Goonj Activities Cron', [
        'id' => $camp['id'],
        'error' => $e->getMessage(),
      ]);
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'institution_goonj_activities_outcome_cron');
}
