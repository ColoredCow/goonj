<?php
/**
 * Class for CiviRules Condition Contribution Campaign
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 18 May 2016
 * @license AGPL-3.0
 */

class CRM_CivirulesConditions_Contribution_Campaign extends CRM_Civirules_Condition {

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
    $contribution = $triggerData->getEntityData('Contribution');
    switch ($this->conditionParams['operator']) {
      case 0:
        if (in_array($contribution['campaign_id'], $this->conditionParams['campaign_id'])) {
          $isConditionValid = TRUE;
        }
      break;
      case 1:
        if (!in_array($contribution['campaign_id'], $this->conditionParams['campaign_id'])) {
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
    if (!empty($params['campaign_id'])) {
      try {
        $params['campaign_id'] = civicrm_api3('Campaign', 'getvalue', [
          'return' => 'name',
          'id' => $params['campaign_id']
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
    if (!empty($condition_params['campaign_id'])) {
      try {
        $condition_params['campaign_id'] = civicrm_api3('Campaign', 'getvalue', [
          'return' => 'id',
          'name' => $condition_params['campaign_id']
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/contribution_campaign', $ruleConditionId);
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
      $friendlyText = 'Is in one of these campaigns: ';
    }
    if ($this->conditionParams['operator'] == 1) {
      $friendlyText = 'Is NOT in of these campaigns: ';
    }
    $campaignText = array();
    foreach ($this->conditionParams['campaign_id'] as $campaignId) {
      try {
        $campaignText[] = civicrm_api3('Campaign', 'Getvalue', array('id' => $campaignId, 'return' => 'title'));
      } catch (CiviCRM_API3_Exception $ex) {}
    }
    if (!empty($campaignText)) {
      $friendlyText .= implode(", ", $campaignText);
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
    return $trigger->doesProvideEntity('Contribution');
  }

}
