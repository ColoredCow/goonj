<?php
/**
 * Form controller class
 */
class CRM_CivirulesConditions_Form_ContributionRecur_xthContribution extends CRM_CivirulesConditions_Form_Form {

  /**
   * Overridden parent method to build form
   *
   * @access public
   */
  public function buildQuickForm() {
    $operatorList[0] = 'equals (=)';
    $operatorList[1] = 'is not equal (!=)';
    $operatorList[2] = 'is more than (>)';
    $operatorList[3] = 'is more than or equal (>=)';
    $operatorList[4] = 'is less than (<)';
    $operatorList[5] = 'is less than or equal (<=)';

    $this->add('hidden', 'rule_condition_id');
    $this->add('select', 'operator', ts('Operator'), $operatorList, true);
    $this->add('text', 'no_of_recurring', ts('Number of the Contribution of the Recurring Contribution'), array(), true);
    $this->addRule('no_of_recurring', 'Number of the Contribution of the Recurring Contribution must be a whole number', 'numeric');
    $this->addRule('no_of_recurring', 'Number of the Contribution of the Recurring Contribution must be a whole number', 'nopunctuation');

    $this->addButtons(array(
      array('type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,),
      array('type' => 'cancel', 'name' => ts('Cancel'))));
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   * @access public
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->ruleCondition->unserializeParams();
    if (!empty($data['operator'])) {
      $defaultValues['operator'] = $data['operator'];
    }
    if (!empty($data['no_of_recurring'])) {
      $defaultValues['no_of_recurring'] = $data['no_of_recurring'];
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
    $data['operator'] = $this->_submitValues['operator'];
    $data['no_of_recurring'] = $this->_submitValues['no_of_recurring'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();

    parent::postProcess();
  }
}
