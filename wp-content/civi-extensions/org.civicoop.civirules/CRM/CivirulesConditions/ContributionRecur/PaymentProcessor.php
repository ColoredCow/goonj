<?php

class CRM_CivirulesConditions_ContributionRecur_PaymentProcessor extends CRM_Civirules_Condition {

  private $conditionParams = [];

  /**
   * Method to set the Rule Condition data
   *
   * @param array $ruleCondition
   * @access public
   */
  public function setRuleConditionData($ruleCondition) {
    parent::setRuleConditionData($ruleCondition);
    $this->conditionParams = [];
    if (!empty($this->ruleCondition['condition_params'])) {
      $this->conditionParams = unserialize($this->ruleCondition['condition_params']);
    }
  }

  /**
   * This method returns true or false when an condition is valid or not
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   * @access public
   * @abstract
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    // To do add condition checking
    $sqlParams = [];
    $whereClauses = [];

    switch ($triggerData->getEntity()) {
      case 'ContributionRecur':
        $whereClauses[] = "ccr.id = %1";
        $sql = "SELECT ccr.id FROM civicrm_contribution_recur ccr WHERE ";
        break;

      case 'Contribution':
        $whereClauses[] = "cc.id = %1";
        $sql = "SELECT cc.id FROM civicrm_contribution cc
LEFT JOIN civicrm_contribution_recur ccr ON ccr.id = cc.contribution_recur_id WHERE ";
        break;

      case 'Membership':
        $whereClauses[] = "cm.id = %1";
        $sql = "SELECT cm.id FROM civicrm_membership cm
LEFT JOIN civicrm_contribution_recur ccr ON ccr.id = cm.contribution_recur_id WHERE ";
        break;
    }

    // We only select live payment processors, but trigger for the test payment processors too
    $livePaymentProcessors = CRM_Civirules_Utils::getPaymentProcessors(TRUE);
    $testPaymentProcessors = array_flip(CRM_Civirules_Utils::getPaymentProcessors(FALSE));
    foreach ($this->conditionParams['payment_processor_id'] as $paymentProcessorID) {
      $paymentProcessorName = $livePaymentProcessors[$paymentProcessorID];
      $this->conditionParams['payment_processor_id'][] = $testPaymentProcessors[$paymentProcessorName];
    }

    $sqlParams[1] = [$triggerData->getEntityData($triggerData->getEntity())['id'], 'Integer'];
    if (count($this->conditionParams['payment_processor_id'])) {
      switch ($this->conditionParams['payment_processor_id_operator']) {
        case 'in':
          $whereClauses[] = 'payment_processor_id IN (' . implode(',', $this->conditionParams['payment_processor_id']). ')';
          break;
        case 'not in':
          $whereClauses[] = '(payment_processor_id NOT IN ('
            . implode(',', $this->conditionParams['payment_processor_id'])
            . ') OR payment_processor_id IS NULL)';
          break;
      }
    }

    $sql .= implode(' AND ', $whereClauses);
    $result = CRM_Core_DAO::singleValueQuery($sql, $sqlParams);
    if ($result) {
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/recurringpaymentprocessor', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $label = '';
    $operator_options = self::getOperatorOptions();

    try {
      $params = [
        'is_test' => 0,
        'options' => ['limit' => 0, 'sort' => "name ASC"],
      ];
      $paymentProcessors = civicrm_api3('PaymentProcessor', 'Get', $params);
      if (isset($this->conditionParams['payment_processor_id']) && count($this->conditionParams['payment_processor_id'])) {
        $operator = $operator_options[$this->conditionParams['payment_processor_id_operator']];
        $values = '';
        foreach ($this->conditionParams['payment_processor_id'] as $paymentProcessorID) {
          if (!isset($paymentProcessors['values'][$paymentProcessorID])) {
            continue;
          }
          if (strlen($values)) {
            $values .= ', ';
          }
          $values .= $paymentProcessors['values'][$paymentProcessorID]['name'];
        }
        $label .= ts('Payment processor %1 %2', [
          1 => $operator,
          2 => $values,
        ]);
      }
    } catch (CiviCRM_API3_Exception $ex) {}

    return trim($label);
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $params = parent::exportConditionParameters();
    if (!empty($params['payment_processor_id']) && is_array($params['payment_processor_id'])) {
      foreach($params['payment_processor_id'] as $i => $gid) {
        try {
          $params['payment_processor_id'][$i] = civicrm_api3('PaymentProcessor', 'getvalue', [
            'return' => 'name',
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
    if (!empty($condition_params['payment_processor_id']) && is_array($condition_params['payment_processor_id'])) {
      foreach($condition_params['payment_processor_id'] as $i => $gid) {
        try {
          $condition_params['payment_processor_id'][$i] = civicrm_api3('PaymentProcessor', 'getvalue', [
            'return' => 'id',
            'name' => $gid,
          ]);
        } catch (CiviCRM_API3_Exception $e) {
        }
      }
    }
    return parent::importConditionParameters($condition_params);
  }

  /**
   * Method to get operators
   *
   * @return array
   * @access protected
   */
  public static function getOperatorOptions() {
    return [
      'in' => ts('Is one of'),
      'not in' => ts('Is not one of'),
    ];
  }

  /**
   * This function validates whether this condition works with the selected trigger.
   *
   * This function could be overridden in child classes to provide additional validation
   * whether a condition is possible in the current setup. E.g. we could have a condition
   * which works on contribution or on contributionRecur then this function could do
   * this kind of validation and return false/true
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    if ($trigger->doesProvideEntity('ContributionRecur')) {
      return TRUE;
    }
    elseif ($trigger->doesProvideEntity('Contribution')) {
      return TRUE;
    }
    elseif ($trigger->doesProvideEntity('Membership')) {
      return TRUE;
    }
    return FALSE;
  }

}
