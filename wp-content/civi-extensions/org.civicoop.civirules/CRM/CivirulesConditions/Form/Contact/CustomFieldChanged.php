<?php


class CRM_CivirulesConditions_Form_Contact_CustomFieldChanged extends CRM_CivirulesConditions_Form_Form {

  /**
   * Method to get case custom fields
   *
   * @return array
   */
  protected function getCustomFields() {
    $query = "SELECT cg.title, cf.id, cf.label
        FROM civicrm_custom_group AS cg
        JOIN civicrm_custom_field AS cf ON cg.id = cf.custom_group_id
        WHERE (cg.extends = 'Contact' OR cg.extends = 'Individual' OR cg.extends = 'Household' OR cg.extends = 'Organization')
          AND cg.is_active = '1' AND cf.is_active = '1'";
    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      $result[$dao->id] = "[" . $dao->title . "]: " . $dao->label;
    }
    asort($result);
    return $result;
  }

  /**
   * Overridden parent method to build form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');
    $this->add('select', 'custom_field_id', ts('Changed Custom Field is one of:'), $this->getCustomFields(), TRUE,
      ['id' => 'custom_field_ids', 'multiple' => 'multiple',  'class' => 'crm-select2']);
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
    $data = $this->ruleCondition->unserializeParams();
    if (!empty($data['custom_field_id'])) {
      $defaultValues['custom_field_id'] = $data['custom_field_id'];
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
    $data['custom_field_id'] = $this->_submitValues['custom_field_id'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();
    parent::postProcess();
  }
}
