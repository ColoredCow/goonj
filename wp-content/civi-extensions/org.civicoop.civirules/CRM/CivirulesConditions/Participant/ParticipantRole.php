<?php

class CRM_CivirulesConditions_Participant_ParticipantRole extends CRM_Civirules_Condition {

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
   */

  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $isConditionValid = FALSE;
    $participant = $triggerData->getEntityData('Participant');
    $participant_role_ids = is_array($participant['role_id']) ?  $participant['role_id'] : explode(CRM_Core_DAO::VALUE_SEPARATOR, $participant['role_id']);
    foreach($participant_role_ids as $participant_role_id) {
      switch ($this->conditionParams['operator']) {
        case 0:
          if (in_array($participant_role_id, $this->conditionParams['participant_role_id'])) {
            $isConditionValid = TRUE;
          }
          break;
        case 1:
          if (!in_array($participant_role_id, $this->conditionParams['participant_role_id'])) {
            $isConditionValid = TRUE;
          }
          break;
      }
    }
    return $isConditionValid;
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $params = parent::exportConditionParameters();
    if (!empty($params['participant_role_id']) && is_array($params['participant_role_id'])) {
      foreach($params['participant_role_id'] as $i => $j) {
        $params['participant_role_id'][$i] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'name',
          'value' => $j,
          'option_group_id' => 'participant_role',
        ]);
      }
    } elseif (!empty($params['participant_role_id'])) {
      try {
        $params['participant_role_id'] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'name',
          'value' => $params['participant_role_id'],
          'option_group_id' => 'participant_role',
        ]);
      } catch (\CiviCRM_Api3_Exception $e) {
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
    if (!empty($condition_params['participant_role_id']) && is_array($condition_params['participant_role_id'])) {
      foreach($condition_params['participant_role_id'] as $i => $j) {
        $condition_params['participant_role_id'][$i] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'value',
          'name' => $j,
          'option_group_id' => 'participant_role',
        ]);
      }
    } elseif (!empty($condition_params['participant_role_id'])) {
      try {
        $condition_params['participant_role_id'] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'value',
          'name' => $condition_params['participant_role_id'],
          'option_group_id' => 'participant_role',
        ]);
      } catch (\CiviCRM_Api3_Exception $e) {
        // Do nothing.
      }
    }
    return parent::importConditionParameters($condition_params);
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
    return CRM_Utils_System::url('civicrm/civirule/form/condition/participant_role', 'rule_condition_id='.$ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   * @throws Exception
   */
  public function userFriendlyConditionParams() {
    $friendlyText = "";
    if ($this->conditionParams['operator'] == 0) {
      $friendlyText = 'Participant Role is one of: ';
    }
    if ($this->conditionParams['operator'] == 1) {
      $friendlyText = 'Participant Role is NOT one of: ';
    }
    $roleText = array();
    $participantRoles = civicrm_api3('OptionValue', 'get', array(
      'value' => array('IN' => $this->conditionParams['participant_role_id']),
      'option_group_id' => 'participant_role',
      'options' => array('limit' => 0)
    ));
    foreach($participantRoles['values'] as $role) {
      $roleText[] = $role['label'];
    }

    if (!empty($roleText)) {
      $friendlyText .= implode(", ", $roleText);
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
    return $trigger->doesProvideEntity('Participant');
  }

}
