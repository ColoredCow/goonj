<?php
/**
 * Class for CiviRules Group Contact Action Form
 *
 * @author David Hayes (Black Brick Software) <david@blackbrick.software>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Contact_Form_UpdateDateValue extends CRM_CivirulesActions_Form_Form {

  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');

    $this->add('select',
        'source_field_id',
        E::ts('Source Field'),
        $this->getEligibleCustomFields(true),
        TRUE);

    $this->add('select',
        'target_field_id',
        E::ts('Target Field'),
        $this->getEligibleCustomFields(false),
        TRUE);

    $this->add('select',
        'update_operation',
        E::ts('Operation'),
        $this->getUpdateOperations(),
        TRUE);

    $this->add('text',
        'update_operand',
        ts('Operand'));

    // set defaults
    $this->setDefaults($this->ruleAction->unserializeParams());

    $this->addButtons(array(
      array('type' => 'next',   'name' => E::ts('Save'), 'isDefault' => TRUE,),
      array('type' => 'cancel', 'name' => E::ts('Cancel'))));
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    $values = $this->exportValues();
    $configuration = [
        'source_field_id'  => $values['source_field_id'] ?? NULL,
        'target_field_id'  => $values['target_field_id'] ?? NULL,
        'update_operation' => $values['update_operation'] ?? NULL,
        'update_operand'   => CRM_Utils_Array::value('update_operand', $values, 0),
    ];

    $this->ruleAction->action_params = serialize($configuration);
    $this->ruleAction->save();
    parent::postProcess();
  }


  /**
   * Get the list of field update operations
   *
   * @return array list of options
   */
  protected function getUpdateOperations() {
    return [
        'set'         => E::ts("Set to"),
        'modify'      => E::ts("Modify"),
        'max_modify'    => E::ts("Set to (global) maximum with modification"),
        'min_modify'    => E::ts("Set to (global) minimum with modification"),
    ];
  }

  /**
   * Get a list of all numeric contact custom fields
   *
   * @return array list of field IDs
   */
  protected function getEligibleCustomFields() {
    static $field_list = null;
    if ($field_list === null) {
      $field_list = [];

      // find relevant groups
      $eligible_group_ids = [];
      $group_query = civicrm_api3('CustomGroup', 'get', [
          'extends'      => ['IN' => ['Contact', 'Individual', 'Organization', 'Household']],
          'is_active'    => 1,
          'option.limit' => 0,
          'return'       => 'id,title',
      ]);
      foreach ($group_query['values'] as $group) {
        $eligible_group_ids[$group['id']] = $group['title'];
      }

      // find eligible fields
      $field_query = civicrm_api3('CustomField', 'get', [
          'data_type'       => ['IN' => ['Date']],
          'custom_group_id' => ['IN' => array_keys($eligible_group_ids)],
          'is_active'       => 1,
          'option.limit'    => 0,
          'return'          => 'id,label,custom_group_id',
      ]);
      foreach ($field_query['values'] as $field) {
        $field_list[$field['id']] = E::ts("Field '%1' (Group '%2')", [
            1 => $field['label'],
            2 => $eligible_group_ids[$field['custom_group_id']]]);
      }
    }

    $result = $field_list;
    return $result;
  }
}