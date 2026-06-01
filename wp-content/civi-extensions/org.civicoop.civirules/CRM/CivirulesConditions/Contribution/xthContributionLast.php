<?php

/**
 * Class for CiviRule Condition xth Contribution in last time interval
 *
 * @author Sandor Semsey <sandor@es-progress.hu>
 * @date 16 Feb 2023
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */
class CRM_CivirulesConditions_Contribution_xthContributionLast extends CRM_Civirules_Condition {

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
        'contribution_status_id' => ['IN' => $this->_conditionParams['contribution_status']],
        'receive_date' => ['>=' => date('Y-m-d', strtotime("-{$this->_conditionParams['interval']} {$this->_conditionParams['interval_unit']}"))],
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/contribution/xthcontributionlast', $ruleConditionId);
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
   * @throws \Exception
   */
  public function userFriendlyConditionParams() {
    $operators = CRM_Civirules_Utils::getGenericComparisonOperatorOptions();
    $financialTypes = CRM_Civirules_Utils::getFinancialTypes();
    $finTypesTxt = [];
    foreach ($this->_conditionParams['financial_type'] as $financialType) {
      $finTypesTxt[] = $financialTypes[$financialType];
    }
    $statuses = CRM_Civirules_Utils_OptionGroup::getActiveValues(CRM_Civirules_Utils::getOptionGroupIdWithName('contribution_status'));
    $statusesTxt = [];
    foreach ($this->_conditionParams['contribution_status'] as $status) {
      $statusesTxt[] = $statuses[$status];
    }
    $units = CRM_CivirulesConditions_Form_Contribution_xthContributionLast::getIntervalUnits();
    return ts('Number of '). implode(' or ', $statusesTxt) . ts(' contributions in the last ')
      . $this->_conditionParams['interval'] . ' ' . $units[$this->_conditionParams['interval_unit']]
      . ts(' of financial type ') . implode(' or ', $finTypesTxt)
      . ' ' .  $operators[$this->_conditionParams['operator']] . ' '
      . $this->_conditionParams['number_contributions'];
  }
}
