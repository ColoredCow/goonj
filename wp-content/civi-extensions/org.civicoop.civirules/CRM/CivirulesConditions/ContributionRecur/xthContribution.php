<?php

class CRM_CivirulesConditions_ContributionRecur_xthContribution extends CRM_Civirules_Condition {

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
   * @access public
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $isConditionValid = FALSE;
    $contribution = $triggerData->getEntityData('Contribution');

    $contributions = \Civi\Api4\Contribution::get(FALSE)
      ->addSelect('COUNT(*) AS count')
      ->addWhere('contribution_recur_id', '=', $contribution['contribution_recur_id'])
      ->addWhere('contribution_status_id:name', '=', 'Completed')
      ->addClause('OR', ['is_test', '=', TRUE], ['is_test', '=', FALSE])
      ->execute()
      ->first();

    switch ($this->conditionParams['operator']) {
      case 1:
        if ($contributions['count'] != $this->conditionParams['no_of_recurring']) {
          $isConditionValid = TRUE;
        }
      break;
      case 2:
        if ($contributions['count'] > $this->conditionParams['no_of_recurring']) {
          $isConditionValid = TRUE;
        }
      break;
      case 3:
        if ($contributions['count'] >= $this->conditionParams['no_of_recurring']) {
        $isConditionValid = TRUE;
      }
      break;
      case 4:
        if ($contributions['count'] < $this->conditionParams['no_of_recurring']) {
        $isConditionValid = TRUE;
      }
      break;
      case 5:
        if ($contributions['count'] <= $this->conditionParams['no_of_recurring']) {
        $isConditionValid = TRUE;
      }
      break;
      default:
        if ($contributions['count'] == $this->conditionParams['no_of_recurring']) {
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
   */
  public function getExtraDataInputUrl($ruleConditionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/contribution_recur_xth_contribution', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $operator = null;
    switch ($this->conditionParams['operator']) {
      case 1:
        $operator = 'is not equal to';
        break;
      case 2:
        $operator = 'more than';
        break;
      case 3:
        $operator = 'more than or equal to';
        break;
      case 4:
        $operator = 'less than';
        break;
      case 5:
        $operator = 'less than or equal to';
        break;
      default:
        $operator = 'is equal to';
        break;
    }
    return ts('Contribution number of a recurring ').$operator.' '.$this->conditionParams['no_of_recurring'];
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
    return $trigger->doesProvideEntity('Contribution');
  }

}