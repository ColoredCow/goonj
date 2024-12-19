<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\UrbanPlannedVisitService;

/**
 * Goonjcustom.UrbanPlannedVisitOutcomeCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_urban_planned_visit_outcome_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.UrbanPlannedVisit API.
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
function civicrm_api3_goonjcustom_urban_planned_visit_outcome_cron($params) {
  $returnValues = [];

  $today = new DateTimeImmutable();
  $endOfDay = $today->setTime(23, 59, 59)->format('Y-m-d H:i:s');

  $institutionVisit = EckEntity::get('Institution_Visit', FALSE)
    ->addSelect('Urban_Planned_Visit.When_do_you_wish_to_visit_Goonj', 'Urban_Planned_Visit.Coordinating_Goonj_POC')
    ->addWhere('Urban_Planned_Visit.Coordinating_Goonj_POC', 'IS NOT NULL')
    ->addWhere('Urban_Planned_Visit.When_do_you_wish_to_visit_Goonj', '<=', $endOfDay)
    ->execute();

  error_log("institutionVisit: " . print_r($institutionVisit, TRUE));

  foreach ($institutionVisit as $visit) {
    try {
      UrbanPlannedVisitService::sendOutcomeEmail($visit);
    }
    catch (\Exception $e) {
      \Civi::log()->info('Error processing visit', [
        'error' => $e->getMessage(),
      ]);
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'urban_planned_visit_outcome_cron');
}
