<?php

/**
 * @file
 */

use Civi\Api4\Job;

/**
 * Goonjcustom.UpdateScheduledTimeCron API specification (optional)
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
 * Goonjcustom.UpdateScheduledTimeCron API.
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

    error_log("todayDateTimeForFeedback: " . print_r($todayDateTimeForFeedback, TRUE));
		error_log( 'todayDateTimeForLogistics: ' . print_r( $todayDateTimeForLogistics, true ) );


    // Update scheduled run time for logistics and volunteer feedback.
    updateJobScheduledTime('collection_camp_cron', $todayDateTimeForLogistics);
    updateJobScheduledTime('volunteer_feedback_collection_camp_cron', $todayDateTimeForFeedback);

  }
  catch (Exception $e) {
    \Civi::log()->error('Error in Goonjcustom.UpdateScheduledTimeCron job: {error}', [
      'error' => $e->getMessage(),
      'trace' => $e->getTraceAsString(),
    ]);
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'update_scheduled_time_cron');
}

/**
 *
 */
function updateJobScheduledTime($apiAction, $scheduledRunDate) {
  error_log( 'apiAction1: ' . print_r( $apiAction, true ) );
  error_log( 'scheduledRunDate1: ' . print_r( $scheduledRunDate, true ) );

  // Fetch the scheduled run date.
  $job = Job::get(TRUE)
    ->addSelect('scheduled_run_date')
    ->addWhere('api_action', '=', $apiAction)
    ->execute()->single();
		error_log( 'job: ' . print_r( $job, true ) );


  $scheduledRunDateFromDb = $job['scheduled_run_date'];
  error_log( 'scheduledRunDateFromDb: ' . print_r( $scheduledRunDateFromDb, true ) );


  // Update the scheduled run time if it differs from the current value.
  if ($scheduledRunDateFromDb !== $scheduledRunDate) {
  error_log( 'scheduledRunDateFromDb: ' . print_r( $scheduledRunDateFromDb, true ) );
  error_log( 'scheduledRunDate: ' . print_r( $scheduledRunDate, true ) );

    Job::update(TRUE)
      ->addValue('scheduled_run_date', $scheduledRunDate)
      ->addWhere('api_action', '=', $apiAction)
      ->execute();
  }

  error_log( 'scheduledRunDatescheduledRunDate: ' . print_r( $scheduledRunDate, true ) );
  error_log( 'apiAction: ' . print_r( $apiAction, true ) );


}
