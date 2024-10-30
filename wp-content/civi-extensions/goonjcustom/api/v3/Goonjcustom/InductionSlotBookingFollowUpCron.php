<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\CollectionCampOutcomeService;
use Civi\HelperService;

/**
 * Goonjcustom.CollectionCampOutcomeReminderCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_induction_slot_booking_follow_up_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.CollectionCampOutcomeReminderCron API.
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
function civicrm_api3_goonjcustom_induction_slot_booking_follow_up_cron($params) {
  $returnValues = [];
  \Civi::log()->info('check');
  $unscheduledInductionActivities = \Civi\Api4\Activity::get(FALSE)
    ->addSelect('source_contact_id')
    ->addWhere('activity_type_id:name', '=', 'Induction')
    ->addWhere('status_id:name', '=', 'To be scheduled')
    ->setLimit(25)
    ->execute();

  foreach($unscheduledInductionActivities as $unscheduledInductionActivitie){
    $template = \Civi\Api4\MessageTemplate::get(FALSE)
      ->addWhere('msg_title', 'LIKE', 'Induction_slot_booking_follow_up_email%') // Adding '%' for right wildcard
      ->setLimit(1)
      ->execute()
      ->single();
    $emailParams = [
      'contact_id' => $unscheduledInductionActivitie['source_contact_id'],
      'template_id' => $template['id'],
    ];
    $emailResult = civicrm_api3('Email', 'send', $emailParams);
    \Civi::log()->info('Email sent', ['result' => $emailResult]);
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'induction_slot_booking_follow_up_cron');
}
