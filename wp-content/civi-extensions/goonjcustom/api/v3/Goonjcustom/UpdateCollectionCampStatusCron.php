<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\HelperService;

/**
 * API spec.
 */
function _civicrm_api3_goonjcustom_update_collection_camp_status_cron_spec(&$spec) {
  // No params.
}

/**
 * Goonjcustom.UpdateCollectionCampStatusCron API.
 */
function civicrm_api3_goonjcustom_update_collection_camp_status_cron($params) {
  $returnValues = [];
  $now = new DateTimeImmutable();
  $endOfDay = $now->setTime(23, 59, 59)->format('Y-m-d H:i:s');

  // Get all relevant collection camps.
  $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect('id')
    ->addWhere('subtype:name', '=', 'Collection_Camp')
    ->addWhere('Camp_Outcome.Rate_the_camp', 'IS NOT NULL')
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('Collection_Camp_Intent_Details.End_Date', '<=', $endOfDay)
    ->addWhere('Collection_Camp_Intent_Details.Camp_Status', '=', 'planned')
    ->execute();

  foreach ($collectionCamps as $camp) {
    $collectionCampId = $camp['id'];

    try {
      $collectionSourceVehicleDispatches = EckEntity::get('Collection_Source_Vehicle_Dispatch', FALSE)
        ->addSelect('id')
        ->addWhere('subtype:name', '=', 'Vehicle_Dispatch')
        ->addWhere('Camp_Vehicle_Dispatch.Collection_Camp', '=', $collectionCampId)
        ->addWhere('Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office', 'IS NOT NULL')
        ->execute();

      // Skip this camp if no dispatch found.
      if (empty($collectionSourceVehicleDispatches)) {
        continue;
      }

      // Update the camp status to completed.
      $currentDate = date('Y-m-d');
      EckEntity::update('Collection_Camp', FALSE)
        ->addWhere('id', '=', $collectionCampId)
        ->addValue('Collection_Camp_Intent_Details.Camp_Status', 'completed')
        ->addValue('Camp_Outcome.Camp_Status_Completion_Date', $currentDate)
        ->execute();

      $returnValues[] = [
        'camp_id' => $collectionCampId,
        'status' => 'updated',
      ];
    }
    catch (\Exception $e) {
      \Civi::log()->error("Error processing campId: {$collectionCampId}. Error: " . $e->getMessage());
      $returnValues[] = [
        'camp_id' => $collectionCampId,
        'status' => 'error',
        'message' => $e->getMessage(),
      ];
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'update_collection_camp_status_cron');
}
