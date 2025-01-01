<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\UrbanPlannedVisitService;

/**
 * Goonjcustom.UrbanReminderEmailToExternalCoordCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_urban_reminder_email_to_external_coord_cron_spec(&$spec) {
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
function civicrm_api3_goonjcustom_urban_reminder_email_to_external_coord_cron($params) {
  $returnValues = [];

  $today = new DateTimeImmutable();
  $endOfDay = $today->setTime(23, 59, 59)->format('Y-m-d H:i:s');

  $institutionVisit = EckEntity::get('Institution_Visit', FALSE)
    ->addSelect('Urban_Planned_Visit.External_Coordinating_PoC', 'Urban_Planned_Visit.Which_Goonj_Processing_Center_do_you_wish_to_visit_', 'Urban_Planned_Visit.What_time_do_you_wish_to_visit_', 'Urban_Planned_Visit.Coordinating_Goonj_POC')
    ->addWhere('Urban_Planned_Visit.External_Coordinating_PoC', 'IS NOT NULL')
    ->addWhere('Urban_Planned_Visit.When_do_you_wish_to_visit_Goonj', '<=', $endOfDay)
    ->addClause('OR',
    ['Urban_Planned_Visit.Reminder_Email_To_Ext_Coord_Poc', 'IS NULL'],
    ['Urban_Planned_Visit.Reminder_Email_To_Ext_Coord_Poc', '=', 0]
    )
    ->execute();

  foreach ($institutionVisit as $visit) {
    try {
      UrbanPlannedVisitService::sendReminderEmailToExtCoordPoc($visit);
    }
    catch (\Exception $e) {
      \Civi::log()->info('Error processing visit', [
        'error' => $e->getMessage(),
        'visit_id' => $visit['id'],
      ]);
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'urban_reminder_email_to_external_coord_cron');
}
