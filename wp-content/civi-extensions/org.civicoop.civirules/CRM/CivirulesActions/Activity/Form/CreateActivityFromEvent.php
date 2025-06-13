<?php
/**
 * Class for CiviRules Group Contact Action Form
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Activity_Form_CreateActivityFromEvent extends CRM_CivirulesActions_Form_Form {

  public static function getActivityCustomFields() {
    static $activityCustomFields = false;
    if ($activityCustomFields === false) {
      $customGroups = civicrm_api3('CustomGroup', 'get', [
        'extends' => 'Activity',
        'options' => ['limit' => 0]
      ]);
      $activityCustomFields = [];
      foreach ($customGroups['values'] as $customGroup) {
        $customFields = civicrm_api3('CustomField', 'get', [
          'custom_group_id' => $customGroup['id'],
          'options' => ['limit' => 0]
        ]);
        foreach ($customFields['values'] as $customField) {
          $activityCustomFields[$customField['id']] = $customGroup['title'] . ': ' . $customField['label'];
        }
      }
    }
    return $activityCustomFields;
  }

  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');
    $this->add('select', 'activity_type_id', E::ts('Activity type'), array('' => E::ts('-- please select --')) + CRM_Core_OptionGroup::values('activity_type'), true);
    $this->add('select', 'status_id', E::ts('Status'), array('' => E::ts('-- please select --')) + CRM_Core_OptionGroup::values('activity_status'), true);
    $this->add('select', 'event_id_custom_field', E::ts('Event ID custom field'), array('' => E::ts('-- please select --')) + self::getActivityCustomFields(), true);
    $this->add('select', 'event_start_date_custom_field', E::ts('Custom field to store the Event Start Date'), array('' => E::ts('-- please select --')) + self::getActivityCustomFields(), false);
    $this->add('select', 'event_end_date_custom_field', E::ts('Custom field to store the Event End Date'), array('' => E::ts('-- please select --')) + self::getActivityCustomFields(), false);

    $attributes = array(
      'multiple' => TRUE,
      'create' => TRUE,
      'api' => array('params' => array('is_deceased' => 0))
    );
    $this->addEntityRef('assignee_contact_id', E::ts('Assigned to'), $attributes, false);

    $this->addYesNo('send_email', 'Send Email to Assigned Contacts', false, true);

    $delayList = array('' => E::ts(' - Use system date (default) - ')) + CRM_Civirules_Delay_Factory::getOptionList();
    $this->add('select', 'activity_date_time', E::ts('Set activity date'), $delayList);
    foreach(CRM_Civirules_Delay_Factory::getAllDelayClasses() as $delay_class) {
      $delay_class->addElements($this, 'activity_date_time', $this->rule);
    }
    $this->assign('delayClasses', CRM_Civirules_Delay_Factory::getAllDelayClasses());

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
    $data = $this->ruleAction->unserializeParams();
    if (!empty($data['activity_type_id'])) {
      $defaultValues['activity_type_id'] = $data['activity_type_id'];
    }
    if (!empty($data['status_id'])) {
      $defaultValues['status_id'] = $data['status_id'];
    }
    if (!empty($data['assignee_contact_id'])) {
      $defaultValues['assignee_contact_id'] = $data['assignee_contact_id'];
    }
    if (!empty($data['send_email'])) {
      $defaultValues['send_email'] = $data['send_email'];
    } else {
      $defaultValues['send_email'] = '0';
    }
    if (!empty($data['event_id_custom_field'])) {
      $defaultValues['event_id_custom_field'] = $data['event_id_custom_field'];
    }
    if (!empty($data['event_start_date_custom_field'])) {
      $defaultValues['event_start_date_custom_field'] = $data['event_start_date_custom_field'];
    }
    if (!empty($data['event_end_date_custom_field'])) {
      $defaultValues['event_end_date_custom_field'] = $data['event_end_date_custom_field'];
    }
    foreach(CRM_Civirules_Delay_Factory::getAllDelayClasses() as $delay_class) {
      $delay_class->setDefaultValues($defaultValues, 'activity_date_time', $this->rule);
    }
    if ($data['activity_date_time'] != 'null') {
      $activityDateClass = unserialize($data['activity_date_time']);
      if ($activityDateClass) {
        $defaultValues['activity_date_time'] = get_class($activityDateClass);
        foreach ($activityDateClass->getValues('activity_date_time', $this->rule) as $key => $val) {
          $defaultValues[$key] = $val;
        }
      }
    }
    return $defaultValues;
  }

  /**
   * Function to add validation action rules (overrides parent function)
   *
   * @access public
   */
  public function addRules() {
    parent::addRules();
    $this->addFormRule(array(
      'CRM_CivirulesActions_Activity_Form_CreateActivityFromEvent',
      'validateActivityDateTime'
    ));
  }

  /**
   * Function to validate value of the delay
   *
   * @param array $fields
   * @return array|bool
   * @access public
   * @static
   */
  static function validateActivityDateTime($fields) {
    $errors = array();
    if (!empty($fields['activity_date_time'])) {
      $ruleActionId = CRM_Utils_Request::retrieve('rule_action_id', 'Integer');
      $ruleAction = new CRM_Civirules_BAO_RuleAction();
      $ruleAction->id = $ruleActionId;
      $ruleAction->find(true);
      $rule = new CRM_Civirules_BAO_Rule();
      $rule->id = $ruleAction->rule_id;
      $rule->find(true);

      $activityDateClass = CRM_Civirules_Delay_Factory::getDelayClassByName($fields['activity_date_time']);
      $activityDateClass->validate($fields, $errors, 'activity_date_time', $rule);
    }

    if (count($errors)) {
      return $errors;
    }

    return TRUE;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    $data['activity_type_id'] = $this->_submitValues['activity_type_id'];
    $data['status_id'] = $this->_submitValues['status_id'];
    $data['event_id_custom_field'] = $this->_submitValues['event_id_custom_field'];
    $data['event_start_date_custom_field'] = $this->_submitValues['event_start_date_custom_field'] ?? false;
    $data['event_end_date_custom_field'] = $this->_submitValues['event_end_date_custom_field'] ?? false;
    $data["assignee_contact_id"] = explode(',', $this->_submitValues["assignee_contact_id"]);

    $data['activity_date_time'] = 'null';
    if (!empty($this->_submitValues['activity_date_time'])) {
      $scheduledDateClass = CRM_Civirules_Delay_Factory::getDelayClassByName($this->_submitValues['activity_date_time']);
      $scheduledDateClass->setValues($this->_submitValues, 'activity_date_time', $this->rule);
      $data['activity_date_time'] = serialize($scheduledDateClass);
    }

    $data['send_email'] = $this->_submitValues['send_email'];

    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }

}
