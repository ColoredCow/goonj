<?php
/**
 * Abstract class for generic value comparison conditions
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

abstract class CRM_CivirulesConditions_Generic_ValueComparison extends CRM_Civirules_Condition {

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
    }
  }

  /**
   * Returns an array with all possible options for the field, in
   * case the field is a select field, e.g. gender, or financial type
   * Return false when the field is a select field
   *
   * This method could be overridden by child classes to return the option
   *
   * The return is an array with the field option value as key and the option label as value
   *
   * @return bool
   */
  public function getFieldOptions() {
    return false;
  }

  /**
   * Returns an array with all possible options for the field, in
   * case the field is a select field, e.g. gender, or financial type
   * Return false when the field is a select field
   *
   * This method could be overridden by child classes to return the option
   *
   * The return is an array with the field option value as key and the option label as value
   *
   * @return bool
   */
  public function getFieldOptionsNames() {
    return false;
  }

  /**
   * Returns true when the field is a select option with multiple select
   *
   * @see getFieldOptions
   * @return bool
   */
  public function isMultiple() {
    return false;
  }

  /**
   * Returns the value of the field for the condition
   * For example: I want to check if age > 50, this function would return the 50
   *
   * @param \CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return mixed
   */
  abstract protected function getFieldValue(CRM_Civirules_TriggerData_TriggerData $triggerData);

  /**
   * Returns the value for the data comparison
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  protected function getComparisonValue() {
    if (empty($this->conditionParams['entity'])) {
      // The entity is required. It should always be set but may not be if the condition was not saved properly
      //   and you can't edit the rule if it does not have the data.
      return '';
    }

    $key = false;
    switch ($this->getOperator()) {
      case '=':
      case '!=':
      case '>':
      case '>=':
      case '<':
      case '<=':
      case 'contains string':
      case 'not contains string':
      case 'matches regex':
      case 'not matches regex':
        $key = 'value';
        break;
      case 'is one of':
      case 'is not one of':
      case 'contains one of':
      case 'not contains one of':
      case 'contains all of':
      case 'not contains all of':
        $key = 'multi_value';
        break;
    }

    if ($key && isset($this->conditionParams[$key])) {
      return $this->conditionParams[$key];
    } else {
      return '';
    }
  }

  /**
   * Helps to determine whether a field is a date.
   *
   * @param string $entity
   * @param string $fieldname
   *
   * @return bool True if the field is a date.
   * @throws \CiviCRM_API3_Exception
   */
  protected function isDateField($entity, $fieldname) {
    $dateType = CRM_Utils_Type::T_DATE;
    $timeType = CRM_Utils_Type::T_TIME;
    $dateTimeType = $dateType + $timeType;
    $timestampType = CRM_Utils_Type::T_TIMESTAMP;
    $dateFields = \Civi::cache()->get("isDateFieldList_$entity") ?? [];
    if (!$dateFields) {
      $fields = civicrm_api3(
        $entity,
        'getfields',
        [
          'sequential' => 1,
          'api_action' => 'get',
        ]
      );

      foreach( $fields['values'] as $field ) {
        if (!isset($field['name'])) {
          continue;
        }
        // Certain fields don't have types (eg. Contact group/tag).
        switch($field['type'] ?? '') {
          case $dateType:
          case $timeType:
          case $dateTimeType:
          case $timestampType:
            $dateFields[] = $field['name'];
        }
      }
      \Civi::cache()->set("isDateFieldList_$entity", $dateFields);
    }

    return in_array($fieldname, $dateFields);
  }

  /**
   * Returns an operator for comparison
   *
   * Valid operators are:
   * - equal: =
   * - not equal: !=
   * - greater than: >
   * - lesser than: <
   * - greater than or equal: >=
   * - lesser than or equal: <=
   *
   * @return string operator for comparison
   */
  protected function getOperator() {
    if (!empty($this->conditionParams['operator'])) {
      return $this->conditionParams['operator'];
    } else {
      return '';
    }
  }

  /**
   * Mandatory method to return if the condition is valid
   *
   * @param \CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return bool
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $value = $this->getFieldValue($triggerData);
    $compareValue = $this->getComparisonValue();
    $result = $this->compare($value, $compareValue, $this->getOperator());
    return $result;
  }

  /**
   * Method to compare data
   *
   * @param mixed $leftValue
   * @param mixed $rightValue
   * @param string $operator
   *
   * @return bool
   */
  protected function compare($leftValue, $rightValue, $operator) {
    switch ($operator) {
      case '=':
        if ($leftValue == $rightValue) {
          return true;
        } else {
          return false;
        }
        break;
      case '>':
        if ($leftValue > $rightValue) {
          return true;
        } else {
          return false;
        }
        break;
      case '<':
        if ($leftValue < $rightValue) {
          return true;
        } else {
          return false;
        }
        break;
      case '>=':
        if ($leftValue >= $rightValue) {
          return true;
        } else {
          return false;
        }
        break;
      case '<=':
        if ($leftValue <= $rightValue) {
          return true;
        } else {
          false;
        }
        break;
      case '!=':
        if ($leftValue != $rightValue) {
          return true;
        } else {
          return false;
        }
        break;
      case 'is one of':
        $rightArray = $this->convertValueToArray($rightValue);
        if (in_array($leftValue, $rightArray)) {
          return true;
        }
        return false;
        break;
      case 'is not one of':
        $rightArray = $this->convertValueToArray($rightValue);
        if (!in_array($leftValue, $rightArray)) {
          return true;
        }
        return false;
        break;
      case 'contains string':
        return stripos($leftValue ?? '',  $rightValue) !== FALSE;
        break;
      case 'not contains string':
        return stripos($leftValue ?? '',  $rightValue) === FALSE;
        break;
      case 'contains one of':
        $leftArray = $this->convertValueToArray($leftValue);
        $rightArray = $this->convertValueToArray($rightValue);
        if ($this->containsOneOf($leftArray, $rightArray)) {
          return true;
        }
        return false;
        break;
      case 'not contains one of':
        $leftArray = $this->convertValueToArray($leftValue);
        $rightArray = $this->convertValueToArray($rightValue);
        if (!$this->containsOneOf($leftArray, $rightArray)) {
          return true;
        }
        return false;
        break;
      case 'contains all of':
        $leftArray = $this->convertValueToArray($leftValue);
        $rightArray = $this->convertValueToArray($rightValue);
        if ($this->containsAllOf($leftArray, $rightArray)) {
          return true;
        }
        return false;
        break;
      case 'not contains all of':
        $leftArray = $this->convertValueToArray($leftValue);
        $rightArray = $this->convertValueToArray($rightValue);
        if ($this->notContainsAllOf($leftArray, $rightArray)) {
          return true;
        }
        return false;
        break;
      case 'is empty':
        if (empty($leftValue)) {
          return true;
        }
        else if (is_array($leftValue)){
          foreach ($leftValue as $item){
            if (!empty($item)){
              return false;
            }
          }
          return true;
        }
        return false;
      case 'is not empty':
        if (empty($leftValue)) {
          return false;
        }
        else if(is_array($leftValue)){
          foreach ($leftValue as $item){
            if (empty($item)){
              return false;
            }
          }
        }
        return true;
      case 'matches regex':
        preg_match('/' . $rightValue . '/', $leftValue, $matches);
        return (!empty($matches));
        break;
      case 'not matches regex':
        preg_match('/' . $rightValue . '/', $leftValue, $matches);
        return (empty($matches));
        break;
      default:
        return false;
        break;
    }
    return false;
  }

  /**
   * @param mixed $leftValues
   * @param mixed $rightValues
   *
   * @return bool
   */
  protected function containsOneOf($leftValues, $rightValues) {
    foreach($leftValues as $leftvalue) {
      if (in_array($leftvalue, $rightValues)) {
        return true;
      }
    }
    return false;
  }

  /**
   * @param mixed $leftValues
   * @param mixed $rightValues
   *
   * @return bool
   */
  protected function containsAllOf($leftValues, $rightValues) {
    $foundValues = [];
    foreach($leftValues as $leftVaue) {
      if (in_array($leftVaue, $rightValues)) {
        $foundValues[] = $leftVaue;
      }
    }
    if (count($foundValues) > 0 && count($foundValues) == count($rightValues)) {
      return true;
    }
    return false;
  }

  /**
   * @param mixed $leftValues
   * @param mixed $rightValues
   *
   * @return bool
   */
  protected function notContainsAllOf($leftValues, $rightValues) {
    foreach($rightValues as $rightValue) {
      if (in_array($rightValue, $leftValues)) {
        return false;
      }
    }
    return true;
  }

  /**
   * Converts a string to an array, the delimiter is the CRM_Core_DAO::VALUE_SEPERATOR
   *
   * This function could be overridden by child classes to define their own array
   * seperator
   *
   * @param mixed $value
   *
   * @return array
   */
  protected function convertValueToArray($value) {
    if (is_array($value)) {
      return $value;
    }
    //split on new lines
    return explode(CRM_Core_DAO::VALUE_SEPARATOR, $value);
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/datacomparison', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    return htmlentities(($this->getOperator())).' '.htmlentities($this->getComparisonValue());
  }

  /**
   * Returns an array with possible operators
   *
   * @return array
   */
  public function getOperators() {
    return [
      '=' => ts('Is equal to'),
      '!=' => ts('Is not equal to'),
      '>' => ts('Is greater than'),
      '<' => ts('Is less than'),
      '>=' => ts('Is greater than or equal to'),
      '<=' => ts('Is less than or equal to'),
      'contains string' => ts('Contains string (case insensitive)'),
      'not contains string' => ts('Does not contain string (case insensitive)'),
      'is empty' => ts('Is empty'),
      'is not empty' => ts('Is not empty'),
      'is one of' => ts('Is one of'),
      'is not one of' => ts('Is not one of'),
      'contains one of' => ts('Does contain one of'),
      'not contains one of' => ts('Does not contain one of'),
      'contains all of' => ts('Does contain all of'),
      'not contains all of' => ts('Does not contain all of'),
      'matches regex' => ts('Matches regular expression'),
      'not matches regex' => ts('Does not match regular expression'),
    ];
  }

}
