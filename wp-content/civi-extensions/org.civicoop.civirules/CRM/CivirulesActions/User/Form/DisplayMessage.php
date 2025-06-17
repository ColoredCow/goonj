<?php
/**
 * Class for CiviRules Contribution Thank You Date Form
 *
 * @author John Kirk (CiviCooP) <john@civifirst.com>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_User_Form_DisplayMessage extends CRM_CivirulesActions_Form_Form {

  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');
    $messageTypes = ['alert' => 'Alert', 'info' => 'Info', 'success' => 'Success', 'error' => 'Error'];
    $this->add('text', 'title', E::ts('Message title:'));
    $this->add('text', 'message', E::ts('Message:'));
    $this->addRadio('type', E::ts('Type of message:'), $messageTypes);
    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')],
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
    $defaultValues['rule_action_id'] = $this->ruleActionId;
    $data = $this->ruleAction->unserializeParams();
    if (!empty($data['title'])) {
      $defaultValues['title'] = $data['title'];
    }
    if (!empty($data['message'])) {
      $defaultValues['message'] = $data['message'];
    }
    if (!empty($data['type'])) {
      $defaultValues['type'] = $data['type'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    $data['title'] = $this->_submitValues['title'];
    $data['message'] = $this->_submitValues['message'];
    $data['type'] = $this->_submitValues['type'];
    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }
}
