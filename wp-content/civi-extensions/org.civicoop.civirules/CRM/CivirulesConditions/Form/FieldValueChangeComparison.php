<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesConditions_Form_FieldValueChangeComparison extends CRM_CivirulesConditions_Form_Form {

  /**
   * Overridden parent method to perform processing before form is build
   *
   * @access public
   */
  public function preProcess() {
    parent::preProcess();

    if (!$this->conditionClass instanceof CRM_CivirulesConditions_Generic_FieldValueChangeComparison) {
      throw new Exception("Not a valid value comparison class");
    }
  }

  /**
   * Function to add validation condition rules (overrides parent function)
   *
   * @access public
   */
  public function addRules() {
    $this->addFormRule([
      'CRM_CivirulesConditions_Form_ValueComparison',
      'validateOperatorAndComparisonValue',
    ]);
  }

  public static function validateOperatorAndComparisonValue($fields) {
    $errors = [];
    $operator = $fields['operator'];
    switch ($operator) {
      case '=':
      case '!=':
      case '>':
      case '>=':
      case '<':
      case '<=':
        if (empty($fields['value'])) {
          $errors['value'] = ts('Compare value is required');
        }
        break;
      case 'is one of':
      case 'is not one of':
      case 'contains one of':
      case 'not contains one of':
      case 'contains all of':
      case 'not contains all of':
        if (empty($fields['multi_value'])) {
          $errors['multi_value'] = ts('Compare values is a required field');
        }
        break;
    }

    $original_operator = $fields['original_operator'];
    switch ($original_operator) {
      case '=':
      case '!=':
      case '>':
      case '>=':
      case '<':
      case '<=':
        if (empty($fields['value'])) {
          $errors['original_value'] = ts('Compare value is required');
        }
        break;
      case 'is one of':
      case 'is not one of':
      case 'contains one of':
      case 'not contains one of':
      case 'contains all of':
      case 'not contains all of':
        if (empty($fields['multi_value'])) {
          $errors['original_multi_value'] = ts('Compare values is a required field');
        }
        break;
    }

    if (count($errors)) {
      return $errors;
    }
    return TRUE;
  }

  /**
   * Overridden parent method to build form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->setFormTitle();

    $this->add('hidden', 'rule_condition_id');

    $this->add('select', 'original_operator', ts('Operator'), $this->conditionClass->getOperators(), TRUE);
    $this->add('text', 'original_value', ts('Compare value'), NULL, TRUE);
    $this->add('textarea', 'original_multi_value', ts('Compare values'));

    $this->add('select', 'operator', ts('Operator'), $this->conditionClass->getOperators(), TRUE);
    $this->add('text', 'value', ts('Compare value'), NULL, TRUE);
    $this->add('textarea', 'multi_value', ts('Compare values'));

    $this->assign('field_options', $this->conditionClass->getFieldOptions());
    $this->assign('is_field_option_multiple', $this->conditionClass->isMultiple());

    $this->addButtons([
      ['type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,],
      ['type' => 'cancel', 'name' => ts('Cancel')],
    ]);
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   * @access public
   */
  public function setDefaultValues() {
    $data = [];
    $defaultValues = [];
    $defaultValues['rule_condition_id'] = $this->ruleConditionId;
    $ruleCondition = new CRM_Civirules_BAO_RuleCondition();
    $ruleCondition->id = $this->ruleConditionId;
    if ($ruleCondition->find(TRUE)) {
      $data = $ruleCondition->unserializeParams();
    }
    if (!empty($data['operator'])) {
      $defaultValues['operator'] = $data['operator'];
    }
    if (!empty($data['value'])) {
      $defaultValues['value'] = $data['value'];
    }
    if (!empty($data['multi_value'])) {
      $defaultValues['multi_value'] = implode("\r\n", $data['multi_value']);
    }

    if (!empty($data['original_operator'])) {
      $defaultValues['original_operator'] = $data['original_operator'];
    }
    if (!empty($data['original_value'])) {
      $defaultValues['original_value'] = $data['original_value'];
    }
    if (!empty($data['original_multi_value'])) {
      $defaultValues['original_multi_value'] = implode("\r\n", $data['original_multi_value']);
    }

    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   * @access public
   */
  public function postProcess() {
    $data = $this->ruleCondition->unserializeParams();
    $data['original_operator'] = $this->getSubmittedValue('original_operator');
    $data['original_value'] = $this->getSubmittedValue('original_value');
    $originalMultiValue = $this->getSubmittedValue('original_multi_value');
    if (!empty($originalMultiValue)) {
      $originalMultiValue = str_replace('\r\n', '\n', $originalMultiValue);
      $data['original_multi_value'] = explode("\n", $originalMultiValue);
    }

    $data['operator'] = $this->getSubmittedValue('operator');
    $data['value'] = $this->getSubmittedValue('value');
    $newMultiValue = $this->getSubmittedValue('multi_value');
    if (!empty($newMultiValue)) {
      $newMultiValue = str_replace('\r\n', '\n', $newMultiValue);
      $data['multi_value'] = explode("\n", $newMultiValue);
    }

    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();

    parent::postProcess();
  }

  /**
   * Returns a help text for this condition.
   * The help text is shown to the administrator who is configuring the
   * condition.
   *
   * @return string
   */
  public function getHelpText() {
    return E::ts('This condition checks the before and after value of a field. It also works with delayed actions that
      re-check conditions because the original values are saved when it is triggered.');
  }

}
