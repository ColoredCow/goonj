<?php
/**
 * Class for CiviRules Copy Custom Field Form
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Generic_Form_CopyCustomField extends CRM_CivirulesActions_Form_Form {

  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');

    $this->add('select2',
      'copy_from_field_id',
      E::ts('Source Field'),
      $this->getEligibleCustomFields(),
      TRUE);

    $this->add('select2',
      'field_id',
      E::ts('Target Field'),
      $this->getEligibleCustomFields(),
      TRUE);

    // set defaults
    $this->setDefaults($this->ruleAction->unserializeParams());

    $this->addButtons([
      ['type' => 'next',   'name' => E::ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')],
    ]);

  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    $data['field_id'] = $this->getSubmittedValue('field_id');
    $data['copy_from_field_id'] = $this->getSubmittedValue('copy_from_field_id');
    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }

  /**
   * Get a list of all numeric contact custom fields
   *
   * @return array list of field IDs
   */
  protected function getEligibleCustomFields() {
    static $field_list = NULL;
    if ($field_list === NULL) {
      foreach ($this->triggerClass->getProvidedEntities() as $entityDef) {
        $entity = $entityDef->entity;
        if ($entity == 'Contact') {
          $entity = ['Contact', 'Individual', 'Organization', 'Household'];
        }
        $eligible_group_ids = [];
        $group_query = civicrm_api3('CustomGroup', 'get', [
          'extends' => ['IN' => (array) $entity],
          'is_active' => 1,
          'option.limit' => 0,
          'return' => 'id,title,extends',
        ]);
        foreach ($group_query['values'] as $group) {
          $eligible_group_ids[$group['id']] = $group['title'];
        }

        // find eligible fields
        if (!$eligible_group_ids) {
          continue;
        }
        $field_query = civicrm_api3('CustomField', 'get', [
          'custom_group_id' => ['IN' => array_keys($eligible_group_ids)],
          'is_active' => 1,
          'option.limit' => 0,
          'return' => 'id,label,custom_group_id',
        ]);
        foreach ($field_query['values'] as $field) {
          $field_list[$field['id']] = ['id' => $field['id'], 'label' => E::ts("Field '%1' (Entity '%3', Group '%2')", [
            1 => $field['label'],
            2 => $eligible_group_ids[$field['custom_group_id']],
            3 => $group_query['values'][$field['custom_group_id']]['extends'],
           ])
          ];
        }
      }
    }
    return $field_list;
  }

}
