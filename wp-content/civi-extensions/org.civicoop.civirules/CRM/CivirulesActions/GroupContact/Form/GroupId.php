<?php
/**
 * Class for CiviRules Group Contact Action Form
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_CivirulesActions_GroupContact_Form_GroupId extends CRM_CivirulesActions_Form_Form {

  /**
   * Overridden parent method to build the form
   *
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');

    $this->add('select', 'type', ts('Single/Multiple'), [
      0 => ts('Select a single a group'),
      1 => ts('Select multiple groups'),
    ]);

    $this->add('select', 'group_id', ts('Group'), ['' => ts('-- please select --')] + CRM_Civirules_Utils::getGroupList());

    $multiGroup = $this->addElement('advmultiselect', 'group_ids', ts('Groups'), CRM_Civirules_Utils::getGroupList(), [
      'size' => 5,
      'style' => 'width:250px',
      'class' => 'advmultiselect',
    ]);

    $multiGroup->setButtonAttributes('add', ['value' => ts('Add >>')]);
    $multiGroup->setButtonAttributes('remove', ['value' => ts('<< Remove')]);

    $this->addButtons([
      ['type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => ts('Cancel')]
    ]);
  }

  /**
   * @return void
   */
  public function addRules() {
    $this->addFormRule(['CRM_CivirulesActions_GroupContact_Form_GroupId', 'validateGroupFields']);
  }

  /**
   * Function to validate value of rule action form
   *
   * @param array $fields
   *
   * @return array|bool
   */
  static function validateGroupFields($fields) {
    $errors = [];
    if ($fields['type'] == 0 && empty($fields['group_id'])) {
      $errors['group_id'] = ts('You have to select at least one group');
    } elseif ($fields['type'] == 1 && (empty($fields['group_ids']) || count($fields['group_ids']) < 1)) {
      $errors['group_ids'] = ts('You have to select at least one group');
    }

    if (count($errors)) {
      return $errors;
    }
    return TRUE;
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->ruleAction->unserializeParams();
    if (!empty($data['group_id'])) {
      $defaultValues['group_id'] = $data['group_id'];
    }
    if (!empty($data['group_ids'])) {
      $defaultValues['group_ids'] = $data['group_ids'];
    }
    if (!empty($data['group_ids']) && is_array($data['group_ids'])) {
      $defaultValues['type'] = 1;
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   */
  public function postProcess() {
    $data['group_id'] = FALSE;
    $data['group_ids'] = FALSE;
    if ($this->_submitValues['type'] == 0) {
      $data['group_id'] = $this->_submitValues['group_id'];
    } else {
      $data['group_ids'] = $this->_submitValues['group_ids'];
    }

    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }

}
