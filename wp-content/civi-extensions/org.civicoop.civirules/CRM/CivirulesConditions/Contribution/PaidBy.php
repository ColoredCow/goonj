<?php

class CRM_CivirulesConditions_Contribution_PaidBy extends CRM_Civirules_Condition {

  private $_conditionParams = [];

  /**
   * Method to set the Rule Condition data
   *
   * @param array $ruleCondition
   */
  public function setRuleConditionData($ruleCondition) {
    parent::setRuleConditionData($ruleCondition);
    $this->_conditionParams = [];
    if (!empty($this->ruleCondition['condition_params'])) {
      $this->_conditionParams = unserialize($this->ruleCondition['condition_params']);
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
    $contribution = $triggerData->getEntityData('Contribution');
    $paymentInstrumentIds = explode(',', $this->_conditionParams['payment_instrument_id']);
    switch ($this->_conditionParams['operator']) {
      case 0:
        if (in_array($contribution['payment_instrument_id'], $paymentInstrumentIds)) {
          $isConditionValid = TRUE;
        }
      break;
      case 1:
        if (!in_array($contribution['payment_instrument_id'], $paymentInstrumentIds)) {
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/contribution_paidby', $ruleConditionId);
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $params = parent::exportConditionParameters();
    if (!empty($params['payment_instrument_id']) && is_array($params['payment_instrument_id'])) {
      foreach($params['payment_instrument_id'] as $i => $gid) {
        try {
          $params['payment_instrument_id'][$i] = civicrm_api3('OptionValue', 'getvalue', [
            'return' => 'name',
            'id' => $gid,
            'option_group_id' => 'payment_instrument'
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
    if (!empty($condition_params['payment_instrument_id']) && is_array($condition_params['payment_instrument_id'])) {
      foreach($condition_params['payment_instrument_id'] as $i => $gid) {
        try {
          $condition_params['payment_instrument_id'][$i] = civicrm_api3('OptionValue', 'getvalue', [
            'return' => 'id',
            'name' => $gid,
            'option_group_id' => 'payment_instrument'
          ]);
        } catch (CRM_Core_Exception $e) {
        }
      }
    }
    return parent::importConditionParameters($condition_params);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    $operator = null;
    if ($this->_conditionParams['operator'] == 0) {
      $operator = 'is one of';
    }
    if ($this->_conditionParams['operator'] == 1) {
      $operator = 'is not one of';
    }
    $paymentNames = [];
    $paymentInstrumentIds = explode(',', $this->_conditionParams['payment_instrument_id']);
    try {
      $apiParams = [
        'sequential' => 1,
        'return' => ["label"],
        'option_group_id' => "payment_instrument",
        'options' => ['limit' => 0],
        'is_active' => 1,
        'value' => ['IN' => $paymentInstrumentIds],
      ];
      $paymentInstruments = civicrm_api3('OptionValue', 'get', $apiParams);
      foreach ($paymentInstruments['values'] as $paymentInstrument) {
        $paymentNames[] = $paymentInstrument['label'];
      }
    }
    catch (CRM_Core_Exception $ex) {
      $logMessage = ts('Could not find payment_instruments in ') . __METHOD__
        . ts(', error from API OptionValue get: ') . $ex->getMessage();
      Civi::log()->debug($logMessage);
    }
    if (!empty($paymentNames)) {
      return 'Paid by ' . $operator . ' ' . implode(', ', $paymentNames);
    }
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
    return $trigger->doesProvideEntity('Contribution');
  }

}
