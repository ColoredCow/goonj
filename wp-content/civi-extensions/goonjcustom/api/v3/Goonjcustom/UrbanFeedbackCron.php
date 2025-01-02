<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\UrbanPlannedVisitService;

/**
 * Goonjcustom.UrbanFeedbackCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_urban_feedback_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.UrbanFeedback API.
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
function civicrm_api3_goonjcustom_urban_feedback_cron($params) {
  $returnValues = [];

  $today = new DateTimeImmutable();
  $nextDay = $today->modify('+1 day')->setTime(0, 0, 0)->format('Y-m-d H:i:s');

  $institutionVisit = EckEntity::get('Institution_Visit', FALSE)
    ->addSelect('Urban_Planned_Visit.Coordinating_Goonj_POC', 'Urban_Planned_Visit.External_Coordinating_PoC')
    ->addWhere('Urban_Planned_Visit.External_Coordinating_PoC', 'IS NOT NULL')
    ->addWhere('Urban_Planned_Visit.When_do_you_wish_to_visit_Goonj', '<', $nextDay)
    ->addClause('OR',
    ['Visit_Feedback.Feedback_Email_Sent', 'IS NULL'],
    ['Visit_Feedback.Feedback_Email_Sent', '=', 0]
    )
    ->execute();

  foreach ($institutionVisit as $visit) {
    try {
      UrbanPlannedVisitService::sendFeedbackEmailToExtCoordPoc($visit);
    }
    catch (\Exception $e) {
      \Civi::log()->info('Error processing visit', [
        'error' => $e->getMessage(),
        'visit_id' => $visit['id'],
      ]);
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'urban_feedback_cron');
}
