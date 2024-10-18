<?php

/**
 * @file
 */

use Civi\Api4\Individual;
use Civi\Api4\OptionValue;
use Civi\HelperService;
use Civi\InductionService;

/**
 * Custom.VolunteerInductionReminderCron API specification.
 *
 * @param array $spec
 */
function _civicrm_api3_goonjcustom_volunteer_induction_reminder_cron_spec(&$spec) {
  // No specific parameters for this cron job.
}

/**
 * Custom.VolunteerInductionReminderCron API.
 *
 * @param array $params
 *
 * @return array API result descriptor
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_goonjcustom_volunteer_induction_reminder_cron($params) {
  $returnValues = [];
  $today = new DateTimeImmutable();

  // Limit the number of emails per batch.
  $batchSize = 20;
  // Start from the first offset.
  $offset = 0;

  // Get the default "from" email.
  $from = HelperService::getDefaultFromEmail();

  while (TRUE) {
    $activityTypeOptionValue = OptionValue::get(TRUE)
      ->addWhere('option_group_id:name', '=', 'activity_type')
      ->addWhere('name', '=', 'Induction')
      ->execute()->single();

    $activityTypeId = $activityTypeOptionValue['value'];

    $activityStatusOptionValue = OptionValue::get(TRUE)
      ->addWhere('option_group_id:name', '=', 'activity_status')
      ->addWhere('name', '=', 'To be scheduled')
      ->execute()->single();

    $activityStatus = $activityStatusOptionValue['value'];

    // Fetch volunteers who have registered but not scheduled an induction.
    $volunteers = Individual::get(TRUE)
      ->addSelect('created_date', 'display_name', 'email_primary.email', 'Individual_fields.Last_Reminder_Sent', 'address_primary.state_province_id')
      ->addJoin('Activity AS activity', 'LEFT')
      ->addWhere('activity.activity_type_id', '=', $activityTypeId)
      ->addWhere('activity.status_id', '=', $activityStatus)
      ->addWhere('contact_sub_type', '=', 'Volunteer')
      ->addWhere('Individual_fields.Last_Reminder_Sent', 'IS NULL')
      ->addWhere('created_date', '<=', (new DateTime())->format('Y-m-d H:i:s'))
      ->setLimit($batchSize)
      ->setOffset($offset)
      ->execute();

    // If there are no more volunteers to process, break the loop.
    if (empty($volunteers)) {
      break;
    }

    // Process each volunteer in the fetched batch.
    foreach ($volunteers as $volunteer) {
      try {
        InductionService::processInductionReminder($volunteer, $today, $from);
      }
      catch (\Exception $e) {
        \Civi::log()->error('Error processing volunteer induction reminder', [
          'id' => $volunteer['id'],
          'error' => $e->getMessage(),
        ]);
      }
    }

    // Increment the offset for the next batch.
    $offset += $batchSize;
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'volunteer_induction_reminder_cron');
}
