<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Contribution_Form_UpdateStatus extends CRM_CivirulesActions_Form_Form {

  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');
    $statuses = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('value', 'label')
      ->addWhere('option_group_id:name', '=', 'contribution_status')
      ->execute()
      ->indexBy('value')
      ->column('label');

    $this->add('select', 'status_id', E::ts('Set Status to'), ['' => E::ts('-- please select --')] + $statuses, TRUE);
    $this->add('text', 'cancel_reason', E::ts('Set Cancel Reason To'));
    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE,],
      ['type' => 'cancel', 'name' => E::ts('Cancel')]
    ]);
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   * @access public
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = unserialize($this->ruleAction->action_params);
    $defaultValues['status_id'] = $data['status_id'] ?? NULL;
    $defaultValues['cancel_reason'] = $data['cancel_reason'] ?? NULL;
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    $data = [];
    $data['status_id'] = $this->getSubmittedValue('status_id');
    $data['cancel_reason'] = $this->getSubmittedValue('cancel_reason');
    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }

}
