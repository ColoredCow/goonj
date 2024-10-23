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
function _civicrm_api3_goonjcustom_collection_camp_outcome_reminder_cron_spec(&$spec) {
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
function civicrm_api3_goonjcustom_collection_camp_outcome_reminder_cron($params) {
  $returnValues = [];
  $now = new DateTimeImmutable();
  $endOfDay = $now->setTime(23, 59, 59)->format('Y-m-d H:i:s');
  $from = HelperService::getDefaultFromEmail();

  // Fetch camps that have completed but the outcome form is not yet filled.
  $collectionCamps = EckEntity::get('Collection_Camp', TRUE)
    ->addSelect(
      'Logistics_Coordination.Camp_to_be_attended_by',
      'Collection_Camp_Intent_Details.End_Date',
      'Camp_Outcome.Last_Reminder_Sent',
      'title',
      'Collection_Camp_Intent_Details.Location_Area_of_camp',
      'Camp_Outcome.Final_Reminder_Sent',
    )
    ->addWhere('Camp_Outcome.Rate_the_camp', 'IS NULL')
    ->addWhere('Logistics_Coordination.Email_Sent', '=', 1)
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('Collection_Camp_Intent_Details.End_Date', '<=', $endOfDay)
    ->addWhere('Collection_Camp_Intent_Details.Camp_Status', '!=', 'aborted')
    ->addWhere('Camp_Outcome.Final_Reminder_Sent', 'IS NULL')
    ->execute();

  foreach ($collectionCamps as $camp) {
    try {
      CollectionCampOutcomeService::processCampReminder($camp, $now, $from);
    }
    catch (\Exception $e) {
      \Civi::log()->error('Error processing camp reminder', [
        'id' => $camp['id'],
        'error' => $e->getMessage(),
      ]);
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'collection_camp_outcome_reminder_cron');
}
