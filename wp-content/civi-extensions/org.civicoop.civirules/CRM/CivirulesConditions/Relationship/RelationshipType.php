<?php

class CRM_CivirulesConditions_Relationship_RelationshipType extends CRM_Civirules_Condition {

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
    $relationship = $triggerData->getEntityData('Relationship');
    // if no relationship type, return FALSE
    if (!isset($relationship['relationship_type_id'])) {
      return $isConditionValid;
    }
    switch ($this->conditionParams['operator']) {
      case 0:
        if (in_array($relationship['relationship_type_id'], $this->conditionParams['relationship_type_id'])) {
          $isConditionValid = TRUE;
        }
        break;
      case 1:
        if (!in_array($relationship['relationship_type_id'], $this->conditionParams['relationship_type_id'])) {
          $isConditionValid = TRUE;
        }
        break;
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
    if (!empty($params['relationship_type_id']) && is_array($params['relationship_type_id'])) {
      foreach($params['relationship_type_id'] as $i => $gid) {
        try {
          $params['relationship_type_id'][$i] = civicrm_api3('RelationshipType', 'getvalue', [
            'return' => 'name_a_b',
            'id' => $gid,
          ]);
        } catch (CiviCRM_API3_Exception $e) {
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
    if (!empty($condition_params['relationship_type_id']) && is_array($condition_params['relationship_type_id'])) {
      foreach($condition_params['relationship_type_id'] as $i => $gid) {
        try {
          $condition_params['relationship_type_id'][$i] = civicrm_api3('RelationshipType', 'getvalue', [
            'return' => 'id',
            'name_a_b' => $gid,
          ]);
        } catch (CiviCRM_API3_Exception $e) {
        }
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/relationship/relationshiptype', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $relationshipTypes = self::getRelationshipTypes();
    $friendlyText = "";
    if ($this->conditionParams['operator'] == 0) {
      $friendlyText = 'Relationship Type is one of: ';
    }
    if ($this->conditionParams['operator'] == 1) {
      $friendlyText = 'Relationship Type is NOT one of: ';
    }
    $relationshipText = array();
    foreach ($this->conditionParams['relationship_type_id'] as $relationshipTypeId) {
      $relationshipText[] = $relationshipTypes[$relationshipTypeId];
    }
    if (!empty($relationshipText)) {
      $friendlyText .= implode(", ", $relationshipText);
    }
    return $friendlyText;
  }

  public static function getRelationshipTypes() {
    $return = array();
    $relationshipTypes = civicrm_api3('RelationshipType', 'Get', array('is_active' => 1, 'options' => array('limit' => 0)));
    foreach ($relationshipTypes['values'] as $relationshipType) {
      $return[$relationshipType['id']] = $relationshipType['label_a_b'].' - '.$relationshipType['label_b_a'];
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
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    return $trigger->doesProvideEntity('Relationship');
  }

}
