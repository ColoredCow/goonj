<?php

/**
 * Class for CiviRule Condition xth Contribution
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 12 Nov 2018
 * @funded by Amnesty International Vlaanderen
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */
class CRM_CivirulesConditions_Contribution_xthContribution extends CRM_Civirules_Condition {

  private $_conditionParams = [];

  /**
   * Method to set the Rule Condition data
   *
   * @param array $ruleCondition
   * @access public
   */
  public function setRuleConditionData($ruleCondition) {
    parent::setRuleConditionData($ruleCondition);
    $this->_conditionParams = [];
    if (!empty($this->ruleCondition['condition_params'])) {
      $this->_conditionParams = unserialize($this->ruleCondition['condition_params']);
    }
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $params = parent::exportConditionParameters();
    if (!empty($params['financial_type']) && is_array($params['financial_type'])) {
      foreach($params['financial_type'] as $i => $gid) {
        try {
          $params['financial_type'][$i] = civicrm_api3('FinancialType', 'getvalue', [
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
    if (!empty($condition_params['financial_type']) && is_array($condition_params['financial_type'])) {
      foreach($condition_params['financial_type'] as $i => $gid) {
        try {
          $condition_params['financial_type'][$i] = civicrm_api3('FinancialType', 'getvalue', [
            'return' => 'id',
            'name' => $gid,
          ]);
        } catch (CRM_Core_Exception $e) {
        }
      }
    }
    return parent::importConditionParameters($condition_params);
  }


  /**
   * Method is mandatory and checks if the condition is met
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   * @access public
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $contactId = $triggerData->getContactId();
    // count number of contributions of financial types for contact
    try {
      $apiParams = [
        'financial_type_id' => ['IN' => $this->_conditionParams['financial_type']],
        'contact_id' => $contactId,
        'contribution_status_id' => 'Completed',
      ];
      $count = (int) civicrm_api3('Contribution', 'getcount', $apiParams);
      switch ($this->_conditionParams['operator']) {
        // equals
        case 0:
          if ($count == $this->_conditionParams['number_contributions']) {
            return TRUE;
          }
          break;
        // greater than
        case 1:
          if ($count > $this->_conditionParams['number_contributions']) {
            return TRUE;
          }
          break;
        // greater than or equal
        case 2:
          if ($count >= $this->_conditionParams['number_contributions']) {
            return TRUE;
          }
          break;
        // less than
        case 3:
          if ($count < $this->_conditionParams['number_contributions']) {
            return TRUE;
          }
          break;
        // less than or equal
        case 4:
          if ($count <= $this->_conditionParams['number_contributions']) {
            return TRUE;
          }
          break;
      }
    }
    catch (CRM_Core_Exception $ex) {
      Civi::log()->error(ts('Unexpected error from API Contribution getcount in ') . __METHOD__
        . ts(', error message: ') . $ex->getMessage());
    }
    return FALSE;
  }

  /**
   * Method is mandatory, in this case no additional data input is required
   * so it returns FALSE
   *
   * @param int $ruleConditionId
   * @return bool
   * @access public
   */
  public function getExtraDataInputUrl($ruleConditionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/contribution/xthcontribution', $ruleConditionId);
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

  /**
   * Overridden parent method to set user friendly condition text in form
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    $operators = CRM_Civirules_Utils::getGenericComparisonOperatorOptions();
    $financialTypes = CRM_Civirules_Utils::getFinancialTypes();
    $finTypesTxt = [];
    foreach ($this->_conditionParams['financial_type'] as $financialType) {
      $finTypesTxt[] = $financialTypes[$financialType];
    }
    return ts('Number of contributions of financial type ') . implode(' or ', $finTypesTxt)
      . ' ' .  $operators[$this->_conditionParams['operator']] . ' '
      . $this->_conditionParams['number_contributions'];
  }
}
