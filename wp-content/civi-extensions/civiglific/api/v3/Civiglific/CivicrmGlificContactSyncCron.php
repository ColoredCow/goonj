<?php

/**
 * @file
 */

/**
 *
 */
function _civicrm_api3_civiglific_civicrm_glific_contact_sync_cron_spec(&$spec) {
  // There are no parameters for the Civiglific cron.
}

/**
 *
 */
function civicrm_api3_civiglific_civicrm_glific_contact_sync_cron($params) {
  $returnValues = [];

  return civicrm_api3_create_success($returnValues, $params, 'Civiglific', 'civicrm_glific_contact_sync_cron');
}
