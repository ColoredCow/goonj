<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;
use Civi\InstitutionCollectionCampService;

/**
 * Goonjcustom.InstitutionCollectionCampCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_institution_collection_camp_cron_spec(&$spec) {
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
function civicrm_api3_goonjcustom_institution_collection_camp_cron($params) {
  $returnValues = [];
  $optionValues = OptionValue::get(FALSE)
    ->addWhere('option_group_id:name', '=', 'eck_sub_types')
    ->addWhere('name', '=', 'Institution_Collection_Camp')
    ->addWhere('grouping', '=', 'Collection_Camp')
    ->setLimit(1)
    ->execute()->single();

  $collectionCampSubtype = $optionValues['value'];
  $today = new DateTimeImmutable();
  $endOfDay = $today->setTime(23, 59, 59)->format('Y-m-d H:i:s');

  $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect(
      'title',
      'Institution_Collection_Camp_Logistics.Email_Sent',
      'Institution_Collection_Camp_Logistics.Camp_to_be_attended_by',
      'Institution_Collection_Camp_Intent.Collections_will_start_on_Date_',
      'Institution_Collection_Camp_Intent.Collections_will_end_on_Date_',
      'Institution_collection_camp_Review.Goonj_Office',
      'Institution_Collection_Camp_Intent.Collection_Camp_Address',
      'Institution_Collection_Camp_Intent.Institution_POC',
      'Institution_Collection_Camp_Logistics.Self_Managed_by_Institution',
      'Institution_collection_camp_Review.Coordinating_POC',
    )
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('subtype', '=', $collectionCampSubtype)
    ->addWhere('Institution_Collection_Camp_Intent.Collections_will_start_on_Date_', '<=', $endOfDay)
    ->addWhere('Institution_Collection_Camp_Logistics.Self_Managed_by_Institution', 'IS NOT NULL')
    ->addWhere('Institution_collection_camp_Review.Camp_Status', '!=', 'aborted')
    ->addClause('OR',
      ['Institution_Collection_Camp_Logistics.Email_Sent', 'IS NULL'],
      ['Institution_Collection_Camp_Logistics.Email_Sent', '=', 0]
    )
    ->execute();

  foreach ($collectionCamps as $camp) {
    try {
      InstitutionCollectionCampService::sendLogisticsEmail($camp);
    }
    catch (\Exception $e) {
      \Civi::log()->info('Error processing camp', [
        'id' => $camp['id'],
        'error' => $e->getMessage(),
      ]);
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'institution_collection_camp_cron');
}
