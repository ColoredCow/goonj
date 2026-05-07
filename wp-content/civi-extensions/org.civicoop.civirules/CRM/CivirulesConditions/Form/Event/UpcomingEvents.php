<?php
/**
 * Class for CiviRules Condition Event Type Form
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesConditions_Form_Event_UpcomingEvents extends CRM_CivirulesConditions_Form_Form {

  protected function getEventTypes() {
    $eventTypeList = civicrm_api3('OptionValue', 'get', array('option_group_id' => "event_type", 'options' => ['limit' => 0]));
    $eventTypes = array();
    foreach($eventTypeList['values'] as $eventType) {
      $eventTypes[$eventType['value']] = $eventType['label'];
    }
    return $eventTypes;
  }

  /**
   * Overridden parent method to build form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');

    $eventTypes = $this->getEventTypes();
    asort($eventTypes);
    $this->add('select', 'event_type_id', E::ts('Event Type(s)'), $eventTypes, false,
      array('id' => 'event_type_ids', 'multiple' => 'multiple','class' => 'crm-select2'));

    $this->add('textarea', 'additional_wheres', E::ts('Additional where clauses (in JSON format for the Event API 4)'), ['cols' => 50, 'rows' => 6]);  
      

    $this->addButtons(array(
      array('type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE,),
      array('type' => 'cancel', 'name' => E::ts('Cancel'))));
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   * @access public
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->ruleCondition->unserializeParams();
    if (!empty($data['event_type_id'])) {
      $defaultValues['event_type_id'] = $data['event_type_id'];
    }
    if (!empty($data['additional_wheres'])) {
      $defaultValues['additional_wheres'] = $data['additional_wheres'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   * @access public
   */
  public function postProcess() {
    $data['event_type_id'] = $this->_submitValues['event_type_id'];
    $data['additional_wheres'] = $this->_submitValues['additional_wheres'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();
    parent::postProcess();
  }
}
