<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesConditions_Membership_ActiveMembership extends CRM_Civirules_Condition {

  protected $conditionParams = [];

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
      $this->conditionParams['membership_type_id'] = (array) $this->conditionParams['membership_type_id'];
    }
  }

  /**
   * This method returns true or false when an condition is valid or not
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return bool
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $params['membership_type_id'] = ['IN' => $this->conditionParams['membership_type_id']];
    $params['contact_id'] = $triggerData->getContactId();
    $params['active_only'] = 1;

    $memberships = civicrm_api3('Membership', 'get', $params);
    if (isset($memberships['values']) && count($memberships['values']) > 0) {
      return ($this->conditionParams['negate'] ? FALSE : TRUE);
    }
    return ($this->conditionParams['negate'] ? TRUE : FALSE);
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/activemembershiptype', $ruleConditionId);
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
      'options' => ['limit' => 0, 'sort' => 'name ASC'],
    ];
    try {
      $membershipTypes = civicrm_api3('MembershipType', 'get', $params);
      if ($this->conditionParams['negate']) {
        $operator = E::ts('is NOT one of');
      }
      else {
        $operator = E::ts('is one of');
      }
      foreach ($membershipTypes['values'] as $membershipType) {
        if (in_array($membershipType['id'], $this->conditionParams['membership_type_id'])) {
          $membershipTypeNames[] = $membershipType['name'];
        }
      }
      return 'Membership Type ' . $operator . ' ' . implode(',', $membershipTypeNames);
    } catch (CRM_Core_Exception $ex) {}
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
    if (!empty($params['membership_type_id'])) {
      try {
        $params['membership_type_id'] = civicrm_api3('MembershipType', 'getvalue', [
          'return' => 'name',
          'id' => $params['membership_type_id'],
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
    if (!empty($condition_params['membership_type_id'])) {
      try {
        $condition_params['membership_type_id'] = civicrm_api3('MembershipType', 'getvalue', [
          'return' => 'id',
          'name' => $condition_params['membership_type_id'],
        ]);
      } catch (\CRM_Core_Exception $e) {
        // Do nothing.
      }
    }
    return parent::importConditionParameters($condition_params);
  }

}
