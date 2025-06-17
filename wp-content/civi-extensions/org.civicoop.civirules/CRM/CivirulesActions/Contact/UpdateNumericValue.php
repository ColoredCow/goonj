<?php
/**
 * Class for CiviRules Group Contact Action Form
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Contact_UpdateNumericValue extends CRM_Civirules_Action {

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
        $new_value = (float) $action_params['update_operand'];
        break;

      case 'increase_by':
        $new_value = (float) $this->getValue($action_params['source_field_id'], 'value', $contactId);
        $new_value += (float) $action_params['update_operand'];
        break;

      case 'decrease_by':
        $new_value = (float) $this->getValue($action_params['source_field_id'], 'value', $contactId);
        $new_value -= (float) $action_params['update_operand'];
        break;

      case 'multiply_by':
        $new_value = (float) $this->getValue($action_params['source_field_id'], 'value', $contactId);
        $new_value *= (float) $action_params['update_operand'];
        break;

      case 'divide_by':
        $new_value = (float) $this->getValue($action_params['source_field_id'], 'value', $contactId);
        $new_value /= (float) $action_params['update_operand'];
        break;

      case 'max_plus':
        $new_value = (float) $this->getValue($action_params['source_field_id'], 'max', $contactId);
        $new_value += (float) $action_params['update_operand'];
        break;

      case 'max_minus':
        $new_value = (float) $this->getValue($action_params['source_field_id'], 'max', $contactId);
        $new_value -= (float) $action_params['update_operand'];
        break;

      case 'min_plus':
        $new_value = (float) $this->getValue($action_params['source_field_id'], 'min', $contactId);
        $new_value += (float) $action_params['update_operand'];
        break;

      case 'min_minus':
        $new_value = (float) $this->getValue($action_params['source_field_id'], 'min', $contactId);
        $new_value -= (float) $action_params['update_operand'];
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
    if ($field_id == 'contact_external_id') {
      civicrm_api3('Contact', 'create', [
          'id'                  => $contact_id,
          'external_identifier' => (int) $new_value]);

    } elseif (is_numeric($field_id)) {
      civicrm_api3('Contact', 'create', [
          'id'                 => $contact_id,
          "custom_{$field_id}" => $new_value]);

    } else {
      // this shouldn't happen
      Civi::log()->debug("UpdateNumericCustomValue Action: Unknown field id '{$field_id}'.");
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
        return (float) civicrm_api3('Contact', 'getvalue', ['id' => $contact_id, 'return' => "custom_{$field_id}"]);

      } elseif ($field_id == 'contact_id') {
        return (float) $contact_id;

      } elseif ($field_id == 'contact_external_id') {
        return (int)  civicrm_api3('Contact', 'getvalue', ['id' => $contact_id, 'return' => 'external_identifier']);

      } else {
        // this should not happen
        Civi::log()->debug("UpdateNumericCustomValue Action: Unknown field id '{$field_id}'.");
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
        return (float) CRM_Core_DAO::singleValueQuery("
            SELECT {$mode}({$custom_field['column_name']})
            FROM {$custom_group['table_name']}
            LEFT JOIN civicrm_contact contact ON contact.id = {$custom_group['table_name']}.entity_id
            WHERE (contact.is_deleted IS NULL OR contact.is_deleted = 0);");

      } elseif ($field_id == 'contact_id') {
        return (float) CRM_Core_DAO::singleValueQuery("SELECT {$mode}(id) FROM civicrm_contact WHERE is_deleted IS NULL OR is_deleted = 0;");

      } elseif ($field_id == 'contact_external_id') {
        return (float) CRM_Core_DAO::singleValueQuery("SELECT {$mode}(external_identifier) FROM civicrm_contact WHERE is_deleted IS NULL OR is_deleted = 0;");

      } else {
        // this should not happen
        Civi::log()->debug("UpdateNumericCustomValue Action: Unknown field id '{$field_id}'.");
        return 0;
      }
    }

    // this should not happen
    Civi::log()->debug("UpdateNumericCustomValue Action: Unknown mode '{$mode}'.");
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/contact/updatenumericvalue', $ruleActionId);
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
        return E::ts("This action takes the value from the contact field specified in 'Source', applies the operator and the operand, and stores the result in the field specified in 'Target'.");

      case 'actionParamsHelp':
        return E::ts("This action takes the value from the contact field specified in 'Source', applies the operator and the operand, and stores the result in the field specified in 'Target'. Only numeric fields will be considered.");
    }

    return $helpText ?? '';
  }

}
