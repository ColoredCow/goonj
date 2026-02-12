<?php

/**
 * @file
 */

/**
 * CiviRulesAction.process API.
 *
 * Process delayed actions.
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
function _civicrm_api3_goonjcustom_action_process_queue_spec(&$spec) {
  $spec['max_seconds'] = [
    'title' => 'Max Seconds',
    'description' => 'Maximum number of seconds to process queue items.',
    'type' => CRM_Utils_Type::T_INT,
    'api.default' => 60,
  ];
}

function civicrm_api3_goonjcustom_action_process_queue($params) {
  $maxSeconds = isset($params['max_seconds']) ? (int) $params['max_seconds'] : 60;
  $returnValues = CRM_Goonjcustom_Engine::processQueue($maxSeconds);
  return civicrm_api3_create_success($returnValues, $params, 'GoonjcustomQueue', 'Process');
}
