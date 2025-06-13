<?php
use CRM_Civirules_ExtensionUtil as E;
/**
 * Class for CiviRules Condition parameters form - entity has tag
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_CivirulesConditions_Form_EntityTag_HasTag extends CRM_CivirulesConditions_Form_Form {

  /**
   * Overridden parent method to build form
   */
  public function buildQuickForm() {
    $tableName = CRM_Utils_Request::retrieveValue('tn', "String");
    if (!$tableName) {
      $tableName = "civicrm_contact";
    }
    $genericTag = new CRM_CivirulesConditions_Generic_HasTag();
    $genericTag->setEntityTable($tableName);
    $this->add('hidden', 'rule_condition_id');
    $this->add('select', 'operator', ts('Operator'), $genericTag->getOperatorOptions(), TRUE);
       $this->add('select', 'tag_ids', E::ts('Select Tag(s)'), $genericTag->getEntityTags(), TRUE, [
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
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->ruleCondition->unserializeParams();
    if (!empty($data['tag_ids'])) {
      $defaultValues['tag_ids'] = $data['tag_ids'];
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
   */
  public function postProcess() {
    $data['tag_ids'] = $this->_submitValues['tag_ids'];
    $data['operator'] = $this->_submitValues['operator'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();

    parent::postProcess();
  }

}
