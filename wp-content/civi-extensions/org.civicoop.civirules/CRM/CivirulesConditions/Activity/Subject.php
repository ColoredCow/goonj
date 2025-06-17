<?php

class CRM_CivirulesConditions_Activity_Subject extends CRM_Civirules_Condition {

  private $conditionParams = [];

  public function getExtraDataInputUrl($ruleConditionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/activity_subject', $ruleConditionId);
  }

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
   * Method to check if the condition is valid
   *
   * @param object $triggerData
   * @return bool
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $isConditionValid = FALSE;
    $activityData = $triggerData->getEntityData('Activity');
    $activity = civicrm_api3('Activity', 'getsingle', [
      'return' => ['subject'],
      'id' => $activityData['id'],
    ]);
    switch ($this->conditionParams['operator']) {
      case 'exact_match':
        if (trim(mb_strtolower($activity['subject'])) == trim(mb_strtolower($this->conditionParams['text']))) {
          $isConditionValid = TRUE;
        }
        break;
      case 'contains':
        if (strpos(mb_strtolower($activity['subject']), mb_strtolower($this->conditionParams['text'])) !== false){
          $isConditionValid = TRUE;
        }
        break;
    }
    return $isConditionValid;
  }

  /**
   * Returns a user friendly text explaining the condition params
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    $friendlyText = "";
    if ($this->conditionParams['operator'] == 'contains') {
      $friendlyText = "Activity subject contains the text '{$this->conditionParams['text']}'.";
    }
    if ($this->conditionParams['operator'] == 'exact_match') {
      $friendlyText = "Activity subject is an exact match to '{$this->conditionParams['text']}'.";
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
    return $trigger->doesProvideEntity('Activity');
  }

}
