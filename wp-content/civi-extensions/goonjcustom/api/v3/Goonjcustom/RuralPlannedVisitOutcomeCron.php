<?php

/**
 * @file
 */

use Civi\Api4\Event;
use Civi\RuralPlannedVisitService;

/**
 * Goonjcustom.RuralPlannedVisitOutcomeCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_rural_planned_visit_outcome_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.RuralPlannedVisitOutcomeCron API.
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
function civicrm_api3_goonjcustom_rural_planned_visit_outcome_cron($params) {
  $returnValues = [];

  $today = new DateTimeImmutable();
  $endOfDay = $today->setTime(23, 59, 59)->format('Y-m-d H:i:s');
  $events = Event::get(TRUE)
    ->addSelect('title', 'loc_block_id.address_id', 'Rural_Planned_Visit.Goonj_Coordinator', 'Rural_Planned_Visit.External_Coordinating_Poc', 'start_date', 'end_date', 'Rural_Planned_Visit_Outcome.Outcome_Email_Sent')
    ->addWhere('event_type_id:name', '=', 'Rural Planned Visit')
    ->addWhere('Rural_Planned_Visit.Status:name', '=', 'Authorized')
    ->addWhere('start_date', '=', $endOfDay)
    ->addClause('OR', ['Rural_Planned_Visit_Outcome.Outcome_Email_Sent', 'IS NULL'], ['Rural_Planned_Visit_Outcome.Outcome_Email_Sent', '=', FALSE])
    ->setLimit(25)
    ->execute();

  foreach ($events as $event) {

    try {
      RuralPlannedVisitService::sendRuralPlannedVisitOutcomeEmail($event);
    }
    catch (\Exception $e) {
      \Civi::log()->info('Error Rural Planned Visit Outcome Cron', [
        'id' => $event['id'],
        'error' => $e->getMessage(),
      ]);
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'rural_planned_visit_outcome_cron');
}
