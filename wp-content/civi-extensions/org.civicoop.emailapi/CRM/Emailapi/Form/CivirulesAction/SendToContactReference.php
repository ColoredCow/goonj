<?php

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Emailapi_Form_CivirulesAction_SendToContactReference extends CRM_Emailapi_Form_CivirulesAction_Send {

  /**
   * Get a list of entities that use custom fields.
   *
   * @return array
   */
  public function getContactReferenceEntities() {

    $entities = [];
    foreach ($this->triggerClass->getProvidedEntities() as $entityDef) {
      $entity = $entityDef->entity;
      if ($entity == 'Contact') {
        $entities += ['Contact', 'Individual', 'Organization', 'Household'];
      } else {
        $entities[] = $entity;
      }
    }
    $return[] = '-- please select --';
    $result = \Civi\Api4\CustomField::get(TRUE)
      ->addSelect('custom_group_id.extends')
      ->addWhere('custom_group_id.extends', 'IN', $entities)
      ->addClause('OR', ['data_type', '=', 'ContactReference'], ['AND', [['data_type', '=', 'EntityReference'], ['fk_entity', '=', 'Contact']]])
      ->execute()
      ->indexBy('custom_group_id.extends');
    foreach ($result as $field) {
      $return[$field['custom_group_id.extends']] = $field['custom_group_id.extends'];
    }
    $return = array_unique($return);
    asort($return);
    return $return;
  }

  public function buildQuickForm() {
    parent::buildQuickForm();
    $this->add('select', 'entity', ts('Type of Entity'), $this->getContactReferenceEntities(), TRUE);
    $this->addEntityRef('contact_reference', ts('Contact Reference Field'), [
      'entity' => 'CustomField',
      'placeholder' => ts('-- select --'),
      'multiple' => true,
      'select' => ['minimumInputLength' => 0],
      'api' => [
        'params' => [
          'data_type' => "Contact Reference",
          'fk_entity' => "Contact",
          'options' => ['or' => [["data_type", "fk_entity"]]],
        ],
      ],
    ], TRUE);
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   * @access public
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->unserializeParams();
    if (!empty($data['entity'])) {
      $defaultValues['entity'] = $data['entity'];
    }
    if (!empty($data['contact_reference'])) {
      if (!is_array($data['contact_reference'])) {
        $data['contact_reference'] = explode(",", $data['contact_reference']);
      }
      $defaultValues['contact_reference'] = $data['contact_reference'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   * @param array $data In theory, accepts additional data from child classes, but in practice it's just to match the parent class signature.
   */
  public function postProcess($data = []) {
    $data['entity'] = $this->_submitValues['entity'];
    $data['contact_reference'] = $this->_submitValues['contact_reference'];
    parent::postProcess($data);
  }



}
