<?php
/**
 * Class for CiviRules Advanced Update Date Action Form
 *
 * @author David Hayes (Black Brick Software) <david@blackbrick.software>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Generic_UpdateDateValue extends CRM_Civirules_Action {

  /**
   * Method processAction to execute the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {

    $contact_id = $triggerData->getContactId();
    $action_params = $this->getActionParameters();
    if (empty($action_params))
      return;

    // $entity_id = $triggerData->getEntityId(); // for what ever reason this is sometime empty
    // $trigger = $triggerData->getTrigger();
    // $ojbectName = null;
    // if ($trigger instanceof CRM_Civirules_Trigger_Post)
    //   $ojbectName = $trigger->getObjectName();

    // parse target field parts
    try {
      list($target_entity, $target_field_id) = $this->parseRawFieldId($action_params['target_field_id']);
    } catch(Exception $e) {
      Civi::log()->debug('UpdateDateCustomValue Action: Error parsing target field name ('.$e->getMessage().')');
      return;
    }

    // get current value
    try {
      $old_value = $this->getValue($target_entity, $target_field_id, $contact_id, 'value');
    } catch(Exception $e) {
      Civi::log()->debug('UpdateDateCustomValue Action: Error retrieving target entity value ('.$e->getMessage().')');
      return;
    }
    $new_value = $old_value;

    // calculate new value
    if ($action_params['update_operation']==='set') {

      // proecess to datetime
      try {
        $new_value_datetime = new DateTime($action_params['update_operand']);
        $new_value = $new_value_datetime->format('Y-m-d H:i:s');
      } catch (Exception $e) {
        Civi::log()->debug("UpdateDateCustomValue Action: Unknown DateTime::__construct operand '{$action_params['update_operand']}'.");
        return;
      }

    } else {

      // parse target field parts
      try {
        list($source_entity, $source_field_id) = $this->parseRawFieldId($action_params['source_field_id']);
      } catch(Exception $e) {
        Civi::log()->debug('UpdateDateCustomValue Action: Error parsing source field name ('.$e->getMessage().')');
        return;
      }
      if (in_array(strtolower($source_entity), ['contact', 'individual', 'organization', 'household'])) {
        // source is a contact field
        $source_entity_id = $contact_id;
      } else {
        // source is the triggering entity
        $source_entity_data = $triggerData->getEntityData($source_entity);
        if (empty($source_entity_data)) {
          Civi::log()->debug("UpdateDateCustomValue Action: Source Entity Data or ID is empty for '{$source_entity}'");
          return;
        }
        if (empty($source_entity_data['id'])) {
          Civi::log()->debug("UpdateDateCustomValue Action: Source Entity ID is empty '{$source_entity_data['id']}'");
          return;
        }
        $source_entity_id = $source_entity_data['id'];
      }

      try {
        switch ($action_params['update_operation']) {
          case 'modify':
            $new_value = $this->getValue($source_entity, $source_field_id, $source_entity_id, 'value');
            break;
          case 'max_modify':
            $new_value = $this->getValue($source_entity, $source_field_id, $source_entity_id, 'max');
            break;
          case 'min_modify':
            $new_value = $this->getValue($source_entity, $source_field_id, $source_entity_id, 'min');
            break;
          default:
            Civi::log()->debug("UpdateDateCustomValue Action: Unknown operation '{$action_params['update_operation']}'.");
            return;
        }
      } catch (Exception $e) {
        Civi::log()->debug("$source_entity, $source_field_id, $source_entity_id, 'value'");
        Civi::log()->debug('UpdateDateCustomValue Action: Error retrieving source entity value ('.$e->getMessage().')');
        return;
      }

      // proecess to datetime
      if (!empty($action_params['update_operand'])) {
        try {
          $new_value_datetime = new DateTime($new_value);
          $new_value_datetime->modify($action_params['update_operand']);
          $new_value = $new_value_datetime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
          Civi::log()->debug("UpdateDateCustomValue Action: Unknown DateTime::__construct or DateTime::modify format for source field '{$action_params['target_field_id']}' or operand '{$action_params['update_operand']}'.");
          return;
        }
      }

    }

    if ($old_value != $new_value) {
      try {
        $this->setValue($target_entity, $target_field_id, $contact_id, $new_value);
      } catch(Exception $e) {
        Civi::log()->debug('UpdateDateCustomValue Action: Error setting target entity value ('.$e->getMessage().')');
        return;
      }
    }
  }

  /**
   * Parse saved field id into entity type and field id (eg Contact:10 to Contact and 10)
   *
   * @param string $raw_field_id
   * @return array [entity_type, field_id]
   * @throws Exception when field id is invalid
   */
  protected function parseRawFieldId($raw_field_id) {

    $field_parts = explode('::', $raw_field_id);

    if (count($field_parts)!==2)
      throw new Exception("Invalid field format '{$raw_field_id}'.");

    list($entity_type, $field_id) = $field_parts;

    $entity_search = civicrm_api3('Entity', 'get', [
      'sequential' => 1,
    ]);

    $entities = array_map('strtolower', $entity_search['values']);
    if (!in_array(strtolower($entity_type), $entities))
      throw new Exception("Invalid entity for field '{$raw_field_id}'.");

    return $field_parts;
  }

  /**
   * Set the value to the given field
   *
   * @param string $entity_type entity type of primary object trigger; Only Contact is supported
   * @param int $entity_id      entity ID of primary object trigger
   * @param string $field_id    field ID or special fields like 'contact_id'
   * @param string $new_value   new value to set
   * @return void
   * @throws Exception when unable to set value
   */
  protected function setValue($entity_type, $field_id, $entity_id, $new_value) {
    if (strtolower($entity_type)==='contact') {
      if (is_numeric($field_id)) {
        civicrm_api3('Contact', 'create', [
          'id'                 => $entity_id,
          "custom_{$field_id}" => $new_value,
        ]);
      } else {
        // this shouldn't happen
        throw new Exception("Unknown field id '{$field_id}'.");
      }
    } else {
      throw new Exception("Unsupported update entity '{$entity_type}'.");
    }
  }


  /**
   * Get the value of the given field for the given contact
   *
   * @param string $entity_type entity type of primary object trigger
   * @param int $entity_id      entity ID of primary object trigger
   * @param string $field_id    field ID or special fields like 'contact_id'
   * @param string $mode        string can be 'value', 'min' or 'max'
   * @return mixed              current value
   * @throws Exception when unable to retrieve value
   */
  protected function getValue($entity_type, $field_id, $entity_id, $mode) {

    if ($mode == 'value') {
      if (is_numeric($field_id)) {
        return civicrm_api3($entity_type, 'getvalue', [
          'id' => $entity_id,
          'return' => "custom_{$field_id}",
        ]);
      } elseif (!empty($field_id)) {
        return civicrm_api3($entity_type, 'getvalue', [
          'id' => $entity_id,
          'return' => $field_id,
        ]);
      } else {
        throw new Exception("Unknown field id '{$field_id}'.");
      }
    }

    // MIN / MAX mode
    if ($mode==='min' || $mode==='max') {
      if (in_array(strtolower($entity_type), ['contact', 'individual', 'organization', 'household'])) {
        if (is_numeric($field_id)) {
          $custom_field = civicrm_api3('CustomField', 'getsingle', [
            'id'     => $field_id,
            'return' => 'custom_group_id,column_name',
          ]);
          $custom_group = civicrm_api3('CustomGroup', 'getsingle', [
            'id'     => $custom_field['custom_group_id'],
            'return' => 'table_name',
          ]);
          return CRM_Core_DAO::singleValueQuery("
            SELECT {$mode}({$custom_field['column_name']})
            FROM {$custom_group['table_name']}
            LEFT JOIN civicrm_contact contact
              ON contact.id = {$custom_group['table_name']}.entity_id
            WHERE (contact.is_deleted IS NULL OR contact.is_deleted = 0);
          ");
        } else {
          // this should not happen
          throw new Exception("Unknown field id '{$field_id}'.");
        }
      } else {
        throw new Exception("Unsupported mode '{$mode}' for entity '{$entity_type}' and field '{$field_id}'.");
      }
    }

    // this should not happen
    throw new Exception("Unknown mode '{$mode}'.");
  }

  /**
   * Method to return the url for additional form processing for action
   * and return false if none is needed
   *
   * @param int $ruleActionId
   * @return bool
   * @access public
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/generic/updatedatevalue', $ruleActionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   * @throws \CRM_Core_Exception
   */
  public function userFriendlyConditionParams() {

    $action_params = $this->getActionParameters();

    try {
      list($target_entity, $target_field_id) = $this->parseRawFieldId($action_params['target_field_id']);
      $target_field = '"' . $this->getHumanReadableFieldLabel($target_field_id) . '" (' . $target_entity . ')';
    } catch(Exception $e) {
      Civi::log()->debug('UpdateDateCustomValue Action: Error parsing target field name ('.$e->getMessage().')');
      return;
    }

    if ($action_params['update_operation']==='set') {

      return 'Set '. $target_field . ' to "'. $action_params['update_operand'] . '"';
    }

    try {
      list($source_entity, $source_field_id) = $this->parseRawFieldId($action_params['source_field_id']);
      $source_field = '"' . $this->getHumanReadableFieldLabel($source_field_id) . '" (' . $source_entity . ')';
    } catch(Exception $e) {
      Civi::log()->debug('UpdateDateCustomValue Action: Error parsing source field name ('.$e->getMessage().')');
      return;
    }

    $update = empty($action_params['update_operand']) ? '' : ' modifield by "' . $action_params['update_operand'] . '"';

    if ($action_params['update_operation']==='modify') {

      return 'Set '. $target_field .' to the value of ' . $source_field . $update;
    }

    if ($action_params['update_operation']==='max_modify') {

      return 'Set ' . $target_field . ' to the Global Maximum value of ' . $source_field . $update;
    }

    if ($action_params['update_operation']==='min_modify') {

      return 'Set ' . $target_field . ' to the Global Minimum value of ' . $source_field . $update;
    }

    return 'Error';
  }

  /**
   * Find the human readable label for a field
   *
   * @param string|int $field_identifier
   * @access protected
   * @return string
   * @throws \CRM_Core_Exception
   */
  protected function getHumanReadableFieldLabel($field_identifier) {

    // Custom fields
    if (is_numeric($field_identifier)) {
      $custom_field = civicrm_api3('CustomField', 'getsingle', [
        'id' => $field_identifier,
      ]);
      return $custom_field['label'];
    }

    // Built in Fields
    return ucwords(str_replace('_', ' ', $field_identifier));
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportActionParameters() {
    $action_params = parent::exportActionParameters();
    if (!empty($action_params['target_field_id'])) {
      try {
        $customField = civicrm_api3('CustomField', 'getsingle', [
          'id' => $action_params['target_field_id'],
        ]);
        $customGroup = civicrm_api3('CustomGroup', 'getsingle', [
          'id' => $customField['custom_group_id'],
        ]);
        unset($action_params['target_field_id']);
        $action_params['target_custom_group'] = $customGroup['name'];
        $action_params['target_custom_field'] = $customField['name'];
      } catch (\CRM_Core_Exception $e) {
        // Do nothing.
      }
    }
    if (!empty($action_params['source_field_id'])) {
      try {
        $customField = civicrm_api3('CustomField', 'getsingle', [
          'id' => $action_params['source_field_id'],
        ]);
        $customGroup = civicrm_api3('CustomGroup', 'getsingle', [
          'id' => $customField['custom_group_id'],
        ]);
        unset($action_params['source_field_id']);
        $action_params['source_custom_group'] = $customGroup['name'];
        $action_params['source_custom_field'] = $customField['name'];
      } catch (\CRM_Core_Exception $e) {
        // Do nothing.
      }
    }
    return $action_params;
  }

  /**
   * Returns condition data as an array and ready for import.
   * E.g. replace name for ids.
   *
   * @return string
   */
  public function importActionParameters($action_params = NULL) {
    if (!empty($action_params['target_custom_group'])) {
      try {
        $customField = civicrm_api3('CustomField', 'getsingle', [
          'name' => $action_params['target_custom_field'],
          'custom_group_id' => $action_params['target_custom_group'],
        ]);
        $action_params['target_field_id'] = $customField['id'];
        unset($action_params['target_custom_group']);
        unset($action_params['target_custom_field']);
      } catch (\CRM_Core_Exception $e) {
        // Do nothing.
      }
    }
    if (!empty($action_params['source_custom_group'])) {
      try {
        $customField = civicrm_api3('CustomField', 'getsingle', [
          'name' => $action_params['source_custom_field'],
          'custom_group_id' => $action_params['source_custom_group'],
        ]);
        $action_params['source_field_id'] = $customField['id'];
        unset($action_params['source_custom_group']);
        unset($action_params['source_custom_field']);
      } catch (\CRM_Core_Exception $e) {
        // Do nothing.
      }
    }
    return parent::importActionParameters($action_params);
  }

  /**
   * Get various types of help text for the action:
   *   - actionDescription: When choosing from a list of actions, explains what the action does.
   *   - actionDescriptionWithParams: When a action has been configured for a rule provides a
   *       user friendly description of the action and params (see $this->userFriendlyConditionParams())
   *   - actionParamsHelp (default): If the action has configurable params, show this help text when configuring
   * @param string $context
   *
   * @return string
   */
  public function getHelpText(string $context): string {
    switch ($context) {
      case 'actionDescriptionWithParams':
        return $this->userFriendlyConditionParams();

      case 'actionDescription':
        return E::ts("For a 'Set' 'Operation', this action sets the value of the 'Target Field' to the value specified in the 'Operand'.");

      case 'actionParamsHelp':
        return E::ts("For a 'Set' 'Operation', this action sets the value of the 'Target Field' to the value specified in the 'Operand'. The 'Operand' in this case can be any valid value that can be passed to the <a href=\"https://www.php.net/manual/en/datetime.construct.php\">DateTime::__construct</a> method.<br /><br />For all other 'Operations', this action takes the value from the 'Source Field', applies the 'Operation' and the 'Operand', and stores the result in the 'Target Field'. 'Operands' can be any valid input to the <a href=\"https://www.php.net/manual/en/datetime.modify.php\">DateTime::modify</a> method.{/ts}<br /><br />'Set to (global) maximum with modification' and 'Set to (global) minimum with modification' are only supported on Contact fields.");
    }

    return $helpText ?? '';
  }

}
