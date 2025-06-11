<?php

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Emailapi_Form_CivirulesAction_SendToCustomFieldValue extends CRM_Emailapi_Form_CivirulesAction_Send {

  protected function getCustomFieldEntities() {
    return CRM_Emailapi_CivirulesAction_SendToCustomFieldValue::getCustomFieldEntities();
  }

  public function buildQuickForm() {
    parent::buildQuickForm();
    $this->add('select', 'entity', ts('Type of Entity'), $this->getCustomFieldEntities(), TRUE);
    $this->add('select', 'custom_value', ts('Custom Field'), CRM_Emailapi_CivirulesAction_SendToCustomFieldValue::_getCustomFieldsForEntity(), TRUE);
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   * @access public
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->unserializeParams();
    if (!empty($data['entity'])) {
      $defaultValues['entity'] = $data['entity'];
    }
    if (!empty($data['custom_value'])) {
      $defaultValues['custom_value'] = $data['custom_value'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   * @param array $data In theory, accepts additional data from child classes, but in practice it's just to match the parent class signature.
   */
  public function postProcess($data = []) {
    $data['entity'] = $this->_submitValues['entity'];
    $data['custom_value'] = $this->_submitValues['custom_value'];
    parent::postProcess($data);
  }

}
