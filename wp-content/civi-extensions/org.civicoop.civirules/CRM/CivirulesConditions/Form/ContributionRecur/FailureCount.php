<?php
/**
 * Class for CiviRules Condition Contribution Recur Failure Count
 *
 * @author Shane Bill (SymbioTIC Coop) <shane@symbiotic.coop>
 * @license AGPL-3.0
 */
use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesConditions_Form_ContributionRecur_FailureCount extends CRM_CivirulesConditions_Form_Form {

  /**
   * Overridden parent method to build form
   *
   * @access public
   */
  public function buildQuickForm() {
    $operatorList[0] = E::ts('equals (=)');
    $operatorList[1] = E::ts('is not equal (!=)');
    $operatorList[2] = E::ts('is more than (>)');
    $operatorList[3] = E::ts('is more than or equal (>=)');
    $operatorList[4] = E::ts('is less than (<)');
    $operatorList[5] = E::ts('is less than or equal (<=)');

    $this->add('hidden', 'rule_condition_id');
    $this->add('select', 'operator', E::ts('Operator'), $operatorList, true);
    $this->add('text', 'failure_count', E::ts('Number of Failed Attempts'), array(), true);
    $this->addRule('failure_count','Number of Recurring Contribution Collections must be a whole number','numeric');
    $this->addRule('failure_count','Number of Recurring Contribution Collections must be a whole number','nopunctuation');

    $this->addButtons(array(
      array('type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE,),
      array('type' => 'cancel', 'name' => E::ts('Cancel'))));
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
    if (!empty($data['failure_count'])) {
      $defaultValues['failure_count'] = $data['failure_count'];
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
    $data['failure_count'] = $this->_submitValues['failure_count'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();

    parent::postProcess();
  }
}
