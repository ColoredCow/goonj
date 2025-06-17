<?php

class CRM_CivirulesConditions_Contribution_Source extends CRM_Civirules_Condition {

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
   * Method to check if the condition is valid
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   * @access public
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $contribution = $triggerData->getEntityData('Contribution');
    switch ($this->conditionParams['operator']) {
      case 'exact_match':
        if (trim(mb_strtolower($contribution['source'])) == trim(mb_strtolower($this->conditionParams['text']))) {
          $isConditionValid = TRUE;
        }
        break;
      case 'contains':
        if (strpos(mb_strtolower($contribution['source']), mb_strtolower($this->conditionParams['text'])) !== false){
          $isConditionValid = TRUE;
        }
        break;
      default:
        throw new Exception("Invalid operator in 'Contribution source' Condition", 1);
    }
    return $isConditionValid;
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a condition
   *
   * @param int $ruleConditionId
   * @return bool|string
   * @access public
   * @abstract
   */
  public function getExtraDataInputUrl($ruleConditionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/contribution_source', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $friendlyText = "";
    if ($this->conditionParams['operator'] == 'contains') {
      $friendlyText = "Contribution source contains the text '{$this->conditionParams['text']}'.";
    }
    if ($this->conditionParams['operator'] == 'exact_match') {
      $friendlyText = "Contribution source is an exact match to '{$this->conditionParams['text']}'.";
    }
    return $friendlyText;
  }

  /**
   * This function validates whether this condition works with the selected trigger.
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    return $trigger->doesProvideEntity('Contribution');
  }

}