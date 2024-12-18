<?php
use CRM_Civirules_ExtensionUtil as E;

/**
 * Class for form processing cancel membership
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 27 Aug 2024
 * @license AGPL-3.0
 */

class CRM_CivirulesActions_Membership_Form_CancelLatestMembership extends CRM_CivirulesActions_Form_Form {

  /**
   * Overridden parent method to build form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');
    $this->add('select', 'membership_type_id', E::ts('Membership type:'), CRM_Civirules_Utils::getMembershipTypes(), TRUE, [
      'class' => 'crm-select2',
      'multiple' => TRUE,
      'placeholder' => '- select type(s) -'
    ]);
    $this->add('select', 'membership_status_id', E::ts('Membership currently has status:'), CRM_Civirules_Utils::getMembershipStatus(), TRUE, [
      'class' => 'crm-select2',
      'multiple' => TRUE,
      'placeholder' => '- select status(es) -'
    ]);
    $this->addButtons([
      ['type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,],
      ['type' => 'cancel', 'name' => ts('Cancel')],
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
    if ($this->ruleActionId) {
      $defaultValues['rule_action_id'] = $this->ruleActionId;
    }
    $data = unserialize($this->ruleAction->action_params);
    if (isset($data['membership_status_id'])) {
      $defaultValues['membership_status_id'] = $data['membership_status_id'];
    }
    if (isset($data['membership_type_id'])) {
      $defaultValues['membership_type_id'] = $data['membership_type_id'];
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
    $data['membership_status_id'] = $this->_submitValues['membership_status_id'];
    $data['membership_type_id'] = $this->_submitValues['membership_type_id'];
    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }

}
