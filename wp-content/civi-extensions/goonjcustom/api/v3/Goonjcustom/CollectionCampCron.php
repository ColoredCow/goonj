<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;
use Civi\CollectionCampService;

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
  $today = new DateTimeImmutable();
  $endOfDay = $today->setTime(23, 59, 59)->format('Y-m-d H:i:s');

  $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
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
    ->addWhere('Collection_Camp_Intent_Details.Camp_Status', '!=', 'aborted')
    ->addClause('OR',
      ['Logistics_Coordination.Email_Sent', 'IS NULL'],
      ['Logistics_Coordination.Email_Sent', '=', 0]
    )
    ->execute();

  foreach ($collectionCamps as $camp) {

    try {
      CollectionCampService::sendLogisticsEmail($camp);
      CollectionCampService::updateContributorCount($camp);

    }
    catch (\Exception $e) {
      \Civi::log()->info('Error processing camp', [
        'id' => $camp['id'],
        'error' => $e->getMessage(),
      ]);
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'collection_camp_cron');
}
