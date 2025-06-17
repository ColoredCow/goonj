<?php

use CRM_Civirules_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_CivirulesConditions_Form_Activity_Subject extends CRM_CivirulesConditions_Form_Form {

  /**
   * Overridden parent method to build the form
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');
    $this->add('select', 'operator', ts('Operator'), ['contains' => E::ts('Contains the text'), 'exact_match' => E::ts('is an exact match to')], true);
    $this->add('textarea', 'text', ts('Text to match'), null, true);

    $this->addButtons([
      ['type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => ts('Cancel')],
    ]);

    parent::buildQuickForm();
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->ruleCondition->unserializeParams();
    if (!empty($data['text'])) {
      $defaultValues['text'] = $data['text'];
    }
    if (!empty($data['operator'])) {
      $defaultValues['operator'] = $data['operator'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to perform data processing once form is submitted
   */
  public function postProcess() {
    $data['operator'] = $this->_submitValues['operator'];
    $data['text'] = $this->_submitValues['text'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();

    parent::postProcess();
  }

}
