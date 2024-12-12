<?php
/**
 * Class for CiviRules Condition Status Form
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license AGPL-3.0
 */
use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesConditions_Form_Status extends CRM_CivirulesConditions_Form_Form {

  /**
   * @var string the entity name
   */
  protected $ruleConditionEntity;

  /**
   * @var string the name of the field for the options
   */
  protected $ruleConditionField;

  /**
   * Get the name of the status field for the entity
   *
   * @param string $entity
   *
   * @return string
   * @throws \Exception
   */
  protected function getStatusFieldForEntity($entity) {
    $className = "CRM_CivirulesConditions_{$entity}_Status";
    if (class_exists($className)) {
      /** @var \CRM_CivirulesConditions_Generic_Status $entityCondition */
      $entityCondition = new $className();
      return $entityCondition->getEntityStatusFieldName();
    }
    Throw new Exception("Entity {$entity} does not implement status condition");
  }

  /**
   * Overridden parent method to build form
   */
  public function buildQuickForm() {
    $this->ruleConditionEntity = CRM_Utils_Request::retrieveValue('entity', 'String', NULL, TRUE);
    $this->add('hidden', 'rule_condition_id');
    $this->add('hidden', 'entity');

    $className = "CRM_CivirulesConditions_{$this->ruleConditionEntity}_Status";
    if (class_exists($className)) {
      $label = "{$this->ruleConditionEntity} status";
      $options = $className::getEntityStatusList(TRUE);
    }
    else {
      \Civi::log()->error("CiviRules CRM_CivirulesConditions_Form_Status called but entity {$this->ruleConditionEntity} is not supported");
    }

    $this->add('select', 'status_id', $label, $options, TRUE, [
      'multiple' => TRUE,
      'class' => 'crm-select2'
    ]);
    $this->add('select', 'operator', ts('Operator'), [0 => ts('is one of'), 1 => ts('is NOT one of')], TRUE);

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
    $defaultValues['entity'] = $this->ruleConditionEntity;
    $data = !empty($this->ruleCondition->condition_params) ? unserialize($this->ruleCondition->condition_params) : [];

    // Old versions may have stored the condition param as eg membership_status_id instead of the generic status_id.
    // Also may not have been array but a single value, so convert here.
    if (isset($data[$this->getStatusFieldForEntity($this->ruleConditionEntity)])) {
      $data['status_id'] = $data[$this->getStatusFieldForEntity($this->ruleConditionEntity)];
    }
    $data['status_id'] = $data['status_id'] ?? NULL;
    if (!is_array($data['status_id'])) {
      $data['status_id'] = [$data['status_id']];
    }

    $defaultValues['status_id'] = $data['status_id'];

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
    $this->ruleConditionEntity = $this->_submitValues['entity'];
    $data['status_id'] = $this->_submitValues['status_id'];
    $data['operator'] = $this->_submitValues['operator'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();
    parent::postProcess();
  }

  /**
   * Returns a help text for this condition.
   * The help text is shown to the administrator who is configuring the condition.
   *
   * @return string
   */
  protected function getHelpText() {
    switch ($this->ruleConditionName) {
      case 'contact_has_recurring':
        return E::ts('This condition checks if the contact has any recurring contributions that are (not) in one of the statuses selected.');

    }
    return E::ts('This condition checks the status of the entity is (not) one of the selected status values.');
  }

}
