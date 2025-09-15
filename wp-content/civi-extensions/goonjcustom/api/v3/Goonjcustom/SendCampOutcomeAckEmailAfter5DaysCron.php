<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\CollectionCampService;

/**
 * Goonjcustom.SendCampOutcomeAckEmailAfter5DaysCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_send_camp_outcome_ack_email_after_5_days_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.SendCampOutcomeAckEmailAfter5DaysCron API.
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
function civicrm_api3_goonjcustom_send_camp_outcome_ack_email_after_5_days_cron($params) {
  $returnValues = [];

  $today = new DateTimeImmutable();
  $startOfDay = $today->setTime(0, 0, 0)->format('Y-m-d H:i:s');
  $endOfDay = $today->setTime(23, 59, 59)->format('Y-m-d H:i:s');

  $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect('Camp_Outcome.Rate_the_camp', 'created_date', 'Camp_Outcome.Camp_Status_Completion_Date', 'Collection_Camp_Core_Details.Contact_Id')
    ->addWhere('Camp_Outcome.Camp_Status_Completion_Date', 'IS NOT NULL')
    ->addWhere('Camp_Outcome.Rate_the_camp', 'IS NOT NULL')
    ->setLimit(25)
    ->execute();

  foreach ($collectionCamps as $collectionCamp) {
    try {
      $campCompletionDate = new DateTimeImmutable($collectionCamp['Camp_Outcome.Camp_Status_Completion_Date']);
      $today = new DateTimeImmutable();

      // Calculate difference in days.
      $diff = $today->diff($campCompletionDate)->days;
      error_log('Difference in days: ' . $diff);

      // Only send if 5 or more days have passed.
      if ($diff < 5) {
        // Skip this camp.
        continue;
      }
      error_log('Camp completion date: ' . $campCompletionDate->format('Y-m-d'));
      error_log('Today: ' . $today->format('Y-m-d'));
      error_log('Days passed: ' . $diff);

      CollectionCampService::sendCampOutcomeAckEmailAfter5Days($collectionCamp);
    }
    catch (\Exception $e) {
      \Civi::log()->info('Error while sending mail after 5 days', [
        'id' => $collectionCamp['id'],
        'error' => $e->getMessage(),
      ]);
    }
  }
  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'send_camp_outcome_ack_email_after_5_days_cron');
}
