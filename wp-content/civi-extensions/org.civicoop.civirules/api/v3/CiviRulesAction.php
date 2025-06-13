<?php

/**
 * CiviRulesAction.Create API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 */
function _civicrm_api3_civi_rules_action_create_spec(&$spec) {
  $spec['label']['api_required'] = 0;
  $spec['name']['api_required'] = 0;
  $spec['class_name']['api_required'] = 0;
}

/**
 * CiviRulesAction.Create API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 */
function civicrm_api3_civi_rules_action_create($params) {
  if (!isset($params['id']) && empty($params['label'])) {
    return civicrm_api3_create_error('Label can not be empty when adding a new CiviRule Action');
  }
  if (empty($params['class_name']) && !isset($params['id'])) {
    return civicrm_api3_create_error('Class_name can not be empty');
  }
  /*
   * set created or modified date and user_id
   */
  $userId = CRM_Core_Session::getLoggedInContactID();
  if (isset($params['id'])) {
    $params['modified_date'] = date('Ymd');
    $params['modified_user_id'] = $userId;
  } else {
    $params['created_date'] = date('Ymd');
    $params['created_user_id'] = $userId;
  }

  return _civicrm_api3_basic_create('CRM_Civirules_BAO_CiviRulesAction', $params);
}

/**
 * CiviRulesAction.Get API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_civi_rules_action_get($params) {
  return _civicrm_api3_basic_get('CRM_Civirules_BAO_CiviRulesAction', $params);
}

/**
 * CiviRulesAction.Delete API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 */
function _civicrm_api3_civi_rules_action_delete_spec(&$spec) {
  $spec['id']['api_required'] = 0;
}

/**
 * CiviRulesAction.Delete API
 *
 * @param array $params
 *
 * @return array API result descriptor
 */
function civicrm_api3_civi_rules_action_delete($params) {
  return _civicrm_api3_basic_delete('CRM_Civirules_BAO_CiviRulesAction', $params);
}

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
function civicrm_api3_civi_rules_action_process($params) {
  $returnValues = CRM_Civirules_Engine::processDelayedActions(60);
  return civicrm_api3_create_success($returnValues, $params, 'CiviRulesAction', 'Process');
}

/**
 * CiviRulesAction.Cleanup API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 */
function _civicrm_api3_civi_rules_action_cleanup_spec(&$spec) {
  $spec['dry_run'] = [
    'title' => 'Dry run (do not actually make any changes)',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.default' => TRUE
  ];
}

/**
 * CiviRulesAction.Cleanup API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_civi_rules_action_cleanup($params) {
  // Get all the civirule actions
  $civirulesactions = civicrm_api3('CiviRulesAction', 'get', [
    'options' => ['limit' => 0, 'sort' => 'id ASC'],
  ]);
  $listOfActionsByName = [];
  foreach ($civirulesactions['values'] as $actionID => $actionDetail) {
    if (!isset($listOfActionsByName[$actionDetail['name']])) {
      $listOfActionsByName[$actionDetail['name']] = $actionDetail;
    }
    else {
      $listOfActionsByName[$actionDetail['name']]['duplicateIDs'][] = $actionID;
    }
  }
  $listToMerge = [];
  foreach ($listOfActionsByName as $name => $detail) {
    if (!isset($detail['duplicateIDs'])) {
      continue;
    }
    $listToMerge[$detail['id']] = $detail['duplicateIDs'];
  }
  foreach ($listToMerge as $originalID => $duplicateIDs) {
    if (!$params['dry_run']) {
      foreach ($duplicateIDs as $duplicateID) {
        $query = 'UPDATE civirule_rule_action SET action_id = %1 WHERE action_id = %2';
        CRM_Core_DAO::executeQuery($query, [1 => [$originalID, 'Positive'], 2 => [$duplicateID, 'Positive']]);
        $deleteQuery = 'DELETE FROM civirule_action WHERE id = %1';
        CRM_Core_DAO::executeQuery($deleteQuery, [1 => [$duplicateID, 'Positive']]);
      }
    }
  }

  if ($params['dry_run']) {
    \Civi::log()->debug('CiviRulesAction.cleanup dry_run: would have merged: ' . print_r($listToMerge, TRUE));
  }

  return civicrm_api3_create_success($listToMerge, $params);
}
