<?php

class CRM_CivirulesConditions_Case_CaseType extends CRM_Civirules_Condition {

  private $conditionParams = array();

  /**
   * Method to set the Rule Condition data
   *
   * @param array $ruleCondition
   * @access public
   */
  public function setRuleConditionData($ruleCondition) {
    parent::setRuleConditionData($ruleCondition);
    $this->conditionParams = array();
    if (!empty($this->ruleCondition['condition_params'])) {
      $this->conditionParams = unserialize($this->ruleCondition['condition_params']);
    }
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $params = parent::exportConditionParameters();
    if (!empty($params['case_type_id'])) {
      try {
        $params['case_type_id'] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'name',
          'value' => $params['case_type_id'],
          'option_group_id' => 'case_type',
        ]);
      } catch (\CRM_Core_Exception $e) {
        // Do nothing.
      }
    }
    return $params;
  }

  /**
   * Returns condition data as an array and ready for import.
   * E.g. replace name for ids.
   *
   * @return string
   */
  public function importConditionParameters($condition_params = NULL) {
    if (!empty($condition_params['case_type_id'])) {
      try {
        $condition_params['case_type_id'] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'value',
          'name' => $condition_params['case_type_id'],
          'option_group_id' => 'case_type',
        ]);
      } catch (\CRM_Core_Exception $e) {
        // Do nothing.
      }
    }
    return parent::importConditionParameters($condition_params);
  }

  /**
   * Method to determine if the condition is valid
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $isConditionValid = FALSE;
    $case = $triggerData->getEntityData('Case');
    // if no case type, return FALSE
    if (!isset($case['case_type_id'])) {
      return $isConditionValid;
    }
    // Our assumptions is that we have only one case type id per case.
    $case_type_id = str_replace(CRM_Core_DAO::VALUE_SEPARATOR, "", $case['case_type_id']);
    switch ($this->conditionParams['operator']) {
      case 0:
        if (in_array($case_type_id, $this->conditionParams['case_type_id'])) {
          $isConditionValid = TRUE;
        }
        break;
      case 1:
        if (!in_array($case_type_id, $this->conditionParams['case_type_id'])) {
          $isConditionValid = TRUE;
        }
        break;
    }
    return $isConditionValid;
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a condition
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleConditionId
   * @return bool|string
   * @access public
   * @abstract
   */
  public function getExtraDataInputUrl($ruleConditionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/case/casetype', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $caseTypes = self::getCaseTypes();
    $friendlyText = "";
    if ($this->conditionParams['operator'] == 0) {
      $friendlyText = 'Case Type is one of: ';
    }
    if ($this->conditionParams['operator'] == 1) {
      $friendlyText = 'Case Type is NOT one of: ';
    }
    $caseText = array();
    foreach ($this->conditionParams['case_type_id'] as $caseTypeId) {
      $caseText[] = $caseTypes[$caseTypeId];
    }
    if (!empty($caseText)) {
      $friendlyText .= implode(", ", $caseText);
    }
    return $friendlyText;
  }

  public static function getCaseTypes() {
    $return = [];
    $caseTypes = civicrm_api3('CaseType', 'Get', ['is_active' => 1]);
    foreach ($caseTypes['values'] as $caseType) {
      $return[$caseType['id']] = $caseType['title'];
    }
    return $return;
  }

  /**
   * This function validates whether this condition works with the selected trigger.
   *
   * This function could be overriden in child classes to provide additional validation
   * whether a condition is possible in the current setup. E.g. we could have a condition
   * which works on contribution or on contributionRecur then this function could do
   * this kind of validation and return false/true
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    return $trigger->doesProvideEntity('Case');
  }

}
