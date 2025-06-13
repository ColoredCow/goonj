<?php
/**
 * Class for CiviRule Condition ScheduleReminder
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_CivirulesConditions_ActionLog_ScheduledReminder extends CRM_Civirules_Condition {

  private $conditionParams = [];

  /**
   * Method to get additional data for the condition
   *
   * return FALSE if none needed
   */
  public function getExtraDataInputUrl($ruleConditionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/scheduledreminder', $ruleConditionId);
  }

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
   * Method to check if the condition is valid, will check if the actionlog
   * has an email error
   *
   * @param object CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   * @access public
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $isConditionValid = FALSE;
    $actionLogData = $triggerData->getEntityData('ActionLog');
    $actionLog = CRM_Core_BAO_ActionLog::findById($actionLogData['id']);
    $reminder = $actionLog->action_schedule_id;

    switch ($this->conditionParams['operator']) {
      case 0:
        if (in_array($reminder, $this->conditionParams['scheduledreminder_ids'])) {
          $isConditionValid = TRUE;
        }
        break;
      case 1:
        if (!in_array($reminder, $this->conditionParams['scheduledreminder_ids'])) {
         $isConditionValid = TRUE;
        }
        break;
    }
    return $isConditionValid;
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
      $friendlyText = 'Scheduled Reminder is one of: ';
    }
    if ($this->conditionParams['operator'] == 1) {
      $friendlyText = 'Scheduled Reminder is NOT one of: ';
    }
    $names = [];
    $reminderList = CRM_Civirules_Utils::getScheduledReminderList();
    foreach ($this->conditionParams['scheduledreminder_ids'] as $reminderId) {
      $names[] = $reminderList[$reminderId];
    }
    if (!empty($names)) {
      $friendlyText .= implode(", ", $names);
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
    return $trigger->doesProvideEntity('ActionLog');
  }

}
