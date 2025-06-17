<?php
/**
 * Form controller class
 */
class CRM_CivirulesConditions_Form_Contact_HasType extends CRM_CivirulesConditions_Form_Form {

  /**
   * Overridden parent method to build form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');
    $this->add('select', 'type_names', ts('Type(s)'), CRM_Civirules_Utils::getContactTypes(), true,
      array('id' => 'contact_type_ids', 'multiple' => 'multiple','class' => 'crm-select2'));
    $this->add('select', 'operator', ts('Operator'), CRM_CivirulesConditions_Contact_HasType::getOperatorOptions(), true);

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
    $data = $this->ruleCondition->unserializeParams();
    if (!empty($data['type_names'])) {
      $defaultValues['type_names'] = $data['type_names'];
    }
    if (!empty($data['operator'])) {
      $defaultValues['operator'] = $data['operator'];
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
    $data['type_names'] = $this->_submitValues['type_names'];
    $data['operator'] = $this->_submitValues['operator'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();

    parent::postProcess();
  }

}
