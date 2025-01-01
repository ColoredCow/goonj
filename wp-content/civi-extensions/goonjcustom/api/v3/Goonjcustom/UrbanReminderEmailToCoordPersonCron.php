<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\UrbanPlannedVisitService;

/**
 * Goonjcustom.UrbanReminderEmailToCoordPersonCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_urban_reminder_email_to_coord_person_cron_spec(&$spec) {
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
function civicrm_api3_goonjcustom_urban_reminder_email_to_coord_person_cron($params) {
  $returnValues = [];

  $today = new DateTimeImmutable();
  $endOfDay = $today->setTime(23, 59, 59)->format('Y-m-d H:i:s');

  $institutionVisit = EckEntity::get('Institution_Visit', FALSE)
    ->addSelect('Urban_Planned_Visit.Visit_Guide', 'Urban_Planned_Visit.Coordinating_Goonj_POC', 'Urban_Planned_Visit.What_time_do_you_wish_to_visit_', 'Urban_Planned_Visit.Number_of_people_accompanying_you')
    ->addWhere('Urban_Planned_Visit.Visit_Guide', 'IS NOT NULL')
    ->addWhere('Urban_Planned_Visit.When_do_you_wish_to_visit_Goonj', '<=', $endOfDay)
    ->addClause('OR',
    ['Urban_Planned_Visit.Reminder_Email_To_Coord_Person', 'IS NULL'],
    ['Urban_Planned_Visit.Reminder_Email_To_Coord_Person', '=', 0]
    )
    ->execute();

  foreach ($institutionVisit as $visit) {
    try {
      UrbanPlannedVisitService::sendReminderEmailToCoordPerson($visit);
    }
    catch (\Exception $e) {
      \Civi::log()->info('Error processing visit', [
        'error' => $e->getMessage(),
        'visit_id' => $visit['id'],
      ]);
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'urban_reminder_email_to_coord_person_cron');
}
