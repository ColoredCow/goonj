<?php
/**
 * Class for CiviRules Group Contact Action Form
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_CivirulesActions_Activity_Form_Activity extends CRM_CivirulesActions_Form_Form {

  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');
    $this->add('select', 'activity_type_id', ts('Activity type'), ['' => ts('-- please select --')] + CRM_Core_OptionGroup::values('activity_type'), true);
    $this->add('select', 'status_id', ts('Status'), ['' => ts('-- please select --')] + CRM_Core_OptionGroup::values('activity_status'), true);
    $this->add('text', 'subject', ts('Subject'));
    $this->add('wysiwyg', 'details', ts('Details'));

    $attributes = [
      'multiple' => TRUE,
      'create' => TRUE,
      'api' => ['params' => ['is_deceased' => 0]]
    ];
    $this->addEntityRef('assignee_contact_id', ts('Assigned to'), $attributes, false);

    $this->addYesNo('send_email', 'Send Email to Assigned Contacts', false, true);

    $delayList = ['' => ts(' - Use system date (default) - ')] + CRM_Civirules_Delay_Factory::getOptionList();
    $this->add('select', 'activity_date_time', ts('Set activity date'), $delayList);
    foreach(CRM_Civirules_Delay_Factory::getAllDelayClasses() as $delay_class) {
      $delay_class->addElements($this, 'activity_date_time', $this->rule);
    }
    $this->assign('delayClasses', CRM_Civirules_Delay_Factory::getAllDelayClasses());

    $this->add('text', 'duration', ts('Duration'));
    // #188 allow assignment of target/assignees dynamically via relationship contacts
    if ($this->trigger->object_name == 'Relationship' && $this->trigger->op == 'create') {
      $this->add('select', 'relationship_contact', ts('Relationship Contact (activity target)'), [
        'both' => ts('Both Contacts'),
        'contact_id_a' => ts('Contact A'),
        'contact_id_b' => ts('Contact B')
      ], TRUE);

      $this->add('select', 'relationship_contact_assignee', ts('Relationship Contact Assignee'), [
        '' => ts('-- please select --'),
        'contact_id_a' => ts('Contact A'),
        'contact_id_b' => ts('Contact B')
      ], FALSE);
    }

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
    $data = $this->ruleAction->unserializeParams();
    if (!empty($data['activity_type_id'])) {
      $defaultValues['activity_type_id'] = $data['activity_type_id'];
    }
    if (!empty($data['status_id'])) {
      $defaultValues['status_id'] = $data['status_id'];
    }
    if (!empty($data['subject'])) {
      $defaultValues['subject'] = $data['subject'];
    }
    if (!empty($data['duration'])) {
      $defaultValues['duration'] = $data['duration'];
    }
    if (!empty($data['details'])) {
      $defaultValues['details'] = $data['details'];
    }
    if (!empty($data['assignee_contact_id'])) {
      $defaultValues['assignee_contact_id'] = $data['assignee_contact_id'];
    }
    if (!empty($data['relationship_contact'])) {
      $defaultValues['relationship_contact'] = $data['relationship_contact'];
    }
    if (!empty($data['relationship_contact_assignee'])) {
      $defaultValues['relationship_contact_assignee'] = $data['relationship_contact_assignee'];
    }
    $defaultValues['send_email'] = $data['send_email'] ?? '';
    foreach(CRM_Civirules_Delay_Factory::getAllDelayClasses() as $delay_class) {
      $delay_class->setDefaultValues($defaultValues, 'activity_date_time', $this->rule);
    }
    if (!empty($data['activity_date_time']) && $data['activity_date_time'] != 'null') {
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
    $this->addFormRule([
      'CRM_CivirulesActions_Activity_Form_Activity',
      'validateActivityDateTime'
    ]);
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
    $errors = [];
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
    $data['subject'] = $this->_submitValues['subject'];
    $data['duration'] = $this->_submitValues['duration'];
    $data['details'] = $this->_submitValues['details'];
    $data["assignee_contact_id"] = explode(',', $this->_submitValues["assignee_contact_id"]);

    $data['activity_date_time'] = 'null';
    if (!empty($this->_submitValues['activity_date_time'])) {
      $scheduledDateClass = CRM_Civirules_Delay_Factory::getDelayClassByName($this->_submitValues['activity_date_time']);
      $scheduledDateClass->setValues($this->_submitValues, 'activity_date_time', $this->rule);
      $data['activity_date_time'] = serialize($scheduledDateClass);
    }

    if (!empty($this->_submitValues['relationship_contact'])) {
      $data['relationship_contact'] = $this->_submitValues['relationship_contact'];
    }

    if (!empty($this->_submitValues['relationship_contact_assignee'])) {
      $data['relationship_contact_assignee'] = $this->_submitValues['relationship_contact_assignee'];
    }

    $data['send_email'] = $this->_submitValues['send_email'];

    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }

}
