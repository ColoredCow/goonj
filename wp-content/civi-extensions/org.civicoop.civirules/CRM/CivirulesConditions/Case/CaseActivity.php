<?php

use Civi\Api4\Activity;
use CRM_Civirules_ExtensionUtil as E;

/**
 * Class for "Days since Last Case Activity" condition
 */
class CRM_CivirulesConditions_Case_CaseActivity extends CRM_Civirules_Condition {

  private $conditionParams = [];

  /**
   * Method to set the Rule Condition data
   *
   * @param array $ruleCondition
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
    $case = $triggerData->getEntityData('Case');
    $daysInactive = $this->conditionParams['days_inactive'];

    try {
      $lastActivity = Activity::get(FALSE)
        ->addSelect('modified_date')
        ->addWhere('case_id', '=', $case['id'])
        ->addOrderBy('modified_date', 'DESC')
        ->execute()
        ->first();
      if (empty($lastActivity)) {
        return FALSE;
      }
    }
    catch (Exception $e) {
      \Civi::log('civirules')->error('Exception checking condition "Days since Last Case Activity": ' . $e->getMessage());
      return FALSE;
    }

    $lastActivityDate = DateTime::createFromFormat("Y-m-d H:i:s", $lastActivity['modified_date']);
    $today = new DateTime();
    $diff = $today->diff($lastActivityDate)->format("%a");

    if($diff >= $daysInactive) {
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
   *
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleConditionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/case/caseactivity', $ruleConditionId);
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
   *
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    return $trigger->doesProvideEntity('Case');
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    return E::ts('%1 Days since Last Case Activity', [1 => $this->conditionParams['days_inactive']]);
  }

}
