<?php

/**
 * CiviRulesTrigger.Create API specification
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @return void
 */
function _civicrm_api3_civi_rules_trigger_create_spec(&$spec) {
  $spec['label']['api_required'] = 0;
  $spec['name']['api_required'] = 0;
  $spec['object_name']['api_required'] = 0;
  $spec['op']['api_required'] = 0;
  $spec['class_name']['api_required'] = 0;
}

/**
 * CiviRulesTrigger.Create API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 */
function civicrm_api3_civi_rules_trigger_create($params) {
  $errorMessage = _validateParams($params);
  if (!empty($errorMessage)) {
    return civicrm_api3_create_error($errorMessage);
  }
  $session = CRM_Core_Session::singleton();
  $userId = $session->get('userID');
  if (isset($params['id'])) {
    $params['modified_date'] = date('Ymd');
    $params['modified_user_id'] = $userId;
  } else {
    $params['created_date'] = date('Ymd');
    $params['created_user_id'] = $userId;
  }
  $returnValues = CRM_Civirules_BAO_CiviRulesTrigger::writeRecord($params);
  return civicrm_api3_create_success((array) $returnValues, $params, 'CiviRulesTrigger', 'Create');
}

/**
 * Function to validate parameters
 *
 * @param array $params
 *
 * @return string $errorMessage
 */
function _validateParams($params) {
  $errorMessage = '';
  // If this create is actually an update, load in the existing values.
  if (isset($params['id'])) {
    $result = civicrm_api3('CiviRuleTrigger', 'get', array(
      'sequential' => 1,
      'id' => $params['id'],
    ));

    if ($result['count']) {
      $params = array_merge($result['values'][0], $params);
    }
  }
  if (!isset($params['id']) && empty($params['label'])) {
    return ts('Label can not be empty when adding a new CiviRule Trigger');
  }
  if (_checkClassNameObjectNameOperation($params) == FALSE) {
    return ts('Either class_name or a combination of object_name and op is mandatory');
  }
  if (isset($params['cron']) && $params['cron'] == 1) {
    $params['object_name'] = null;
    $params['op'] = null;
    if (!isset($params['class_name']) || empty($params['class_name'])) {
      return ts('For a cron type trigger the class_name is mandatory');
    }
  }
  if (isset($params['object_name']) && !empty($params['object_name'])) {
    $extensionConfig = CRM_Civirules_Config::singleton();
    if (!in_array($params['object_name'], $extensionConfig->getValidTriggerObjectNames())) {
      return ts('ObjectName passed in parameters ('.$params['object_name']
        .')is not a valid object for a CiviRule Trigger');
    }
  }
  if (isset($params['op']) && !empty($params['op'])) {
    $extensionConfig = CRM_Civirules_Config::singleton();
    if (!in_array($params['op'], $extensionConfig->getValidTriggerOperations())) {
      return ts('Operation passed in parameters ('.$params['op']
        .')is not a valid operation for a CiviRule Trigger');
    }
  }
  if (!isset($params['id']) && CRM_Civirules_BAO_CiviRulesTrigger::triggerExists($params) == TRUE) {
    return ts('There is already a trigger for this class_name or combination of object_name and op');
  }

  return $errorMessage;
}

/**
 * Function to check if className or Op/ObjectName are passed
 *
 * @param array $params
 *
 * @return bool
 */
function _checkClassNameObjectNameOperation($params) {
  if (isset($params['class_name']) && !empty($params['class_name'])) {
    if (!isset($params['object_name']) && !isset($params['op'])) {
      return TRUE;
    } else {
      if (empty($params['object_name']) && empty($params['op'])) {
        return TRUE;
      }
    }
  }
  if (isset($params['object_name']) && isset($params['op']) && !empty($params['object_name']) && !empty($params['op'])) {
    if (!isset($params['class_name']) || empty($params['class_name'])) {
      return TRUE;
    }
  }
  return FALSE;
}

/**
 * CiviRulesTrigger.Get API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_civi_rules_trigger_get($params) {
  return _civicrm_api3_basic_get('CRM_Civirules_BAO_CiviRulesTrigger', $params);
}

/**
 * CiviRulesTrigger.Delete API specification
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @return void
 */
function _civicrm_api3_civi_rules_trigger_delete_spec(&$spec) {
  $spec['id']['api_required'] = 1;
}

/**
 * CiviRulesTrigger.Delete API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_civi_rules_trigger_delete($params) {
  return _civicrm_api3_basic_delete('CRM_Civirules_BAO_CiviRulesTrigger', $params);
}

/**
 * CiviRulesTrigger.Cleanup API specification
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @return void
 */
function _civicrm_api3_civi_rules_trigger_cleanup_spec(&$spec) {
  $spec['dry_run'] = [
    'title' => 'Dry run (do not actually make any changes)',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.default' => TRUE
  ];
}

/**
 * CiviRulesTrigger.Cleanup API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_civi_rules_trigger_cleanup($params) {
  // Get all the civirule triggers
  $civiruletriggers = civicrm_api3('CiviRuleTrigger', 'get', [
    'options' => ['limit' => 0, 'sort' => 'id ASC'],
  ]);
  $listOfTriggersByName = [];
  foreach ($civiruletriggers['values'] as $triggerID => $triggerDetail) {
    if (!isset($listOfTriggersByName[$triggerDetail['name']])) {
      $listOfTriggersByName[$triggerDetail['name']] = $triggerDetail;
    }
    else {
      $listOfTriggersByName[$triggerDetail['name']]['duplicateIDs'][] = $triggerID;
    }
  }
  $listToMerge = [];
  foreach ($listOfTriggersByName as $name => $detail) {
    if (!isset($detail['duplicateIDs'])) {
      continue;
    }
    $listToMerge[$detail['id']] = $detail['duplicateIDs'];
  }
  foreach ($listToMerge as $originalID => $duplicateIDs) {
    if (!$params['dry_run']) {
      foreach ($duplicateIDs as $duplicateID) {
        $query = 'UPDATE civirule_rule SET trigger_id = %1 WHERE trigger_id = %2';
        CRM_Core_DAO::executeQuery($query, [1 => [$originalID, 'Positive'], 2 => [$duplicateID, 'Positive']]);
        $deleteQuery = 'DELETE FROM civirule_trigger WHERE id = %1';
        CRM_Core_DAO::executeQuery($deleteQuery, [1 => [$duplicateID, 'Positive']]);
      }
    }
  }

  if ($params['dry_run']) {
    \Civi::log()->debug('CiviRuletrigger.cleanup dry_run: would have merged: ' . print_r($listToMerge, TRUE));
  }

  return civicrm_api3_create_success($listToMerge, $params);
}
