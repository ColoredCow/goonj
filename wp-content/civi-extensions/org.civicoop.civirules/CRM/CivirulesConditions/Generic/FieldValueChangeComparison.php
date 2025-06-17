<?php

use CRM_Civirules_ExtensionUtil as E;

abstract class CRM_CivirulesConditions_Generic_FieldValueChangeComparison extends CRM_CivirulesConditions_Generic_ValueComparison {

  /**
   * Returns name of entity
   * @fixme should be abstract but requires conversion of all child classes first
   *
   * @return string
   */
  protected function getEntity() {
    return '';
  }

  /**
   * Returns name of the field
   * @fixme should be abstract but requires conversion of all child classes first
   *
   * @return string
   */
  protected function getEntityStatusFieldName() {
    return '';
  }

  /**
   * Returns the value of the field for the condition
   * For example: I want to check if age > 50, this function would return the 50
   *
   * @param \CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return mixed|null
   */
  protected function getOriginalFieldValue(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $entity = $this->getEntity();
    if ($triggerData->getOriginalEntity() != $entity) {
      return NULL;
    }

    $data = $triggerData->getOriginalData();
    $field = $this->getEntityStatusFieldName();
    if (isset($data[$field])) {
      return $data[$field];
    }
    return NULL;
  }

  /**
   * Returns the value of the field for the condition
   * For example: I want to check if age > 50, this function would return the 50
   *
   * @param \CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return mixed|null
   */
  protected function getFieldValue(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $entity = $this->getEntity();
    $data = $triggerData->getEntityData($entity);
    $field = $this->getEntityStatusFieldName();
    if (isset($data[$field])) {
      return $data[$field];
    }
    return NULL;
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
    return $trigger->doesProvideEntity($this->getEntity());
  }

  /**
   * Returns the value for the data comparison
   *
   * @return mixed
   * @access protected
   */
  protected function getOriginalComparisonValue() {
    switch ($this->getOriginalOperator()) {
      case '=':
      case '!=':
      case '>':
      case '>=':
      case '<':
      case '<=':
      case 'contains string':
      case 'not contains string':
        $key = 'original_value';
        break;
      case 'is one of':
      case 'is not one of':
      case 'contains one of':
      case 'not contains one of':
      case 'contains all of':
      case 'not contains all of':
        $key = 'original_multi_value';
        break;
    }

    if (isset($key)
      and !empty($this->conditionParams[$key])) {
      return $this->conditionParams[$key];
    } else {
      return '';
    }
  }

  /**
   * Returns the value for the data comparison
   *
   * @return mixed
   * @access protected
   */
  protected function getComparisonValue() {
    switch ($this->getOperator()) {
      case '=':
      case '!=':
      case '>':
      case '>=':
      case '<':
      case '<=':
      case 'contains string':
      case 'not contains string':
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

    if (isset($key)
      and !empty($this->conditionParams[$key])) {
      return $this->conditionParams[$key];
    } else {
      return '';
    }
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
   * @access protected
   */
  protected function getOriginalOperator() {
    if (!empty($this->conditionParams['original_operator'])) {
      return $this->conditionParams['original_operator'];
    } else {
      return '';
    }
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
   * @access protected
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
   * @param object CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   * @access public
   */

  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    //not the right trigger. The trigger data should contain also
    if (!$triggerData instanceof CRM_Civirules_TriggerData_Interface_OriginalData) {
      return false;
    }

    $originalValue = $this->getOriginalFieldValue($triggerData);
    $originalCompareValue = $this->getOriginalComparisonValue();
    $originalComparison = $this->compare($originalValue, $originalCompareValue, $this->getOriginalOperator());

    $value = $this->getFieldValue($triggerData);
    $compareValue = $this->getComparisonValue();
    $newComparison = $this->compare($value, $compareValue, $this->getOperator());

    if ($originalComparison && $newComparison) {
      return true;
    }
    return false;
  }

  public function getExtraDataInputUrl($ruleConditionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/datachangedcomparison', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $originalComparisonValue = $this->getOriginalComparisonValue();
    $comparisonValue = $this->getComparisonValue();
    $options = $this->getFieldOptions();
    if (is_array($options)) {
      if (is_array($originalComparisonValue)) {
        foreach($originalComparisonValue as $idx => $val) {
          if (isset($options[$val])) {
            $originalComparisonValue[$idx] = $options[$val];
          }
        }
      } elseif (isset($options[$originalComparisonValue])) {
        $originalComparisonValue = $options[$originalComparisonValue];
      }

      if (is_array($comparisonValue)) {
        foreach($comparisonValue as $idx => $val) {
          if (isset($options[$val])) {
            $comparisonValue[$idx] = $options[$val];
          }
        }
      } elseif (isset($options[$comparisonValue])) {
        $comparisonValue = $options[$comparisonValue];
      }
    }


    if (is_array($originalComparisonValue)) {
      $originalComparisonValue = implode(", ", $originalComparisonValue);
    }
    if (is_array($comparisonValue)) {
      $comparisonValue = implode(", ", $comparisonValue);
    }
    return
      E::ts('Old value  ')
      . htmlentities(($this->getOriginalOperator())).' '.htmlentities($originalComparisonValue)
      . E::ts (' and new value ')
      . htmlentities(($this->getOperator())) . ' ' . htmlentities($comparisonValue);
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $options = $this->getFieldOptionsNames();
    $params = parent::exportConditionParameters();
    if (!is_array($options)) {
      return $params;
    }
    foreach(['value', 'multi_value', 'original_value', 'original_multi_value'] as $key) {
      if (isset($params[$key]) && is_array($params[$key])) {
        foreach ($params[$key] as $i => $j) {
          $params[$key][$i] = $options[$j];
        }
      } elseif (isset($params[$key])) {
        $params[$key] = $options[$params[$key]];
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
    $options = $this->getFieldOptionsNames();
    if (!is_array($options)) {
      return $condition_params;
    }
    $options = array_flip($options);
    foreach(['value', 'multi_value', 'original_value', 'original_multi_value'] as $key) {
      if (isset($condition_params[$key]) && is_array($condition_params[$key])) {
        foreach ($condition_params[$key] as $i => $j) {
          $condition_params[$key][$i] = $options[$j];
        }
      } elseif (isset($condition_params[$key])) {
        $condition_params[$key] = $options[$condition_params[$key]];
      }
    }
    return parent::importConditionParameters($condition_params);
  }

}
