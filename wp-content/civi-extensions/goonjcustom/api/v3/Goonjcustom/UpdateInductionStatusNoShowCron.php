<?php

use Civi\Api4\Activity;
use Civi\Api4\MessageTemplate;
use Civi\InductionService;

/**
 * Goonjcustom.Induction Status Update to No Show API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_update_induction_status_no_show_cron_spec(&$spec) {
    // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.update_induction_status_no_show_cron API implementation.
 *
 * This function checks for unscheduled induction activities older than 30 days 
 * and update induction status to No Show
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
function civicrm_api3_goonjcustom_update_induction_status_no_show_cron($params) {
    $returnValues = [];
    try {
        InductionService::updateInductionStatusNoShow();
    } catch (\Exception $e) {
        \Civi::log()->error('Error in follow-up cron: ' . $e->getMessage());
        return civicrm_api3_create_error($e->getMessage());
    }

    return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'update_induction_status_no_show_cron');
}
