<?php
/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesConditions_Form_Generic_Date extends CRM_CivirulesConditions_Form_Form {

  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {

    $trigger_object = strtolower($this->trigger->object_name);

    $this->add('hidden', 'rule_condition_id');

    if ($trigger_object==='participant') {
      $this->addRadio('date_select', ts('Which Date'), [
        // 'Activity::activity_date_time' => ts('Activity Date'),
        'Participant::participant_register_date' => ts('Participant Register Date'),
        'Event::start_date' => ts('Event Start Date'),
        'Event::end_date' => ts('Event End Date'),
      ], [], null, true);
    }

    $this->add('select', 'operator', ts('Operator'), CRM_Civirules_Utils::getActivityDateOperatorOptions(), true);
    $this->addRadio('compare_type', ts('Comparison Type'), [
      'fixed' => ts('Fixed Date'),
      'field' => ts('Field Value'),
      'trigger' => ts('Trigger Date'),
      'action' => ts('Action Date'),
    ], [], null, true);
    $this->add('datepicker', 'activity_compare_date', ts('Comparison Date'), ['placeholder' => ts('Compare with')],false, ['time' => false]);
    $this->add('select', 'activity_compare_field', ts('Comparison Field'), $this->getEligibleCustomFields(true));
    $this->addRadio('empty_field', ts('Treat Empty Field'), [
      'trigger' => ts('Empty As Trigger Date'),
      'action' => ts('Empty As Action Date'),
      'true' => ts('Condition Always True'),
      'false' => ts('Condition Always False'),
    ]);
    $this->add('datepicker', 'activity_from_date', ts('From date'), ['placeholder' => ts('From')],false, ['time' => false]);
    $this->add('datepicker', 'activity_to_date', ts('To date'), ['placeholder' => ts('To')],false, ['time' => false]);
    $this->addButtons([
      ['type' => 'next', 'name' => ts('Save'), 'isDefault' => true,],
      ['type' => 'cancel', 'name' => ts('Cancel')],
    ]);

    parent::buildQuickForm();
  }

  /**
   * Function to add validation condition rules (overrides parent function)
   *
   * @access public
   */
  public function addRules() {
    $this->addFormRule(['CRM_CivirulesConditions_Form_Generic_Date', 'validateInputFields']);
  }

  /**
   * Method to validate if from and to date cover a valid period
   *
   * @param $fields
   * @return array|bool
   */
  public static function validateInputFields($fields) {
    
    $errors = [];

    // need an operator
    if (!isset($fields['operator'])) {
      $errors['operator'] = 'Required';
      return $errors;
    }

    // if operator is between
    if ($fields['operator'] == 6) {
      // from and to date can not be empty
      if (empty($fields['activity_from_date']) || empty($fields['activity_to_date'])) {
        $errors['operator'] = ts('From and To Date are required  and can not be empty when using Between');
        return $errors;
      }
      // to date can not be earlier than from date
      try {
        $fromDate = new DateTime($fields['activity_from_date']);
        $toDate = new DateTime($fields['activity_to_date']);
        if ($toDate < $fromDate) {
          $errors['from_date'] = ts('From Date should be earlier than or the same as To Date');
          return $errors;
        }
      } catch (Exception $ex) {
        Civi::log()->error('Could not parse either from date or to date into DateTime in ' . __METHOD__);
        $errors['operator'] = ts('Invalid From or To Date');
        return $errors;
      }
    } else {
      // need to know comparison type
      if (empty($fields['compare_type'])) {
        $errors['compare_type'] = 'Required';
        return $errors;
      }
      if ($fields['compare_type']==='fixed' || $fields['compare_type']==='field') {
        if (empty($fields['empty_field']))
          $errors['empty_field'] = 'Required';
        if ($fields['compare_type']==='field' && empty($fields['activity_compare_field']))
          $errors['activity_compare_field'] = 'Required';
      }
    }

    return empty($errors) ? true : $errors;
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

    if (!empty($data['date_select']))
      $defaultValues['date_select'] = $data['date_select'];

    if (!empty($data['operator']))
      $defaultValues['operator'] = $data['operator'];

    if (!empty($data['compare_type'])) {
      $defaultValues['compare_type'] = $data['compare_type'];
      if ($data['compare_type']==='fixed' || $data['compare_type']==='field') {
        $defaultValues['empty_field'] = $data['empty_field'];
      }
    } elseif (!empty($data['use_trigger_date'])) { // old verion of way trigger date was saved
      $defaultValues['compare_type'] = 'trigger';
    } elseif (!empty($data['use_action_date'])) { // old verion of way action date was saved
      $defaultValues['compare_type'] = 'action';
    }

    if (!empty($data['activity_compare_date'])) {
      $defaultValues['activity_compare_date'] = $data['activity_compare_date'];
    }
    
    if (!empty($data['activity_compare_field'])) {
      $defaultValues['activity_compare_field'] = $data['activity_compare_field'];
    }
    
    if (!empty($data['activity_from_date'])) {
      $defaultValues['activity_from_date'] = $data['activity_from_date'];
    }

    if (!empty($data['activity_to_date'])) {
      $defaultValues['activity_to_date'] = $data['activity_to_date'];
    }
    
    if ($data['operator'] == 6) {
      $this->assign('between', 1);
    } else {
      $this->assign('between', 0);
    }

    return $defaultValues;
  }

  /**
   * Overridden parent method to perform data processing once form is submitted
   *
   * @access public
   */
  public function postProcess() {
    $data = [];
    if (!empty($this->_submitValues['date_select']))
      $data['date_select'] = $this->_submitValues['date_select'];
    $data['operator'] = $this->_submitValues['operator'];
    $data['compare_type'] = $this->_submitValues['compare_type'];
    if ($this->_submitValues['operator'] == 6) {
      $data['activity_compare_date'] = $data['compare_type'] = $data['empty_field'] = "";
      $data['activity_from_date'] = $this->_submitValues['activity_from_date'];
      $data['activity_to_date'] = $this->_submitValues['activity_to_date'];
    } else {
      $data['activity_from_date'] = "";
      $data['activity_to_date'] = "";
      if ($this->_submitValues['compare_type'] == 'action' || $this->_submitValues['compare_type'] == 'trigger') {
        $data['activity_compare_date'] = $data['empty_field'] = "";
      } elseif ($this->_submitValues['compare_type'] == 'field') {
        $data['activity_compare_field'] = $this->_submitValues['activity_compare_field'];
        $data['empty_field'] = $this->_submitValues['empty_field'];
      } else {
        $data['compare_type'] = 'fixed'; // should be this already, default out
        $data['activity_compare_date'] = $this->_submitValues['activity_compare_date'];
        $data['empty_field'] = $this->_submitValues['empty_field'];
      }
    }
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();
    parent::postProcess();
  }

  /**
   * Get a list of all numeric contact custom fields
   *
   * @param bool also include fields that are not updatable
   * @return array list of field IDs
   */
  protected function getEligibleCustomFields($include_readonly_fields = false) {

    $field_list = [];

    // find relevant groups
    $eligible_group_ids = [];
    $group_query = civicrm_api3('CustomGroup', 'get', [
      'extends'      => ['IN' => ['Contact', 'Individual', 'Organization', 'Household']],
      'is_active'    => 1,
      'option.limit' => 0,
      'return'       => 'id,title',
    ]);
    if (!empty($group_query['values'])) {
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
        $field_list['Contact::'.$field['id']] = E::ts("Field '%1' (Group '%2')", [
          1 => $field['label'],
          2 => $eligible_group_ids[$field['custom_group_id']]
        ]);
      }
    }

    if ($include_readonly_fields && !empty($this->trigger->object_name)) {

      $trigger_object = strtolower($this->trigger->object_name);
      $extends = [ $trigger_object ];
      if ($trigger_object==='participant') {
        $extends[] = 'Event';
      }

      // find relevant trigger object groups
      $eligible_trigger_group_ids = [];
      $trigger_group_query = civicrm_api3('CustomGroup', 'get', [
        'extends'      => ['IN' => $extends],
        'is_active'    => 1,
        'option.limit' => 0,
        'return'       => 'id,title',
      ]);

      if (!empty($trigger_group_query['values'])) {
        foreach ($trigger_group_query['values'] as $group) {
          $eligible_trigger_group_ids[$group['id']] = $group['title'];
        }
        // find eligible trigger object fields
        $trigger_field_query = civicrm_api3('CustomField', 'get', [
          'data_type'       => ['IN' => ['Date']],
          'custom_group_id' => ['IN' => array_keys($eligible_trigger_group_ids)],
          'is_active'       => 1,
          'option.limit'    => 0,
          'return'          => 'id,label,custom_group_id',
        ]);
        foreach ($trigger_field_query['values'] as $field) {
          $field_list[$trigger_object.'::'.$field['id']] = E::ts("%3 Trigger: Field '%1' (Group '%2')", [
            1 => $field['label'],
            2 => $eligible_trigger_group_ids[$field['custom_group_id']],
            3 => ucwords($trigger_object),
          ]);
        }
      }

      // other available fields
      if ($trigger_object==='activity') {
        $field_list['Activity::activity_date_time'] = E::ts("%2 Trigger: Field '%1'", [
          1 => 'Activity Date',
          2 => 'Activity',
          ]);
      } elseif (in_array($trigger_object, ['event', 'participant'])) {
        $field_list['Event::start_date'] = E::ts("%2 Trigger: Field '%1'", [
          1 => 'Event Start Date',
          2 => 'Event',
        ]);
        $field_list['Event::end_date'] = E::ts("%2 Trigger: Field '%1'", [
          1 => 'Event End Date',
          2 => 'Event',
        ]);
        if ($trigger_object==='participant') {
          $field_list['Participant::participant_register_date'] = E::ts("%2 Trigger: Field '%1'", [ // just register_date does not work
            1 => 'Participant Register Date',
            2 => 'Participant',
          ]);
        }
      }
    }

    return $field_list;
  }
}
