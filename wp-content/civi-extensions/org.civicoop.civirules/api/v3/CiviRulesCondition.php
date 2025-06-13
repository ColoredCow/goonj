<?php

/**
 * CiviRulesCondition.Create API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @return void
 */
function _civicrm_api3_civi_rules_condition_create_spec(&$spec) {
  $spec['label']['api_required'] = 0;
  $spec['name']['api_required'] = 0;
  $spec['id']['api_required'] = 0;
  $spec['class_name']['api_required'] = 0;
}

/**
 * CiviRulesCondition.Create API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 */
function civicrm_api3_civi_rules_condition_create($params) {
  if (!isset($params['id']) && empty($params['label'])) {
    return civicrm_api3_create_error('Label can not be empty when adding a new CiviRule Condition');
  }
  if (empty($params['class_name']) && !isset($params['id'])) {
    return civicrm_api3_create_error('Class_name can not be empty');
  }
  /*
   * set created or modified date and user_id
   */
  $session = CRM_Core_Session::singleton();
  $userId = $session->get('userID');
  if (isset($params['id'])) {
    $params['modified_date'] = date('Ymd');
    $params['modified_user_id'] = $userId;
  }
  else {
    $params['created_date'] = date('Ymd');
    $params['created_user_id'] = $userId;
  }
  $returnValues = CRM_Civirules_BAO_CiviRulesCondition::writeRecord($params);
  return civicrm_api3_create_success($returnValues->toArray(), $params, 'CiviRulesCondition', 'Create');
}

/**
 * CiviRulesCondition.Get API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_civi_rules_condition_get($params) {
  return _civicrm_api3_basic_get('CRM_Civirules_BAO_CiviRulesCondition', $params);
}

/**
 * CiviRulesCondition.Delete API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @return void
 */
function _civicrm_api3_civi_rules_condition_delete_spec(&$spec) {
  $spec['id']['api_required'] = 0;
}

/**
 * CiviRulesCondition.Delete API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 */
function civicrm_api3_civi_rules_condition_delete($params) {
  return _civicrm_api3_basic_delete('CRM_Civirules_BAO_CiviRulesCondition', $params);
}

/**
 * CiviRulesCondition.Cleanup API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @throws \CRM_Core_Exception
 * @see civicrm_api3_create_error
 * @see civicrm_api3_create_success
 */
function civicrm_api3_civi_rules_condition_cleanup($params) {
  // Get all the civirule conditions
  $civirulesconditions = civicrm_api3('CiviRulesCondition', 'get', [
    'options' => ['limit' => 0, 'sort' => 'id ASC'],
  ]);
  $listOfConditionsByName = [];
  foreach ($civirulesconditions['values'] as $conditionID => $conditionDetail) {
    if (!isset($listOfConditionsByName[$conditionDetail['name']])) {
      $listOfConditionsByName[$conditionDetail['name']] = $conditionDetail;
    }
    else {
      $listOfConditionsByName[$conditionDetail['name']]['duplicateIDs'][] = $conditionID;
    }
  }
  $listToMerge = [];
  foreach ($listOfConditionsByName as $name => $detail) {
    if (!isset($detail['duplicateIDs'])) {
      continue;
    }
    $listToMerge[$detail['id']] = $detail['duplicateIDs'];
  }
  foreach ($listToMerge as $originalID => $duplicateIDs) {
    if (!$params['dry_run']) {
      foreach ($duplicateIDs as $duplicateID) {
        $query = 'UPDATE civirule_rule_condition SET condition_id = %1 WHERE condition_id = %2';
        CRM_Core_DAO::executeQuery($query, [1 => [$originalID, 'Positive'], 2 => [$duplicateID, 'Positive']]);
        $deleteQuery = 'DELETE FROM civirule_condition WHERE id = %1';
        CRM_Core_DAO::executeQuery($deleteQuery, [1 => [$duplicateID, 'Positive']]);
      }
    }
  }

  if ($params['dry_run']) {
    \Civi::log()->debug('CiviRulesCondition.Cleanup dry_run: would have merged: ' . print_r($listToMerge, TRUE));
  }

  return civicrm_api3_create_success($listToMerge, $params);
}

/**
 * CiviRulesCondition.Cleanup API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @return void
 */
function _civicrm_api3_civi_rules_condition_cleanup_spec(&$spec) {
  $spec['dry_run'] = [
    'title' => 'Dry run (do not actually make any changes)',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.default' => TRUE
  ];
}
