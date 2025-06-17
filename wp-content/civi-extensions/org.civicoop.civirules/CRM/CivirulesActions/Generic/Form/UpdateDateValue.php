<?php
/**
 * Class for CiviRules Advanced Update Date Action Form
 *
 * @author David Hayes (Black Brick Software) <david@blackbrick.software>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Generic_Form_UpdateDateValue extends CRM_CivirulesActions_Form_Form {

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
   * Function to add validation action rules (overrides parent function)
   *
   * @access public
   */
  public function addRules() {
    $this->addFormRule([
      'CRM_CivirulesActions_Generic_Form_UpdateDateValue',
      'validateFields',
    ]);
    $this->addFormRule([
      'CRM_CivirulesActions_Generic_Form_UpdateDateValue',
      'validateOperation',
    ]);
    $this->addFormRule([
      'CRM_CivirulesActions_Generic_Form_UpdateDateValue',
      'validateOperand',
    ]);
  }

  /**
   * Function to validate operation
   *
   * @param array $fields
   * @return array|bool
   * @access public
   * @static
   */
  static function validateFields($fields) {

    $errors = [];

    $source_parts = explode('::',$fields['source_field_id']);
    if (count($source_parts)!==2)
      $errors['source_field_id'] = 'Unsupported field';

    $target_parts = explode('::',$fields['target_field_id']);
    if (count($target_parts)!==2)
      $errors['target_field_id'] = 'Unsupported field';

    if (count($errors))
      return $errors;

    // we should not trip these if dropdown is populated correctly

    $entity_search = civicrm_api3('Entity', 'get', [
      'sequential' => 1,
    ]);
    $entities = array_map('strtolower', $entity_search['values']);

    list($source_entity, $source_field_id) = $source_parts;
    if (!in_array(strtolower($source_entity), $entities))
      $errors['source_field_id'] = 'Invalid field';

    list($target_entity, $target_field_id) = $target_parts;
    if (!in_array(strtolower($target_entity), $entities))
      $errors['target_field_id'] = 'Invalid field';

    if (count($errors))
      return $errors;

    return true;
  }

  /**
   * Function to validate operation
   *
   * @param array $fields
   * @return array|bool
   * @access public
   * @static
   */
  static function validateOperation($fields) {
    $errors = [];

    if (empty($fields['update_operation'])) {
      $errors['update_operation'] = "'Operation' is required";
    } elseif (in_array($fields['update_operation'],['max_modify','min_modify'])) {
      $source_parts = explode('::',$fields['source_field_id']);
      if (count($source_parts)===2) { // will be caught by other validation if this is not true
        list($entity_type, $field_id) = $source_parts;
        if (!in_array(strtolower($entity_type), ['contact', 'individual', 'organization', 'household']))
          $errors['update_operation'] = "'Operation' is not supported for selected 'Source Field'";
      }
    }

    if (count($errors))
      return $errors;

    return true;
  }

  /**
   * Function to validate operand
   *
   * @param array $fields
   * @return array|bool
   * @access public
   * @static
   */
  static function validateOperand($fields) {
    $errors = [];

    if ($fields['update_operation']==='set') {
      if (empty($fields['update_operand'])) {
        $errors['update_operand'] = "Operation is required in 'Set' mode";
      } else {
        try {
          new DateTime($fields['update_operand']);
        } catch (Exception $e) {
          $errors['update_operand'] = 'Operation is an invalid input to DateTime::__construct';
        }
      }
    } elseif(!empty($fields['update_operand'])) {
      $datetime = new DateTime;
      $modified = $datetime->modify($fields['update_operand']);
      if ($modified===false)
        $errors['update_operand'] = 'Operation is an invalid input to DateTime::modify';
    }

    if (count($errors))
      return $errors;

    return true;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    $values = $this->exportValues();
    $configuration = [
      'source_field_id'  => CRM_Utils_Array::value('source_field_id', $values),
      'target_field_id'  => CRM_Utils_Array::value('target_field_id', $values),
      'update_operation' => CRM_Utils_Array::value('update_operation', $values),
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
