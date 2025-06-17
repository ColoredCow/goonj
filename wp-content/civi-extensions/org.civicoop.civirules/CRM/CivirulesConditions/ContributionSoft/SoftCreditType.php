<?php

class CRM_CivirulesConditions_ContributionSoft_SoftCreditType extends CRM_Civirules_Condition {

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
   * Method to determine if the condition is valid
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $isConditionValid = FALSE;
    $contributionSoft = $triggerData->getEntityData('ContributionSoft');
    if (!isset($contributionSoft['soft_credit_type_id'])) {
      // The soft credit type could be empty because it was submitted via API or otherwise undefined at post time.
      // So we have to look it up in the database.
      $contributionSoft['soft_credit_type_id'] = CRM_Core_DAO::singleValueQuery("SELECT soft_credit_type_id FROM civicrm_contribution_soft WHERE id = %1", [1 => [$contributionSoft['id'], 'Integer']]);
    }
    switch ($this->conditionParams['operator']) {
      case 0:
        if (in_array($contributionSoft['soft_credit_type_id'], $this->conditionParams['soft_credit_type_id'])) {
          $isConditionValid = TRUE;
        }
        break;

      case 1:
        if (!in_array($contributionSoft['soft_credit_type_id'], $this->conditionParams['soft_credit_type_id'])) {
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/contribution_soft_type', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $friendlyText = "";

    if ($this->conditionParams['operator'] == 0) {
      $friendlyText = 'Soft Credit Type is one of: ';
    }
    if ($this->conditionParams['operator'] == 1) {
      $friendlyText = 'Soft Credit Type is NOT one of: ';
    }
    $selectedSoftCreditTypes = array_column(civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'option_group_id' => "soft_credit_type",
      'value' => ['IN' => $this->conditionParams['soft_credit_type_id']],
    ])['values'], 'label');

    if ($selectedSoftCreditTypes) {
      $friendlyText .= implode(", ", $selectedSoftCreditTypes);
    }

    return $friendlyText;
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $params = parent::exportConditionParameters();
    if (!empty($params['soft_credit_type_id'])) {
      try {
        $params['soft_credit_type_id'] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'name',
          'value' => $params['soft_credit_type_id'],
          'option_group_id' => 'soft_credit_type',
        ]);
      } catch (\CiviCRM_Api3_Exception $e) {
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
    if (!empty($params['soft_credit_type_id'])) {
      try {
        $condition_params['soft_credit_type_id'] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'value',
          'name' => $params['soft_credit_type_id'],
          'option_group_id' => 'soft_credit_type',
        ]);
      } catch (\CiviCRM_Api3_Exception $e) {
        // Do nothing.
      }
    }
    return parent::importConditionParameters($condition_params);
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
    return $trigger->doesProvideEntity('ContributionSoft');
  }

}
