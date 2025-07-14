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
    // Get current hour.
    $currentTime = (int) $currentDate->format('H');

    // Set time to 9:00 AM (next day if past 9 AM).
    $nineAmDateTime = clone $currentDate;
    $nineAmDateTime->setTime(9, 0, 0);
    if ($currentTime >= 9) {
      $nineAmDateTime->modify('+1 day');
    }
    $visitTime = $nineAmDateTime->format('Y-m-d H:i:s');

    // Set time to 10:00 AM (next day if past 10 AM).
    $tenAmDateTime = clone $currentDate;
    $tenAmDateTime->setTime(10, 0, 0);
    if ($currentTime >= 10) {
      $tenAmDateTime->modify('+1 day');
    }
    $logisticsTime = $tenAmDateTime->format('Y-m-d H:i:s');

    // Set time to 1:00 AM (next day if past 1 AM).
    $oneAmDateTime = clone $currentDate;
    $oneAmDateTime->setTime(1, 0, 0);
    if ($currentTime >= 1) {
      $oneAmDateTime->modify('+1 day');
    }
    $settlementTime = $oneAmDateTime->format('Y-m-d H:i:s');

    // Set time to 2:00 PM (next day if past 2 PM).
    $twoPmDateTime = clone $currentDate;
    $twoPmDateTime->setTime(14, 0, 0);
    if ($currentTime >= 14) {
      $twoPmDateTime->modify('+1 day');
    }
    $feedbackTime = $twoPmDateTime->format('Y-m-d H:i:s');

    // Update scheduled run time for logistics and volunteer feedback.
    updateJobScheduledTime('institution_collection_camp_cron', $logisticsTime);
    updateJobScheduledTime('collection_camp_cron', $logisticsTime);
    updateJobScheduledTime('volunteer_feedback_collection_camp_cron', $feedbackTime);

    // Update scheduled run time for urban reminder visit.
    updateJobScheduledTime('urban_reminder_email_to_coord_person_cron', $visitTime);
    updateJobScheduledTime('urban_reminder_email_to_external_coord_cron', $visitTime);

    // Update scheduled run time for urban feedback form.
    updateJobScheduledTime('urban_feedback_cron', $logisticsTime);

    // Update scheduled run time for razorpay settlement.
    updateJobScheduledTime('CivicrmRazorpayFetchSettlementDateCron', $settlementTime);

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
  // Fetch the scheduled run date.
  $job = Job::get(TRUE)
    ->addSelect('scheduled_run_date')
    ->addWhere('api_action', '=', $apiAction)
    ->execute()->single();

  $scheduledRunDateFromDb = $job['scheduled_run_date'];

  // Update the scheduled run time if it differs from the current value.
  if ($scheduledRunDateFromDb !== $scheduledRunDate) {
    Job::update(TRUE)
      ->addValue('scheduled_run_date', $scheduledRunDate)
      ->addWhere('api_action', '=', $apiAction)
      ->execute();
  }
}
