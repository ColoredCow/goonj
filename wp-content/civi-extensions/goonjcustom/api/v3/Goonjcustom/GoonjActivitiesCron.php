<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;
use Civi\GoonjActivitiesService;

/**
 * Goonjcustom.CollectionCampCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_goonj_activities_cron_spec(&$spec) {
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
function civicrm_api3_goonjcustom_goonj_activities_cron($params) {
  $returnValues = [];

  $optionValues = OptionValue::get(FALSE)
    ->addWhere('option_group_id:name', '=', 'eck_sub_types')
    ->addWhere('name', '=', 'Goonj_activities')
    ->addWhere('grouping', '=', 'Collection_Camp')
    ->setLimit(1)
    ->execute()->single();


  $collectionCampSubtype = $optionValues['value'];
  \Civi::log()->info('optionValues', ['optionValues'=>$collectionCampSubtype]);
  $today = new DateTimeImmutable();
  $endOfDay = $today->setTime(23, 59, 59)->format('Y-m-d H:i:s');


  $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect(
      'title',
      'Logistics_Coordination.Camp_to_be_attended_by',
      'Goonj_Activities.Start_Date',
      'Logistics_Coordination.Email_Sent',
      'Goonj_Activities.Goonj_Office',
      'Goonj_Activities.Where_do_you_wish_to_organise_the_activity_',
      'Collection_Camp_Core_Details.Contact_Id',
      'Goonj_Activities.Select_Goonj_POC_Attendee_Outcome_Form'
    )
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('subtype', '=', $collectionCampSubtype)
    ->addWhere('Goonj_Activities.Start_Date', '<=', $endOfDay)
    ->addWhere('Logistics_Coordination.Camp_to_be_attended_by', 'IS NOT EMPTY')
    ->addClause('OR',
      ['Logistics_Coordination.Email_Sent', 'IS NULL'],
      ['Logistics_Coordination.Email_Sent', '=', 0]
    )
    ->execute();
  \Civi::log()->info('collectionCamps', ['collectionCamps'=>$collectionCamps]);


  foreach ($collectionCamps as $camp) {

    try {
      GoonjActivitiesService::sendActivityLogisticsEmail($camp);
    //   GoonjActivitiesService::updateContributionCount($camp);
    }
    catch (\Exception $e) {
      \Civi::log()->info('Error Goonj Activities Cron', [
        'id' => $camp['id'],
        'error' => $e->getMessage(),
      ]);
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'goonj_activities_cron');
}
