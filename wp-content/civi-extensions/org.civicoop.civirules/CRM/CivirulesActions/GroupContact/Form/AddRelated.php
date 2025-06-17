<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_GroupContact_Form_AddRelated extends CRM_CivirulesActions_Form_Form {

  /**
   * Overridden parent method to build the form
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');
    $this->add('select', 'rel_type_ids', E::ts('Related contacts'), CRM_Civirules_Utils::getRelationshipTypes(), TRUE, [
      'multiple' => 'multiple',
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('--- select relationship type(s) ---'),
    ]);
    $this->add('select', 'group_id', E::ts('Group'), ['' => E::ts('-- please select --')] + CRM_Civirules_Utils::getGroupList(), TRUE);
    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')]
    ]);
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->ruleAction->unserializeParams();
    if (!empty($data['rel_type_ids'])) {
      $defaultValues['rel_type_ids'] = $data['rel_type_ids'];
    }
    if (!empty($data['group_id'])) {
      $defaultValues['group_id'] = $data['group_id'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   */
  public function postProcess() {
    $data['rel_type_ids'] = $this->getSubmittedValue('rel_type_ids');
    $data['group_id'] = $this->getSubmittedValue('group_id');
    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }

}
