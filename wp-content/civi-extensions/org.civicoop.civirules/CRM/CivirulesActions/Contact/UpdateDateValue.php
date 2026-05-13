<?php
/**
 * Class for CiviRules Group Contact Action Form
 *
 * @author David Hayes (Black Brick Software) <david@blackbrick.software>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Contact_UpdateDateValue extends CRM_Civirules_Action {

  /**
   * Method processAction to execute the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   *
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $contactId = $triggerData->getContactId();
    $action_params = $this->getActionParameters();

    // get current value
    $old_value = $this->getValue($action_params['target_field_id'], 'value', $contactId);

    // calculate new value
    switch ($action_params['update_operation']) {
      default:
      case 'set':
        // proecess to datetime
        try {
          $new_value_datetime = new DateTime($action_params['update_operand']);
          $new_value = $new_value_datetime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
          Civi::log()->debug("UpdateDateCustomValue Action: Unknown DateTime set format for target field id '{$action_params['target_field_id']}'.");
          return;
        }
        break;

      case 'modify':
        $new_value = $this->getValue($action_params['source_field_id'], 'value', $contactId);
        // proecess to datetime
        try {
          $new_value_datetime = new DateTime($new_value);
          $new_value_datetime->modify($action_params['update_operand']);
          $new_value = $new_value_datetime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
          Civi::log()->debug("UpdateDateCustomValue Action: Unknown DateTime modify format for target field id '{$action_params['target_field_id']}'.");
          return;
        }
        break;

      case 'max_modify':
        $new_value = $this->getValue($action_params['source_field_id'], 'max', $contactId);
        // proecess to datetime
        try {
          $new_value_datetime = new DateTime($new_value);
          $new_value_datetime->modify($action_params['update_operand']);
          $new_value = $new_value_datetime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
          Civi::log()->debug("UpdateDateCustomValue Action: Unknown DateTime modify format for target field id '{$action_params['target_field_id']}'.");
          return;
        }
        break;

      case 'min_modify':
        $new_value = $this->getValue($action_params['source_field_id'], 'min', $contactId);
        // proecess to datetime
        try {
          $new_value_datetime = new DateTime($new_value);
          $new_value_datetime->modify($action_params['update_operand']);
          $new_value = $new_value_datetime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
          Civi::log()->debug("UpdateDateCustomValue Action: Unknown DateTime modify format for target field id '{$action_params['target_field_id']}'.");
          return;
        }
        break;

    }

    if ($old_value != $new_value) {
      $this->setValue($action_params['target_field_id'], $new_value, $contactId);
    }
  }

  /**
   * Set the value to the given field
   *
   * @param $field_id     string field ID or special fields like 'contact_id'
   * @param $new_value    float new value to set
   * @param $contact_id   int contact ID
   */
  protected function setValue($field_id, $new_value, $contact_id) {
    if (is_numeric($field_id)) {
      civicrm_api3('Contact', 'create', [
          'id'                 => $contact_id,
          "custom_{$field_id}" => $new_value]);

    } else {
      // this shouldn't happen
      Civi::log()->debug("UpdateDateCustomValue Action: Unknown field id '{$field_id}'.");
    }
  }


  /**
   * Get the value of the given field for the given contact
   *
   * @param $field_id     string field ID or special fields like 'contact_id'
   * @param $mode         string can be 'value', 'min' or 'max'
   * @param $contact_id   int contact ID
   *
   * @return float current value
   */
  protected function getValue($field_id, $mode, $contact_id) {
    if ($mode == 'value') {
      if (is_numeric($field_id)) {
        return civicrm_api3('Contact', 'getvalue', ['id' => $contact_id, 'return' => "custom_{$field_id}"]);

      } else {
        // this should not happen
        Civi::log()->debug("UpdateDateCustomValue Action: Unknown field id '{$field_id}'.");
        return 0;
      }
    }

    // MIN / MAX mode
    if ($mode == 'min' || $mode == 'max') {
      if (is_numeric($field_id)) {
        $custom_field = civicrm_api3('CustomField', 'getsingle', [
            'id'     => $field_id,
            'return' => 'custom_group_id,column_name']);
        $custom_group = civicrm_api3('CustomGroup', 'getsingle', [
            'id'     => $custom_field['custom_group_id'],
            'return' => 'table_name']);
        return CRM_Core_DAO::singleValueQuery("
            SELECT {$mode}({$custom_field['column_name']})
            FROM {$custom_group['table_name']}
            LEFT JOIN civicrm_contact contact ON contact.id = {$custom_group['table_name']}.entity_id
            WHERE (contact.is_deleted IS NULL OR contact.is_deleted = 0);");

      } else {
        // this should not happen
        Civi::log()->debug("UpdateDateCustomValue Action: Unknown field id '{$field_id}'.");
        return 0;
      }
    }

    // this should not happen
    Civi::log()->debug("UpdateDateCustomValue Action: Unknown mode '{$mode}'.");
    return 0;
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/contact/updatedatevalue', $ruleActionId);
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

    $target_field_id = $action_params['target_field_id'];
    $target_field = '"' . $this->getHumanReadableFieldLabel($target_field_id) . '"';

    if ($action_params['update_operation']==='set') {

      return 'Set '. $target_field . ' to "'. $action_params['update_operand'] . '"';
    }

    $source_field_id = $action_params['source_field_id'];
    $source_field = '"' . $this->getHumanReadableFieldLabel($source_field_id) . '"';

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
        return E::ts("'This action takes the value from the contact field specified in 'Source', applies the operator and the operand, and stores the result in the field specified in 'Target'");

      case 'actionParamsHelp':
        return E::ts("This action takes the value from the contact field specified in 'Source', applies the operator and the operand, and stores the result in the field specified in 'Target'. Only date fields will be considered. For 'Set', the 'Operand' will be passed to <a href=\"https://www.php.net/manual/en/datetime.construct.php\">DateTime::__construct</a> is a valid entry.  For 'Modify', the 'Operand' will be passed to <a href=\"https://www.php.net/manual/en/datetime.modify.php\">DateTime::modify</a> is a valid entry.");
    }

    return $helpText ?? '';
  }

}
