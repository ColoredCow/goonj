<?php

class CRM_CivirulesConditions_Group_GroupType extends CRM_Civirules_Condition {

  /**
   * @var array
   */
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
   *
   * @return bool
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $isConditionValid = FALSE;
    $group = $triggerData->getEntityData('Group');
    // getting a group with groupType as Array instead of string.
    $group = civicrm_api3('Group', 'getsingle', ['id' => $group['id']]);
    // if no case type, return FALSE
    if (!isset($group['group_type'])) {
      return $isConditionValid;
    }
    // Our assumptions is that we have only one case type id per case.
    switch ($this->conditionParams['operator']) {
      case 0:
        if (in_array( $this->conditionParams['group_type_id'], $group['group_type'])) {
          $isConditionValid = TRUE;
        }
        break;
      case 1:
        if (!in_array( $this->conditionParams['group_type_id'], $group['group_type'])) {
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
   *
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleConditionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/group/grouptype', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    $groupTypes = self::getGroupTypes();
    $friendlyText = "";
    if ($this->conditionParams['operator'] == 0) {
      $friendlyText = 'Group Type is one of: ';
    }
    if ($this->conditionParams['operator'] == 1) {
      $friendlyText = 'Group Type is NOT one of: ';
    }
    $friendlyText .= $groupTypes[$this->conditionParams['group_type_id']] ?? 'Unknown';
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
    if (!empty($params['group_type_id']) && is_array($params['group_type_id'])) {
      foreach($params['group_type_id'] as $i => $j) {
        $params['group_type_id'][$i] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'name',
          'value' => $j,
          'option_group_id' => 'group_type',
        ]);
      }
    } elseif (!empty($params['group_type_id'])) {
      try {
        $params['group_type_id'] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'name',
          'value' => $params['group_type_id'],
          'option_group_id' => 'group_type',
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
    if (!empty($condition_params['group_type_id']) && is_array($condition_params['group_type_id'])) {
      foreach($condition_params['group_type_id'] as $i => $j) {
        $condition_params['group_type_id'][$i] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'name',
          'value' => $j,
          'option_group_id' => 'group_type',
        ]);
      }
    } elseif (!empty($condition_params['group_type_id'])) {
      try {
        $condition_params['group_type_id'] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'value',
          'name' => $condition_params['group_type_id'],
          'option_group_id' => 'group_type',
        ]);
      } catch (\CRM_Core_Exception $e) {
        // Do nothing.
      }
    }
    return parent::importConditionParameters($condition_params);
  }

  /**
   * @return array
   * @throws \CRM_Core_Exception
   */
  public static function getGroupTypes() {
    $return = [];
    $option_group_id = civicrm_api3('OptionGroup', 'getvalue', ['return' => 'id', 'name' => 'group_type']);
    $groupTypes = civicrm_api3('OptionValue', 'Get', ['option_group_id' => $option_group_id]);
    foreach ($groupTypes['values'] as $groupType) {
      $return[$groupType['value']] = $groupType['label'];
    }
    return $return;
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
    return $trigger->doesProvideEntity('Group');
  }

}
