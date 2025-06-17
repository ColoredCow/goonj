<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesConditions_Contact_LoggedIn extends CRM_Civirules_Condition {

  private $conditionParams = [];

  /**
   * Method to set the Rule Condition data
   *
   * @param array $ruleCondition
   * @access public
   */
  public function setRuleConditionData($ruleCondition) {
    parent::setRuleConditionData($ruleCondition);
    $this->conditionParams = [];
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
    $loggedIn = CRM_Utils_System::isUserLoggedIn();
    if ($this->conditionParams['operator'] === 'is logged in' && $loggedIn) {
      $isConditionValid = TRUE;
    }
    elseif ($this->conditionParams['operator'] === 'is not logged in' && !$loggedIn) {
      $isConditionValid = TRUE;
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/contact_loggedin', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $operators = self::getOperatorOptions();
    $operator = $this->conditionParams['operator'];
    $friendlyTxt = $operators[$operator];
    return $friendlyTxt;
  }

  /**
   * Method to get operators
   *
   * @return array
   */
  public static function getOperatorOptions() {
    return [
      'is logged in' => E::ts('User is logged in'),
      'is not logged in' => E::ts('User is not logged in'),
    ];
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
    return $trigger->doesProvideEntity('Contact');
  }

}
