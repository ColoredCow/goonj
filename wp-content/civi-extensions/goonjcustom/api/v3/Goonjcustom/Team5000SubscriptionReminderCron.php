<?php

/**
 * @file
 */

use Civi\Team5000SubscriptionReminderService;
use Civi\HelperService;

/**
 * Goonjcustom.Team5000SubscriptionReminderCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_team5000_subscription_reminder_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.Team5000SubscriptionReminderCron API.
 *
 * Sends subscription expiry reminder emails to Team 5000 donors
 * at 7 days, 3 days, and 1 day before their subscription ends.
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
function civicrm_api3_goonjcustom_team5000_subscription_reminder_cron($params) {
  $returnValues = [];
  $now = new DateTimeImmutable();
  $from = HelperService::getDefaultFromEmail();

  try {
    Team5000SubscriptionReminderService::processReminders($now, $from);
  }
  catch (\Exception $e) {
    \Civi::log()->error('Error in Team 5000 Subscription Reminder Cron', [
      'error' => $e->getMessage(),
    ]);
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'team5000_subscription_reminder_cron');
}
