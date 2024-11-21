<?php

use Civi\Api4\Activity;
use Civi\Api4\MessageTemplate;
use Civi\InductionService;

/**
 * Goonjcustom.InductionSlotBookingRescheduleMail API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_induction_reschedule_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.induction_slot_booking_reschedule_cron API implementation.
 *
 * This function checks for not visited induction activities older than 1 days 
 * and sends reschedule emails to the respective contacts if they haven't already 
 * received one.
 *
 * @param array $params
 *   Parameters passed to the API call.
 *
 * @return array API result descriptor
 *
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_goonjcustom_induction_reschedule_cron($params) {
    $returnValues = [];
    try {
        InductionService::sendInductionRescheduleEmail();
    } catch (Exception $e) {
        \Civi::log()->error('Error in induction reschedule cron: ' . $e->getMessage());
        return civicrm_api3_create_error($e->getMessage());
    }

    return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'induction_reschedule_cron');
}
