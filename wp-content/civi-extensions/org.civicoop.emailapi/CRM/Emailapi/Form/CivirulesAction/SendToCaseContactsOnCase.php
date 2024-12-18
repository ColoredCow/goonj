<?php

use CRM_Emailapi_ExtensionUtil as E;
/**
 * Extends CRM_Emailapi_Form_CivirulesAction_Send to allow sending to a case contacts.
 *
 */
class CRM_Emailapi_Form_CivirulesAction_SendToCaseContactsOnCase extends CRM_Emailapi_Form_CivirulesAction_Send {

  /**
   * @see CRM_Emailapi_From_CivirulesAction_Send::buildQuickForm()
   */
  function buildQuickForm() {
    parent::buildQuickForm();
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   * @access public
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    if (!empty($this->ruleAction->action_params)) {
      $data = unserialize($this->ruleAction->action_params);
    }
    if (!empty($data['relationship_type'])) {
      $defaultValues['relationship_type'] = $data['relationship_type'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess($data = []) {
    $data['relationship_type'] = $this->_submitValues['relationship_type'];
    parent::postProcess($data);
  }

}
