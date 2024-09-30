<?php

/**
 * CiviRulesAction.process API
 *
 * Process delayed actions
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_goonjcustom_action_process_queue($params) {
  $returnValues = CRM_Goonjcustom_Engine::processQueue(60);
  return civicrm_api3_create_success($returnValues, $params, 'GoonjcustomQueue', 'Process');
}
