<?php
use CRM_Civirules_ExtensionUtil as E;

/**
 * Class for CiviRules Condition In Group Since Form
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 28 Sep 2021
 * @link https://lab.civicrm.org/extensions/civirules/-/issues/158
 * @license AGPL-3.0
 */

class CRM_CivirulesConditions_Form_GroupContact_InGroupSince extends CRM_CivirulesConditions_Form_Form {

  /**
   * Overridden parent method to build form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');
    $this->add('select', 'group_id', E::ts('Group'), CRM_Civirules_Utils::getGroupList(), TRUE, ['class' => 'crm-select2 civirules-group-id']);
    $operatorOptions = [
      'longer' => E::ts('longer than'),
      'shorter' => E::ts('shorter than')];
    $this->addRadio('operator', E::ts('longer/shorter than') . ': ', $operatorOptions,  [], " ", TRUE);
    $this->add('text',  'number', E::ts('number'));
    $this->addRule('number','Number has to be a whole number','numeric');
    $this->addRule('number','Number has to be a whole number','nopunctuation');
    $this->add('select', 'period', E::ts('Period'), CRM_Civirules_Utils::getPeriods(), TRUE, ['class' => 'crm-select2 civirules-periods']);

    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE,],
      ['type' => 'cancel', 'name' => E::ts('Cancel')]]);
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
    if (!empty($data['group_id'])) {
      $defaultValues['group_id'] = $data['group_id'];
    }
    if (!empty($data['number'])) {
      $defaultValues['number'] = $data['number'];
    }
    if (!empty($data['period'])) {
      $defaultValues['period'] = $data['period'];
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
    $data['group_id'] = $this->_submitValues['group_id'];
    $data['number'] = $this->_submitValues['number'];
    $data['period'] = $this->_submitValues['period'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();
    parent::postProcess();
  }

}
