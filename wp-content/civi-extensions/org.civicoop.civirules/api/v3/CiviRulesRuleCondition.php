<?php
use CRM_Civirules_ExtensionUtil as E;

/**
 * CiviRulesRuleCondition.Create API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @return void
 */
function _civicrm_api3_civi_rules_rule_condition_create_spec(&$spec) {
  $spec['rule_id']['api.required'] = 1;
  $spec['condition_id']['api.required'] =1;
  $spec['condition_params']['api.required'] = 0;
}

/**
 * CiviRulesRuleCondition.Create API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_civi_rules_rule_condition_create($params) {
  $returnValues = CRM_Civirules_BAO_CivirulesRuleCondition::writeRecord($params);
  $keyedReturnValues = [$returnValues->id => $returnValues->toArray()];
  return civicrm_api3_create_success($keyedReturnValues, $params,'CiviRulesRuleCondition', 'create');
}

/**
 * CiviRulesRuleCondition.Get API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @throws \CRM_Core_Exception
 * @see civicrm_api3_create_error
 * @see civicrm_api3_create_success
 */
function civicrm_api3_civi_rules_rule_condition_get($params) {
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
function _civicrm_api3_civi_rules_rule_condition_delete_spec(&$spec) {
  $spec['id']['api.required'] = 1;
}

/**
 * CiviRulesRuleCondition.Delete API
 *
 * @param array $params
 *
 * @return array API result descriptor
 */
function civicrm_api3_civi_rules_rule_condition_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}
