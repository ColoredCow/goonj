<?php
/**
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

/**
 * Form Class for Action: "Add Membership"
 */
class CRM_CivirulesActions_Membership_Form_Add extends CRM_CivirulesActions_Form_Form {

  /**
   * Overridden parent method to build the form
   *
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');

    $membershipTypeOptions =
      ['' => E::ts('-- please select --')] +
      CRM_Civirules_Utils::getMembershipTypes(TRUE);

    $this->add('select', 'membership_type_id', E::ts('Membership type'), $membershipTypeOptions, TRUE, [
      'class' => 'select2 huge',
    ]);

    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE,],
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
    if (!empty($data['membership_type_id'])) {
      $defaultValues['membership_type_id'] = $data['membership_type_id'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   */
  public function postProcess() {
    $data['membership_type_id'] = $this->_submitValues['membership_type_id'];
    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }

}
