<?php
/**
 * Class for CiviRules Condition Contribution Financial Type Form
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_CivirulesConditions_Form_Contact_InDomain extends CRM_CivirulesConditions_Form_Form {

  /**
   * Method to get groups
   *
   * @return array
   */
  protected function getDomains() {
    return CRM_CivirulesConditions_Contact_InDomain::domains();
  }

  /**
   * Method to get operators
   *
   * @return array
   */
  protected function getOperators() {
    return CRM_CivirulesConditions_Contact_InDomain::getOperatorOptions();
  }

  /**
   * Overridden parent method to build form
   *
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');

    $this->add('select', 'domain_id', ts('Domain'), $this->getDomains(), true);
    $this->add('select', 'operator', ts('Operator'), $this->getOperators(), true);

    $this->addButtons([
      ['type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,],
      ['type' => 'cancel', 'name' => ts('Cancel')]
    ]);
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->ruleCondition->unserializeParams();
    if (!empty($data['domain_id'])) {
      $defaultValues['domain_id'] = $data['domain_id'];
    }
    if (!empty($data['operator'])) {
      $defaultValues['operator'] = $data['operator'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   */
  public function postProcess() {
    $data['domain_id'] = $this->_submitValues['domain_id'];
    $data['operator'] = $this->_submitValues['operator'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();

    parent::postProcess();
  }
}
