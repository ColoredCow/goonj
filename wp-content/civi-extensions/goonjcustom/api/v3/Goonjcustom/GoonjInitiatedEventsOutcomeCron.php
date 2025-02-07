<?php

/**
 * @file
 */

use Civi\Api4\Event;
use Civi\GoonjInitiatedEventsService;

/**
 * Goonjcustom.GoonjInititatedEventsOutcomeCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_goonj_initiated_events_outcome_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.GoonjInititatedEventsOutcomeCron API.
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
function civicrm_api3_goonjcustom_goonj_initiated_events_outcome_cron($params) {
  $returnValues = [];
  $today = new DateTimeImmutable();
  $endOfDay = $today->setTime(23, 59, 59)->format('Y-m-d H:i:s');
  $events = Event::get(TRUE)
	->addSelect('title', 'loc_block_id.address_id', 'Goonj_Events.Goonj_Coordinating_POC_Main_', 'Goonj_Events.Goonj_Coordinating_POC', 'start_date', 'end_date', 'Goonj_Events_Outcome.Outcome_Email_Sent')
	->addWhere('start_date', '<=', $endOfDay)
	->addClause('OR',
		['Goonj_Events_Outcome.Outcome_Email_Sent', 'IS NULL'],
		['Goonj_Events_Outcome.Outcome_Email_Sent', '=', 0]
	)
	->addWhere('event_type_id:name', '!=', 'Rural Planned Visit')
	->execute();

  foreach ($events as $event) {

	try {
	  GoonjInitiatedEventsService::sendEventOutcomeEmail($event);
	}
	catch (\Exception $e) {
	  \Civi::log()->info('Error Goonj Events Outcome Cron', [
		'id' => $event['id'],
		'error' => $e->getMessage(),
	  ]);
	}
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'goonj_initiated_events_outcome_cron');
}
