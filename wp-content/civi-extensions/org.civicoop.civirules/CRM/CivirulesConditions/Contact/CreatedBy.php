<?php

class CRM_CivirulesConditions_Contact_CreatedBy extends CRM_Civirules_Condition {

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
    $added_id=$triggerData->getContactId();
    $contact_id = CRM_Core_DAO::singleValueQuery("SELECT modified_id contact_id FROM civicrm_log WHERE id = (SELECT MIN(id)  FROM civicrm_log WHERE entity_table like 'civicrm_contact' and entity_id = $added_id)");
    //$contact_id = $triggerData->getContactId();
    $checkGroupIds = $this->conditionParams['group_ids'];
    if (!isset($this->conditionParams['check_group_tree'])) {
      $this->conditionParams['check_group_tree'] = FALSE;
    }
    // if check_group_tree, add child groups to checkGroupIds (link https://lab.civicrm.org/extensions/civirules/issues/18)
    if ($this->conditionParams['check_group_tree']) {
      $children = CRM_Contact_BAO_GroupNesting::getDescendentGroupIds($checkGroupIds);
      foreach ($children as $child) {
        if (!in_array($child, $checkGroupIds)) {
          $checkGroupIds[] = $child;
        }
      }
    }
    switch($this->conditionParams['operator']) {
      case 'in one of':
        $isConditionValid = $this->contactIsMemberOfOneGroup($contact_id, $checkGroupIds);
        break;
      case 'in all of':
        $isConditionValid = $this->contactIsMemberOfAllGroups($contact_id, $checkGroupIds);
        break;
      case 'not in':
        $isConditionValid = $this->contactIsNotMemberOfGroup($contact_id, $checkGroupIds);
        break;
    }
    return $isConditionValid;
  }
  protected function contactIsNotMemberOfGroup($contact_id, $group_ids) {
    $isValid = TRUE;
    foreach($group_ids as $gid) {
      if (CRM_CivirulesConditions_Utils_GroupContact::isContactInGroup($contact_id, $gid)) {
        $isValid = FALSE;
        break;
      }
    }
    return $isValid;
  }

  protected function contactIsMemberOfOneGroup($contact_id, $group_ids) {
    $isValid = FALSE;
    foreach($group_ids as $gid) {
      if (CRM_CivirulesConditions_Utils_GroupContact::isContactInGroup($contact_id, $gid)) {
        $isValid = TRUE;
        break;
      }
    }
    return $isValid;
  }

  protected function contactIsMemberOfAllGroups($contact_id, $group_ids) {
    $isValid = 0;
    foreach($group_ids as $gid) {
      if (CRM_CivirulesConditions_Utils_GroupContact::isContactInGroup($contact_id, $gid)) {
        $isValid++;
      }
    }
    if (count($group_ids) == $isValid && count($group_ids) > 0) {
      return TRUE;
    }
    return FALSE;
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/contact_ingroup', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $operators = CRM_CivirulesConditions_Contact_InGroup::getOperatorOptions();
    $operator = $this->conditionParams['operator'];
    $operatorLabel = ts('unknown');
    if (isset($operators[$operator])) {
      $operatorLabel = $operators[$operator];
    }

    $groups = '';
    foreach($this->conditionParams['group_ids'] as $gid) {
      if (strlen($groups)) {
        $groups .= ', ';
      }
      try {
        $groups .= civicrm_api3('Group', 'getvalue', [
          'return' => 'title',
          'id' => $gid
        ]);
      } catch (Exception $e) {
        // Do nothing.
      }
    }
    $friendlyTxt = $operatorLabel . ' groups (' . $groups . ')';
    if ($this->conditionParams['check_group_tree']) {
      $friendlyTxt .= ' (also checking child group membership)';
    }
    return $friendlyTxt;
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

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $params = parent::exportConditionParameters();
    if (!empty($params['group_ids']) && is_array($params['group_ids'])) {
      foreach($params['group_ids'] as $i => $gid) {
        try {
          $params['group_ids'][$i] = civicrm_api3('Group', 'getvalue', [
            'return' => 'name',
            'id' => $gid,
          ]);
        } catch (CRM_Core_Exception $e) {
        }
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
    if (!empty($condition_params['group_ids']) && is_array($condition_params['group_ids'])) {
      foreach($condition_params['group_ids'] as $i => $gid) {
        try {
          $condition_params['group_ids'][$i] = civicrm_api3('Group', 'getvalue', [
            'return' => 'id',
            'name' => $gid,
          ]);
        } catch (CRM_Core_Exception $e) {
        }
      }
    }
    return parent::importConditionParameters($condition_params);
  }

}
