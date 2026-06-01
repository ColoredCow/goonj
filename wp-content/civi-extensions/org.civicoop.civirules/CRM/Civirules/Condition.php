<?php
/**
 * Abstract Class for CiviRules condition
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

abstract class CRM_Civirules_Condition {

  protected $ruleCondition = [];

  /**
   * Method to set RuleConditionData
   *
   * @param array $ruleCondition
   */
  public function setRuleConditionData(array $ruleCondition) {
    $this->ruleCondition = [];
    if (is_array($ruleCondition)) {
      $this->ruleCondition = $ruleCondition;
    }
  }

  /**
   * This method returns TRUE or false when an condition is valid or not
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return bool
   */
  public abstract function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData);

  /**
   * Returns a redirect url to extra data input from the user after adding a condition
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleConditionId
   *
   * @return bool|string
   */
  abstract public function getExtraDataInputUrl($ruleConditionId);

  /**
   * @param string $url
   * @param int $ruleConditionID
   *
   * @return string
   */
  public function getFormattedExtraDataInputUrl(string $url, int $ruleConditionID): string {
    return CRM_Utils_System::url($url, 'rule_condition_id=' . $ruleConditionID, FALSE, NULL, FALSE, FALSE, TRUE);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    return '';
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    if (!empty($this->ruleCondition['condition_params'])) {
      return unserialize($this->ruleCondition['condition_params']);
    }
    return [];
  }

  /**
   * Returns condition data as an array and ready for import.
   * E.g. replace name for ids.
   *
   * @return string
   */
  public function importConditionParameters($condition_params=null) {
    if (!empty($condition_params)) {
      return serialize($condition_params);
    }
    return '';
  }

  /**
   * This function validates whether this condition works with the selected trigger.
   *
   * This function could be overridden in child classes to provide additional validation
   * whether a condition is possible in the current setup. E.g. we could have a condition
   * which works on contribution or on contributionRecur then this function could do
   * this kind of validation and return false/TRUE
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   *
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    return TRUE;
  }

  /**
   * Logs a message to the logger
   *
   * @param $message
   * @param \CRM_Civirules_TriggerData_TriggerData|NULL $triggerData
   * @param string $level Should be one of \Psr\Log\LogLevel
   */
  protected function logCondition($message, ?CRM_Civirules_TriggerData_TriggerData $triggerData=null, $level=\Psr\Log\LogLevel::INFO) {
    $context = [];
    $context['message'] = $message;
    $context['rule_id'] = $this->ruleCondition['rule_id'];
    $rule = new CRM_Civirules_BAO_Rule();
    $rule->id = $this->ruleCondition['rule_id'];
    $context['rule_title'] = '';
    if ($rule->find(TRUE)) {
      $context['rule_title'] = $rule->label;
    }
    $context['rule_condition_id'] = $this->ruleCondition['id'];
    $context['condition_label'] = CRM_Civirules_BAO_Condition::getConditionLabelWithId($this->ruleCondition['condition_id']);
    $context['condition_parameters'] = $this->userFriendlyConditionParams();
    $context['contact_id'] = $triggerData ? $triggerData->getContactId() : - 1;
    $msg = "Rule: '{$context['rule_title']}' with id {$context['rule_id']}: Condition: {$context['condition_label']} with id {$context['rule_condition_id']}: {$message}";
    if ($context['contact_id'] > 0) {
      $msg .= ": For contact: {contact_id}";
    }
    CRM_Civirules_Utils_LoggerFactory::log($msg, $context, $level);
  }

}
