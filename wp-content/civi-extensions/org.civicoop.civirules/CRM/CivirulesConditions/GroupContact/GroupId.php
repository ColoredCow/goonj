<?php

class CRM_CivirulesConditions_GroupContact_GroupId extends CRM_Civirules_Condition {

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

  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $groupContact = $triggerData->getEntityData('GroupContact');
    if (($groupContact['group_id'] ?? 0) == $this->conditionParams['group_id']) {
      return true;
    }
    return false;
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/groupcontact/groupid', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    if (!empty($this->conditionParams['group_id'])) {
      try {
        $group = civicrm_api3('Group', 'getvalue', [
          'return' => 'title',
          'id' => $this->conditionParams['group_id']
        ]);
        return ts('Group is %1', [1 => $group]);
      } catch (Exception $e) {
        return '';
      }
    }
    return '';
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $params = parent::exportConditionParameters();
    if (!empty($params['group_id'])) {
      try {
        $params['group_id'] = civicrm_api3('Group', 'getvalue', [
          'return' => 'name',
          'id' => $params['group_id'],
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
    if (!empty($condition_params['group_id'])) {
      try {
        $condition_params['group_id'] = civicrm_api3('Group', 'getvalue', [
          'return' => 'id',
          'name' => $condition_params['group_id'],
        ]);
      } catch (\CRM_Core_Exception $e) {
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
    return $trigger->doesProvideEntity('GroupContact');
  }

}
