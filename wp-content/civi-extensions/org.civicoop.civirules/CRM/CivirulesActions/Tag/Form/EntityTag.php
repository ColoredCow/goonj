<?php
use CRM_Civirules_ExtensionUtil as E;
/**
 * Class for CiviRules Add Tag to Entity Action Form
 *
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 11 May 2021
 * @link https://lab.civicrm.org/extensions/civirules/-/issues/116
 * @license AGPL-3.0
 */

class CRM_CivirulesActions_Tag_Form_EntityTag extends CRM_CivirulesActions_Form_Form {

  /**
   * Method to get entity tags
   *
   * @return array
   * @access protected
   */
  protected function getEntityTags($tableName) {
    $tags = CRM_CivirulesActions_Tag_EntityTag::getApi4Tags($tableName);
    return $tags;
  }

  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    // get table name from request
    $tableName = CRM_Utils_Request::retrieveValue('tn', "String");
    // civicrm_contact is default
    if (empty($tableName)) {
      $tableName = "civicrm_contact";
    }
    $this->add('hidden', 'rule_action_id');
    $this->add('select', 'tag_id', E::ts('Select Tag(s)'), $this->getEntityTags($tableName), TRUE, [
      'class' => 'crm-select2',
      'multiple' => TRUE,
      'placeholder' => '--- select tag(s) ---',
    ]);
    $this->addButtons([
      ['type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,],
      ['type' => 'cancel', 'name' => ts('Cancel')]
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
    if ($this->ruleActionId) {
      $defaultValues['rule_action_id'] = $this->ruleActionId;
    }
    $data = $this->ruleAction->unserializeParams();
    if (!empty($data['tag_id'])) {
      $defaultValues['tag_id'] = $data['tag_id'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    if (isset($this->_submitValues['tag_id'])) {
      $data['tag_id'] = $this->_submitValues['tag_id'];
      $this->ruleAction->action_params = serialize($data);
      $this->ruleAction->save();
    }
    parent::postProcess();
  }

}
