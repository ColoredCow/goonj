<?php

use CRM_Civirules_ExtensionUtil as E;

/**
 * Form Class for Action "Set the Status of a Case"
 */
class CRM_CivirulesActions_Case_Form_SetStatus extends CRM_CivirulesActions_Form_Form {

  /**
   * Build the form.
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');

    $this->add('select', 'status_id', ts('Set status to'),
      CRM_Case_BAO_Case::buildOptions('status_id'));

    $this->addButtons([
      [
        'type' => 'next',
        'name' => ts('Save'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ]
    ]);
  }


  /**
   * Set default values.
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->ruleAction->unserializeParams();
    $defaultValues['status_id'] = empty($data['status_id']) ? '' : $data['status_id'];
    return $defaultValues;
  }


  /**
   * Process form data after submitting
   */
  public function postProcess() {
    $data['status_id'] = $this->_submitValues['status_id'];
    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }

}
