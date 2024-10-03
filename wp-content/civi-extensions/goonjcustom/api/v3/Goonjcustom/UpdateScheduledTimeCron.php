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
    $formattedToday = $currentDate->format('Y-m-d H:i:s');
    $currentDate->setTime(10, 0, 0);
    $todayDateTime = $currentDate->format('Y-m-d H:i:s');

    // Fetch the scheduled run date.
    $jobs = Job::get(TRUE)
      ->addSelect('scheduled_run_date')
      ->addWhere('api_action', '=', 'collection_camp_cron')
      ->execute()->single();

    $scheduledRunDate = $jobs['scheduled_run_date'];

    if ($scheduledRunDate == $todayDateTime) {
      error_log("Hello");
      return;
    }

    if ($formattedToday > $scheduledRunDate) {
      $nextScheduledDate = new DateTime();
      // Set time to 10:00 AM.
      $nextScheduledDate->setTime(10, 0, 0);

      $results = Job::update(TRUE)
        ->addValue('scheduled_run_date', $nextScheduledDate->format('Y-m-d H:i:s'))
        ->addWhere('api_action', '=', 'collection_camp_cron')
        ->execute();
    }

  }
  catch (Exception $e) {
    \Civi::log()->info("Error is there: " . $e->getMessage());
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'update_scheduled_time_cron');
}
