<?php

/**
 * @file
 */

/**
 * Goonjcustom.CollectionCampCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_update_scheduled_cron_spec(&$spec) {
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
function civicrm_api3_goonjcustom_update_scheduled_cron($params) {
  $returnValues = [];
  try {
    
  }
  catch (Exception $e) {
    \Civi::log()->info("Error is there" . $e->getMessage());
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'update_scheduled_cron');
}
