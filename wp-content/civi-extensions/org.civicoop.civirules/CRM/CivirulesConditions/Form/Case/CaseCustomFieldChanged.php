<?php


class CRM_CivirulesConditions_Form_Case_CaseCustomFieldChanged extends CRM_CivirulesConditions_Form_Form {

  /**
   * Method to get case custom fields
   *
   * @return array
   */
  protected function getCaseCustomFields() {
    $query = "SELECT cg.title, cf.id, cf.label
        FROM civicrm_custom_group AS cg
        JOIN civicrm_custom_field AS cf ON cg.id = cf.custom_group_id
        WHERE cg.extends = %1 AND cg.is_active = %2 AND cf.is_active = %2";
    $dao = CRM_Core_DAO::executeQuery($query, [
      1 => ["Case", "String"],
      2 => [1, "Integer"],
    ]);
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
    $this->add('select', 'case_custom_field_id', ts('Changed Custom Field is one of:'), $this->getCaseCustomFields(), TRUE,
      ['id' => 'case_custom_field_ids', 'multiple' => 'multiple',  'class' => 'crm-select2']);
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
    if (!empty($data['case_custom_field_id'])) {
      $defaultValues['case_custom_field_id'] = $data['case_custom_field_id'];
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
    $data['case_custom_field_id'] = $this->_submitValues['case_custom_field_id'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();
    parent::postProcess();
  }
}
