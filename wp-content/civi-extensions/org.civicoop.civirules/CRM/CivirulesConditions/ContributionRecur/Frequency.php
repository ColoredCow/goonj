<?php

/**
 * Class CRM_CivirulesConditions_Contribution_Recur_Frequency
 *
 * This CiviRule condition will check if the frequency unit and interval of the recurring contribution is OK
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 */

class CRM_CivirulesConditions_ContributionRecur_Frequency extends CRM_Civirules_Condition {

  private $_conditionParams = array();

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
    $contributionRecur = $triggerData->getEntityData('ContributionRecur');
    if ($contributionRecur['frequency_unit'] && $contributionRecur['frequency_interval']) {
      if ($contributionRecur['frequency_unit'] == $this->_conditionParams['frequency_unit'] && $contributionRecur['frequency_interval'] == $this->_conditionParams['frequency_interval']) {
        $isConditionValid = TRUE;
      }
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/recurfrequency', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $frequencyUnits = CRM_Civirules_Utils::getFrequencyUnits();
    return "Recurring contribution has frequency of " . $this->_conditionParams['frequency_interval'] . " " . $frequencyUnits[$this->_conditionParams['frequency_unit']];
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
    return $trigger->doesProvideEntity('ContributionRecur');
  }

}
