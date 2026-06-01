<?php
/**
 * Class for CiviRules Set Field Value Form
 *
 * @author Jon Goldberg <jon@megaphonetech.com>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Generic_Form_SetField extends CRM_CivirulesActions_Form_Form {

  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');

    $this->add('select', 'entity', ts('Entity'), $this->getEntityOptions(), TRUE, array('class' => 'crm-select2 huge'));

    $this->add('select',
        'field',
        E::ts('Target Field'),
        $this->getFields(),
        TRUE,
        ['class' => 'crm-select2 huge'],
      );

    $this->add('text',
        'value',
        E::ts('Value'),
        [],
        FALSE);

    // set defaults
    $defaults = $this->ruleAction->unserializeParams();
    $defaults['field'] = $defaults['entity'] . '_' . $defaults['field'];
    $this->setDefaults($defaults);

    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')],
    ]);
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    $values = $this->exportValues();
    $configuration = [
      'entity'    => $values['entity'],
      'field'  => str_replace($values['entity'] . '_', '', $values['field']),
      'value'     => $values['value'],
    ];

    $this->ruleAction->action_params = serialize($configuration);
    $this->ruleAction->save();
    parent::postProcess();
  }

  /**
   * Get a list of all fields for this entity.
   *
   * @return array list of field IDs
   */
  protected function getFields() {
    $return = [];
    foreach ($this->triggerClass->getProvidedEntities() as $entityDef) {
      $entity = $entityDef->entity;
      $fieldList = civicrm_api4($entity, 'getfields', [
        'where' => [
          ['readonly', '=', FALSE],
        ],
        'checkPermissions' => FALSE,
        'select' => [
          'label', 'name',
        ],
      ])->column('label', 'name');
      foreach ($fieldList as $key => $label) {
        $return[$entity . '_' . $key] = $label;
      }
    }

    return $return;
  }

  protected function getEntityOptions() {
    $return = array();
    foreach ($this->triggerClass->getProvidedEntities() as $entityDef) {
      if (!empty($entityDef->daoClass) && class_exists($entityDef->daoClass)) {
        $return[$entityDef->entity] = $entityDef->label;
      }
    }
    return $return;
  }
}
