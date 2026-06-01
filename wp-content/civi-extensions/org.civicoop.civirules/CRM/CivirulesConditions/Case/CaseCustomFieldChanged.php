<?php

class CRM_CivirulesConditions_Case_CaseCustomFieldChanged extends CRM_Civirules_Condition {

  private $_conditionParams = [];

  /**
   * Method to set the Rule Condition data
   *
   * @param array $ruleCondition
   * @access public
   */
  public function setRuleConditionData($ruleCondition) {
    parent::setRuleConditionData($ruleCondition);
    $this->_conditionParams = [];
    if (!empty($this->ruleCondition['condition_params'])) {
      $this->_conditionParams = unserialize($this->ruleCondition['condition_params']);
    }
  }

  /**
   * Method to determine if the condition is valid
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $isConditionValid = FALSE;
    // if condition custom field not in entity data, return false
    $caseData = $triggerData->getEntityData('Case');
    if ($caseData) {
      $appears = FALSE;
      foreach ($this->_conditionParams['case_custom_field_id'] as $customFieldId) {
        $element = "custom_" . $customFieldId;
        if (isset($caseData[$element])) {
          $appears = TRUE;
        }
      }
      if ($appears) {
        // return true if field changed
        $isConditionValid = $this->hasFieldChanged($triggerData->getOriginalData(), $caseData);
      }
    }
    return $isConditionValid;
  }

  /**
   * Method to determine if one of the condition fields has changed
   *
   * @param $originalData
   * @param $caseData
   * @return bool
   */
  private function hasFieldChanged($originalData, $caseData) {
    foreach ($this->_conditionParams['case_custom_field_id'] as $customFieldId) {
      $element = "custom_" . $customFieldId;
      // changed if new value but no original value
      if (isset($caseData[$element]) && !isset($originalData[$element])) {
        return TRUE;
      }
      // changed if new value not equal old value
      if (isset($caseData[$element]) && isset($originalData[$element])) {
        if ($caseData[$element] != $originalData[$element]) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $params = parent::exportConditionParameters();
    if (!empty($params['case_custom_field_id'])) {
      try {
        $customField = civicrm_api3('CustomField', 'getsingle', [
          'id' => $params['case_custom_field_id'],
        ]);
        $customGroup = civicrm_api3('CustomGroup', 'getsingle', [
          'id' => $customField['custom_group_id'],
        ]);
        unset($params['case_custom_field_id']);
        $params['case_custom_field_group'] = $customGroup['name'];
        $params['case_custom_field_field'] = $customField['name'];
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
    if (!empty($condition_params['case_custom_field_group'])) {
      try {
        $customField = civicrm_api3('CustomField', 'getsingle', [
          'name' => $condition_params['case_custom_field_field'],
          'custom_group_id' => $condition_params['case_custom_field_group'],
        ]);
        $condition_params['case_custom_field_id'] = $customField['id'];
        unset($condition_params['case_custom_field_field']);
        unset($condition_params['case_custom_field_group']);
      } catch (\CRM_Core_Exception $e) {
        // Do nothing.
      }
    }
    return parent::importConditionParameters($condition_params);
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/casecustomfield', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $friendlyText = 'Changed Case Custom Field is one of: ';
    $fields = [];
    try {
      $result = civicrm_api3('CustomField', 'get', [
        'sequential' => 1,
        'return' => ["label"],
        'id' => ['IN' => $this->_conditionParams['case_custom_field_id']],
      ]);
      foreach ($result['values'] as $customField) {
        $fields[] = $customField['label'];
      }
      $friendlyText .= implode(",", $fields);
    }
    catch (CRM_Core_Exception $ex) {
    }
    return $friendlyText;
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
