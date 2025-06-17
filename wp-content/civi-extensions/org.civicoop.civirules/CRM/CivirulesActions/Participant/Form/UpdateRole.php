<?php
/**
 * @author Gerhard Weber (civiservice.de) <gerhard.weber@civiservice.de>
 * @license AGPL-3.0
 */

class CRM_CivirulesActions_Participant_Form_UpdateRole extends CRM_CivirulesActions_Form_Form {


  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');
    $this->add('select', 'role_id', ts('Role'), array('' => ts('-- please select --'))
      + CRM_Event_PseudoConstant::participantRole(), true);
    $this->addButtons(array(
      array('type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,),
      array('type' => 'cancel', 'name' => ts('Cancel'))));
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
    if (!empty($data['role_id'])) {
      $defaultValues['role_id'] = $data['role_id'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    $data['role_id'] = $this->_submitValues['role_id'];
    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }

}
