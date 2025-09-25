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

  $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect('Camp_Outcome.Rate_the_camp', 'created_date', 'Camp_Outcome.Camp_Status_Completion_Date', 'Collection_Camp_Core_Details.Contact_Id')
    ->addWhere('Camp_Outcome.Camp_Status_Completion_Date', 'IS NOT NULL')
    ->addWhere('Camp_Outcome.Rate_the_camp', 'IS NOT NULL')
    ->addClause('OR',
      ['Camp_Outcome.Five_Day_Email_Sent', 'IS NULL'],
      ['Camp_Outcome.Five_Day_Email_Sent', '=', 0]
    )
    ->setLimit(25)
    ->execute();
    error_log('count of camps: ' . print_r(count($collectionCamps), TRUE));

  foreach ($collectionCamps as $collectionCamp) {
    try {
      $campCompletionDate = new DateTimeImmutable($collectionCamp['Camp_Outcome.Camp_Status_Completion_Date']);
      $today = new DateTimeImmutable();

      // // Calculate difference in days.
      // $diff = $today->diff($campCompletionDate)->days;
      // // Only send if 5 or more days have passed.
      // if ($diff < 5) {
      //   // Skip this camp.
      //   continue;
      // }

      // Testing Purpose
      // Calculate difference in minutes instead of days.
      // $diffInMinutes = ($today->getTimestamp() - $campCompletionDate->getTimestamp()) / 60;
      // error_log('diffInMinutes:' . print_r($diffInMinutes, TRUE));

      // // For testing: Only send if 1 or more minutes have passed.
      // if ($diffInMinutes < 1) {
      // error_log('inside the if condition');

      //   // Skip this camp.
      //   continue;
      // }

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
