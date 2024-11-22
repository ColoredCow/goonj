<?php

use Civi\Api4\Activity;
use Civi\Api4\MessageTemplate;
use Civi\InductionService;

/**
 * Goonjcustom.InductionRemainderEmail to Volunteer on Induction Day API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_induction_remainder_email_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.induction_remainder_email_cron API implementation.
 *
 * This function checks for scheduled induction for current day
 * and send emails to the respective contacts 
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
function civicrm_api3_goonjcustom_induction_remainder_email_cron($params) {
    $returnValues = [];
    try {
        InductionService::sendRemainderEmails();
    } catch (Exception $e) {
        \Civi::log()->error('Error in Remainder Email cron: ' . $e->getMessage());
        return civicrm_api3_create_error($e->getMessage());
    }

    return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'induction_remainder_email_cron');
}