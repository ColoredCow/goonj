<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\Api4\Activity;

/**
 *
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

  /**
   * --------------------------------------------------------------
   * STEP 1 — Fetch eligible camps (Outcome filled, authorized, etc.)
   * --------------------------------------------------------------
   */

  $eligibleCampIds = [];

  $eligibleCampsQuery = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect('id')
    ->addWhere('subtype:name', '=', 'Institution_Collection_Camp')
    ->addWhere('Camp_Outcome.Rate_the_camp', 'IS NOT NULL')
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('Institution_Collection_Camp_Intent.Collections_will_end_on_Date_', '<=', $endOfDay)
    ->addWhere('Institution_collection_camp_Review.Camp_Status', '=', 1)
    ->execute();

  foreach ($eligibleCampsQuery as $camp) {
    $eligibleCampIds[] = $camp['id'];
  }

  $campIdsToUpdate = [];

  /**
   * --------------------------------------------------------------
   * STEP 2 — For each eligible camp, check Dispatch OR IMC Activity
   * --------------------------------------------------------------
   */

  foreach ($eligibleCampIds as $campId) {

    $markComplete = FALSE;

    /**
     * CONDITION A — Outcome + Dispatch Acknowledgement exists
     */
    $dispatches = EckEntity::get('Collection_Source_Vehicle_Dispatch', FALSE)
      ->addSelect('id')
      ->addWhere('subtype:name', '=', 'Vehicle_Dispatch')
      ->addWhere('Camp_Vehicle_Dispatch.Institution_Collection_Camp', '=', $campId)
      ->addWhere('Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office', 'IS NOT NULL')
      ->execute();

    if ($dispatches->count() > 0) {
      $markComplete = TRUE;
    }

    /**
     * CONDITION B — Outcome + IMC Activity exists
     * (Evaluate only if dispatch condition not met)
     */
    if (!$markComplete) {
      $activities = Activity::get(FALSE)
        ->addSelect('id')
        ->addWhere('activity_type_id:label', '=', 'Institution Material Contribution')
        ->addWhere('Institution_Material_Contribution.Collection_Camp', '=', $campId)
        ->addWhere('Institution_Material_Contribution.Material_Type', 'IS NOT EMPTY')
        ->execute();

      if ($activities->count() > 0) {
        $markComplete = TRUE;
      }
    }

    /**
     * Add to update queue
     */
    if ($markComplete) {
      $campIdsToUpdate[] = $campId;
    }
  }

  /**
   * --------------------------------------------------------------
   * STEP 3 — Update all qualified camps to Completed (3)
   * --------------------------------------------------------------
   */
  foreach ($campIdsToUpdate as $campId) {
    try {
      EckEntity::update('Collection_Camp', FALSE)
        ->addWhere('id', '=', $campId)
        ->addValue('Institution_collection_camp_Review.Camp_Status', 3)
        ->execute();

      $returnValues[] = [
        'camp_id'  => $campId,
        'status'   => 'updated',
      ];
    }
    catch (\Exception $e) {
      \Civi::log()->error("Error processing campId: {$campId}. Error: " . $e->getMessage());
      $returnValues[] = [
        'camp_id'  => $campId,
        'status'   => 'error',
        'message'  => $e->getMessage(),
      ];
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'update_institute_collection_camp_status_cron');
}
