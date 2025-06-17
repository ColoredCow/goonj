<?php
/**
 * Form controller class
 */
class CRM_CivirulesConditions_Form_Contribution_IsPayLater extends CRM_CivirulesConditions_Form_Form {

  /**
   * Overridden parent method to build form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');

    $radioOptions = [
      'is pay later' => ts('is pay later'),
      'is not pay later' => ts('is not pay later'),
    ];
    $this->addRadio('test', ts('Contribution') . ': ', $radioOptions);

    $this->addButtons([
      ['type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE],
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
    if (!empty($data['test'])) {
      $defaultValues['test'] = $data['test'];
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
    $data['test'] = $this->_submitValues['test'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();

    parent::postProcess();
  }

}
