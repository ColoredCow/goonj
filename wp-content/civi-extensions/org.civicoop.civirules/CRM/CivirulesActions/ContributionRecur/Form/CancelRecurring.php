<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_ContributionRecur_Form_CancelRecurring extends CRM_CivirulesActions_Form_Form {


  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');
    $statuses = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('value', 'label')
      ->addWhere('option_group_id:name', '=', 'contribution_recur_status')
      ->execute()
      ->indexBy('value')
      ->column('label');

    $this->add('select', 'status_id', E::ts('Set Status to'), ['' => E::ts('-- please select --')] + $statuses, TRUE);
    $this->add('text', 'cancel_reason', ts('Cancel Reason'), '', TRUE);
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
    $data = $this->ruleAction->unserializeParams();
    if (!empty($data['status_id'])) {
      $defaultValues['status_id'] = $data['status_id'];
    }
    if (!empty($data['cancel_reason'])) {
      $defaultValues['cancel_reason'] = $data['cancel_reason'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    $data['status_id'] = $this->getSubmittedValue('status_id');
    $data['cancel_reason'] = $this->getSubmittedValue('cancel_reason');
    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }

}
