<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\HelperService;

/**
 * Goonjcustom.UpdateCollectionCampStatusCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_update_collection_camp_status_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.UpdateCollectionCampStatusCron API.
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
function civicrm_api3_goonjcustom_collection_camp_outcome_reminder_cron($params) {
  $returnValues = [];
  $now = new DateTimeImmutable();
  $endOfDay = $now->setTime(23, 59, 59)->format('Y-m-d H:i:s');
  $from = HelperService::getDefaultFromEmail();

  $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect('id')
    ->addWhere('subtype:name', '=', 'Collection_Camp')
    ->addWhere('Camp_Outcome.Rate_the_camp', 'IS NOT NULL')
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('Collection_Camp_Intent_Details.End_Date', '<=', $endOfDay)
    ->addWhere('Collection_Camp_Intent_Details.Camp_Status', '=', 'planned')
    ->execute();

  foreach ($collectionCamps as $camp) {
    try {
      $collectionSourceVehicleDispatches = EckEntity::get('Collection_Source_Vehicle_Dispatch', FALSE)
        ->addSelect('id')
        ->addWhere('subtype:name', '=', 'Vehicle_Dispatch')
        ->addWhere('Camp_Vehicle_Dispatch.Collection_Camp', '=', $camp)
        ->addWhere('Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office', 'IS NOT NULL')
        ->execute();

      if (!$collectionSourceVehicleDispatches) {
        return;
      }

      try {
        $currentDate = date('Y-m-d');
        EckEntity::update('Collection_Camp', FALSE)
          ->addWhere('id', '=', $collectionCampId)
          ->addValue('Collection_Camp_Intent_Details.Camp_Status', 'completed')
          ->addValue('Camp_Outcome.Camp_Status_Completion_Date', $currentDate)
          ->execute();

      }
      catch (\Exception $e) {
        \Civi::log()->error("Exception occurred while updating camp status for campId: $collectionCampId. Error: " . $e->getMessage());
      }
    }
    catch (\Exception $e) {
      \Civi::log()->error('Error processing camp', [
        'id' => $camp['id'],
        'error' => $e->getMessage(),
      ]);
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'collection_camp_outcome_reminder_cron');
}
