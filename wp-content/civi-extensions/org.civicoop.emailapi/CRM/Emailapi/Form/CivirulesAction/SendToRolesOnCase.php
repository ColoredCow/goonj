<?php

use CRM_Emailapi_ExtensionUtil as E;
/**
 * Extends CRM_Emailapi_Form_CivirulesAction_Send to allow sending to a related contact.
 *
 */
class CRM_Emailapi_Form_CivirulesAction_SendToRolesOnCase extends CRM_Emailapi_Form_CivirulesAction_Send {

  protected function getRelationshipTypes() {
    return ['' => E::ts('All people with a role on the case')] + CRM_Emailapi_CivirulesAction_SendToRelatedContact::getRelationshipTypes('a_b');
  }

  /**
   * @see CRM_Emailapi_From_CivirulesAction_Send::buildQuickForm()
   */
  function buildQuickForm() {
    parent::buildQuickForm();
    $this->add('select', 'relationship_type', E::ts('Restrict to Roles'), $this->getRelationshipTypes());
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
