<?php

use CRM_Civirules_ExtensionUtil as E;

/**
 * Form Class for "Days since Last Case Activity" condition
 */
class CRM_CivirulesConditions_Form_Case_CaseActivity extends CRM_CivirulesConditions_Form_Form {

  protected function getCaseStatus() {
    return CRM_CivirulesConditions_Case_CaseStatus::getCaseStatus();
  }

  /**
   * Overridden parent method to build form
   *
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');
    $this->add('text', 'days_inactive', E::ts('Number of days'), [], TRUE);
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
    if (!empty($data['days_inactive'])) {
      $defaultValues['days_inactive'] = $data['days_inactive'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   */
  public function postProcess() {
    $data['days_inactive'] = $this->_submitValues['days_inactive'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();
    parent::postProcess();
  }

}
