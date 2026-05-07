<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesConditions_Membership_Type extends CRM_Civirules_Condition {

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
    $membership = $triggerData->getEntityData('Membership');
    switch ($this->conditionParams['operator']) {
      case 0:
        if ($membership['membership_type_id'] == $this->conditionParams['membership_type_id']) {
          $isConditionValid = TRUE;
        }
      break;

      case 1:
        if ($membership['membership_type_id'] != $this->conditionParams['membership_type_id']) {
          $isConditionValid = TRUE;
        }
      break;

      case 2:
        if (in_array($membership['membership_type_id'], $this->conditionParams['membership_type_ids'])) {
          $isConditionValid = TRUE;
        }
        break;

      case 3:
        if (!in_array($membership['membership_type_id'], $this->conditionParams['membership_type_ids'])) {
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
    if (!empty($params['membership_type_ids']) && is_array($params['membership_type_ids'])) {
      foreach($params['membership_type_ids'] as $i => $j) {
        $params['membership_type_ids'][$i] = civicrm_api3('MembershipType', 'getvalue', [
          'return' => 'name',
          'id' => $j,
        ]);
      }
    } elseif (!empty($params['membership_type_ids'])) {
      try {
        $params['membership_type_ids'] = civicrm_api3('MembershipType', 'getvalue', [
          'return' => 'name',
          'id' => $params['membership_type_ids'],
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
    if (!empty($condition_params['membership_type_ids']) && is_array($condition_params['membership_type_ids'])) {
      foreach($condition_params['membership_type_ids'] as $i => $j) {
        $condition_params['membership_type_ids'][$i] = civicrm_api3('MembershipType', 'getvalue', [
          'return' => 'id',
          'name' => $j,
        ]);
      }
    } elseif (!empty($condition_params['membership_type_ids'])) {
      try {
        $condition_params['membership_type_ids'] = civicrm_api3('MembershipType', 'getvalue', [
          'return' => 'id',
          'name' => $condition_params['membership_type_ids'],
        ]);
      } catch (\CRM_Core_Exception $e) {
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
   */
  public function getExtraDataInputUrl($ruleConditionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/membershiptype', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    $params = [
      'is_active' => 1,
      'options' => ['limit' => 0, 'sort' => "name ASC"],
    ];
    try {
      $membershipTypes = civicrm_api3('MembershipType', 'Get', $params);
      $operator = null;
      if ($this->conditionParams['operator'] == 0) {
        $operator = E::ts('equals');
      }
      if ($this->conditionParams['operator'] == 1) {
        $operator = E::ts('is not equal to');
      }
      if ($this->conditionParams['operator'] == 2) {
        $operator = E::ts('is one of');
      }
      if ($this->conditionParams['operator'] == 3) {
        $operator = E::ts('is NOT one of');
      }
      $membershipTypeLabels = [];
      foreach ($membershipTypes['values'] as $membershipType) {
        if (in_array($this->conditionParams['operator'], [2, 3])) {
          if (in_array($membershipType['id'], $this->conditionParams['membership_type_ids'])) {
            $membershipTypeLabels[] = $membershipType['name'];
          }
        } elseif ($membershipType['id'] == $this->conditionParams['membership_type_id']) {
          $membershipTypeLabels[] = $membershipType['name'];
        }
      }
      return E::ts('Membership type %1 %2', [1 => $operator, 2=> implode(', ', $membershipTypeLabels)]);
    }
    catch (CRM_Core_Exception $ex) {}
    return '';
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
    return $trigger->doesProvideEntity('Membership');
  }

}
