<?php
use CRM_Civirules_ExtensionUtil as E;

/**
 * CiviRulesRuleAction.Create API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @return void
 */
function _civicrm_api3_civi_rules_rule_action_create_spec(&$spec) {
 $spec['rule_id']['api.required'] = 1;
 $spec['action_id']['api.required'] = 1;
 $spec['action_params']['api.required'] = 0;
}

/**
 * CiviRulesRuleAction.Create API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_civi_rules_rule_action_create($params) {
  $returnValues = CRM_Civirules_BAO_CiviRulesRuleAction::writeRecord($params);
  $keyedReturnValues = [$returnValues->id => $returnValues->toArray()];
  return civicrm_api3_create_success($keyedReturnValues, $params, 'CiviRulesRuleAction', 'Create');
}

/**
 * CiviRulesRuleAction.Get API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @throws \CRM_Core_Exception
 * @see civicrm_api3_create_error
 * @see civicrm_api3_create_success
 */
function civicrm_api3_civi_rules_rule_action_get($params) {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * CiviRulesRuleAction.Delete API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @return void
 */
function _civicrm_api3_civi_rules_rule_action_delete_spec(&$spec) {
  $spec['id']['api.required'] = 1;
}

/**
 * CiviRulesRuleAction.Delete API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @throws \CRM_Core_Exception
 * @see civicrm_api3_create_error
 * @see civicrm_api3_create_success
 */
function civicrm_api3_civi_rules_rule_action_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}


