<?php
/**
 * @author Jaap Jansma <jaap.jansma@civicoop.org>, Sebastian Lisken <sebastian.lisken@civiservice.de>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

/**
 * Form Class for Action: "Update Membership"
 */

class CRM_CivirulesActions_Membership_Form_UpdateStatus extends CRM_CivirulesActions_Form_Form {

  /**
   * Overridden parent method to build the form
   *
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');

    $membershipStatusOptions =
      ['' => E::ts('-- please select --')] +
      CRM_Civirules_Utils::getMembershipStatus(FALSE);

    $this->add('select', 'membership_status_id', E::ts('Membership Status'), $membershipStatusOptions, TRUE, [
      'class' => 'select2 huge',
    ]);

    $options = [
      'set_true' => E::ts('set to override permanently'),
      'set_false' => E::ts('set to not override'),
      'dont_set' => E::ts('do not change setting'),
    ];
    $this->addRadio('set_is_override', ts('Status Override?'), $options, [], NULL, TRUE);

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
    if (!empty($data['membership_status_id'])) {
      $defaultValues['membership_status_id'] = $data['membership_status_id'];
    }
    if (!empty($data['set_is_override'])) {
        $defaultValues['set_is_override'] = $data['set_is_override'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   */
  public function postProcess() {
    $data['membership_status_id'] = $this->_submitValues['membership_status_id'];
    $data['set_is_override'] = $this->_submitValues['set_is_override'];
    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }

}
