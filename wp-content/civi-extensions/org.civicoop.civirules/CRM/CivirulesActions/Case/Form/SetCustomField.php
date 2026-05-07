<?php
/**
 * Class for CiviRules Group Contact Action Form
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Case_Form_SetCustomField extends CRM_CivirulesActions_Form_Form {

  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');

    $this->add('select',
        'field_id',
        E::ts('Target Field'),
        $this->getEligibleCustomFields(),
        TRUE);

    $this->add('text',
        'value',
        E::ts('Value (raw or json)'),
        [],
        FALSE);

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
        'field_id'  => $values['field_id'] ?? NULL,
        'value'     => $values['value'] ?? NULL,
    ];

    $this->ruleAction->action_params = serialize($configuration);
    $this->ruleAction->save();
    parent::postProcess();
  }

  /**
   * Get a list of all numeric contact custom fields
   *
   * @return array list of field IDs
   */
  protected function getEligibleCustomFields() {
    static $field_list = null;
    if ($field_list === null) {
      // find relevant groups
      $eligible_group_ids = [];
      $group_query = civicrm_api3('CustomGroup', 'get', [
          'extends'      => ['IN' => ['Case']],
          'is_active'    => 1,
          'option.limit' => 0,
          'return'       => 'id,title',
      ]);
      foreach ($group_query['values'] as $group) {
        $eligible_group_ids[$group['id']] = $group['title'];
      }

      // find eligible fields
      $field_query = civicrm_api3('CustomField', 'get', [
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

    return $field_list;
  }
}
