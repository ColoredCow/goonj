<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\Api4\Activity;

/**
 * API spec.
 */
function _civicrm_api3_goonjcustom_update_institute_collection_camp_status_cron_spec(&$spec) {
  // No params.
}

/**
 * Goonjcustom.UpdateInstitutionCollectionCampStatusCron API.
 */
function civicrm_api3_goonjcustom_update_institute_collection_camp_status_cron($params) {
  $returnValues = [];
  $now = new DateTimeImmutable();
  $endOfDay = $now->setTime(23, 59, 59)->format('Y-m-d H:i:s');

  $campIdsToUpdate = [];

  /**
   * -------------------------------------------
   * CASE 1: Activity-based camp completion
   * -------------------------------------------
   */
  $activities = Activity::get(FALSE)
    ->addSelect('Institution_Material_Contribution.Collection_Camp')
    ->addWhere('activity_type_id:label', '=', 'Institution Material Contribution')
    ->addWhere('Institution_Material_Contribution.Material_Type', 'IS NOT EMPTY')
    ->addWhere('Institution_Material_Contribution.Collection_Camp', 'IS NOT EMPTY')
    ->execute();

  foreach ($activities as $activity) {
    $campId = $activity['Institution_Material_Contribution.Collection_Camp'];
    if (!empty($campId)) {
      // Add to unique list.
      $campIdsToUpdate[$campId] = TRUE;
    }
  }

  /**
   * -------------------------------------------
   * CASE 2: Vehicle Dispatch–based completion
   * -------------------------------------------
   */
  $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect('id')
    ->addWhere('subtype:name', '=', 'Institution_Collection_Camp')
    ->addWhere('Camp_Outcome.Rate_the_camp', 'IS NOT NULL')
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('Institution_Collection_Camp_Intent.Collections_will_end_on_Date_', '<=', $endOfDay)
    ->addWhere('Institution_collection_camp_Review.Camp_Status', '=', 1)
    ->execute();

  foreach ($collectionCamps as $camp) {
    $campId = $camp['id'];

    $dispatches = EckEntity::get('Collection_Source_Vehicle_Dispatch', FALSE)
      ->addSelect('id')
      ->addWhere('subtype:name', '=', 'Vehicle_Dispatch')
      ->addWhere('Camp_Vehicle_Dispatch.Institution_Collection_Camp', '=', $campId)
      ->addWhere('Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office', 'IS NOT NULL')
      ->execute();

    if ($dispatches->count()) {
      // Add to unique list.
      $campIdsToUpdate[$campId] = TRUE;
    }
  }

  /**
   * -------------------------------------------
   * UPDATE CAMPS (unique list)
   * -------------------------------------------
   */
  foreach (array_keys($campIdsToUpdate) as $campId) {
    try {
      EckEntity::update('Collection_Camp', FALSE)
        ->addWhere('id', '=', $campId)
        ->addValue('Institution_collection_camp_Review.Camp_Status', 3)
        ->execute();

      $returnValues[] = [
        'camp_id' => $campId,
        'status' => 'updated',
      ];
    }
    catch (\Exception $e) {
      \Civi::log()->error("Error processing campId: {$campId}. Error: " . $e->getMessage());
      $returnValues[] = [
        'camp_id' => $campId,
        'status' => 'error',
        'message' => $e->getMessage(),
      ];
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'update_institute_collection_camp_status_cron');
}
