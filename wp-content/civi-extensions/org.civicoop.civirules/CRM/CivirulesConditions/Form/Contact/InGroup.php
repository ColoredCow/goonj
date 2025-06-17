<?php
/**
 * Class for CiviRules Condition Contribution Financial Type Form
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */
use CRM_Civirules_ExtensionUtil as E;
class CRM_CivirulesConditions_Form_Contact_InGroup extends CRM_CivirulesConditions_Form_Form {

  /**
   * Method to get operators
   *
   * @return array
   */
  protected function getOperators() {
    return CRM_CivirulesConditions_Contact_InGroup::getOperatorOptions();
  }

  /**
   * Overridden parent method to build form
   *
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');
    $group = $this->add('select', 'group_ids', ts('Groups'), CRM_Civirules_Utils::getGroupList(), TRUE);
    $group->setMultiple(TRUE);
    $this->add('select', 'operator', ts('Operator'), $this->getOperators(), TRUE);
    $this->addYesNo('check_group_tree', ts('Check Group Tree?'), FALSE, TRUE);
    $status = $this->add('select', 'statuses', E::ts('Status'), [
      'Added' => E::ts('Added'),
      'Pending' => E::ts('Pending'),
      'Removed' => E::ts('Removed'),
    ], TRUE);
    $status->setMultiple(TRUE);

    $this->addButtons([
      ['type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => ts('Cancel')],
    ]);
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->ruleCondition->unserializeParams();
    if (!empty($data['group_ids'])) {
      $defaultValues['group_ids'] = $data['group_ids'];
    }
    if (!empty($data['operator'])) {
      $defaultValues['operator'] = $data['operator'];
    }
    if (!empty($data['statuses'])) {
      $defaultValues['statuses'] = $data['statuses'];
    }
    if (isset($data['check_group_tree'])) {
      $defaultValues['check_group_tree'] = $data['check_group_tree'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   */
  public function postProcess() {
    $data['group_ids'] = $this->_submitValues['group_ids'];
    $data['operator'] = $this->_submitValues['operator'];
    $data['check_group_tree'] = $this->_submitValues['check_group_tree'];
    $data['statuses'] = $this->getSubmittedValue('statuses');
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();

    parent::postProcess();
  }

}
