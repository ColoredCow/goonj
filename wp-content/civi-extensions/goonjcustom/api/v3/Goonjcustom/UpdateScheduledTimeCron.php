<?php

/**
 * @file
 */

use Civi\Api4\Job;

/**
 * Goonjcustom.CollectionCampCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_update_scheduled_time_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.CollectionCampCron API.
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
function civicrm_api3_goonjcustom_update_scheduled_time_cron($params) {
  $returnValues = [];
  try {
    $currentDate = new DateTime();
    // Set time to 10:00 AM.
    $currentDate->setTime(10, 0, 0);
    $todayDateTimeForLogistics = $currentDate->format('Y-m-d H:i:s');

    $twoPmDateTime = clone $currentDate;
    // Set time to 2:00 PM.
    $twoPmDateTime->setTime(14, 0, 0);
    $todayDateTimeForFeedback = $twoPmDateTime->format('Y-m-d H:i:s');

    // Fetch the scheduled run date.
    $logisticJob = Job::get(TRUE)
      ->addSelect('scheduled_run_date')
      ->addWhere('api_action', '=', 'collection_camp_cron')
      ->execute()->single();

    $logisticScheduledRunDate = $logisticJob['scheduled_run_date'];

    $feedbackJob = Job::get(TRUE)
      ->addSelect('scheduled_run_date')
      ->addWhere('api_action', '=', 'volunteer_feedback_collection_camp_cron')
      ->execute()->single();

    $volunteerScheduledRunDate = $feedbackJob['scheduled_run_date'];

    // Update the scheduled run time for logistics mail.
    if ($logisticScheduledRunDate != $todayDateTimeForLogistics) {
      $results = Job::update(TRUE)
        ->addValue('scheduled_run_date', $todayDateTimeForLogistics)
        ->addWhere('api_action', '=', 'collection_camp_cron')
        ->execute();
    }

    // Update the scheduled run time for volunteer feedback mail.
    if ($volunteerScheduledRunDate != $todayDateTimeForFeedback) {
      $results = Job::update(TRUE)
        ->addValue('scheduled_run_date', $todayDateTimeForFeedback)
        ->addWhere('api_action', '=', 'volunteer_feedback_collection_camp_cron')
        ->execute();
    }

  }
  catch (Exception $e) {
    \Civi::log()->error('Error in Goonjcustom.UpdateScheduledTimeCron job: {error}', [
      'error' => $e->getMessage(),
      'trace' => $e->getTraceAsString(),
    ]);
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'update_scheduled_time_cron');
}
