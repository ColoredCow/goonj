<?php

use CRM_Emailapi_ExtensionUtil as E;

/**
 * Extends CRM_Emailapi_Form_CivirulesAction_Send to allow sending to a related contact.
 *
 */
class CRM_Emailapi_Form_CivirulesAction_SendToRelatedContact extends CRM_Emailapi_Form_CivirulesAction_Send {

  protected function getRelationshipTypes() {
    return CRM_Emailapi_CivirulesAction_SendToRelatedContact::getRelationshipTypes();
  }

  protected function getRelatedOptions() {
    return CRM_Emailapi_CivirulesAction_SendToRelatedContact::getRelationshipOptions();
  }

  /**
   * @see CRM_Emailapi_From_CivirulesAction_Send::buildQuickForm()
   */
  function buildQuickForm() {
    parent::buildQuickForm();
    $this->add('select', 'relationship_type', E::ts('Relationship Type'), $this->getRelationshipTypes(), TRUE);
    $this->add('select', 'relationship_option', E::ts('Send email to'), $this->getRelatedOptions(), TRUE);
  }

  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->unserializeParams();
    if (!empty($data['relationship_type'])) {
      $defaultValues['relationship_type'] = $data['relationship_type'];
    }
    if (!empty($data['relationship_option'])) {
      $defaultValues['relationship_option'] = $data['relationship_option'];
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
    $data['relationship_option'] = $this->_submitValues['relationship_option'];
    parent::postProcess($data);
  }

}
