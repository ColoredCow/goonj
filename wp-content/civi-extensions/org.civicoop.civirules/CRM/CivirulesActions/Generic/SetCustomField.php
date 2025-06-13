<?php
/**
 * Class for CiviRules Set Custom Field
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Generic_SetCustomField extends CRM_Civirules_Action {

  /**
   * Method processAction to execute the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   *
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $action_params = $this->getActionParameters();
    $field_id = $action_params['field_id'];

    // Get the entity the custom field extends.
    $entity = civicrm_api3('CustomField', 'getsingle', [
      'return' => ['custom_group_id.extends'],
      'id' => $field_id,
    ])['custom_group_id.extends'];
    if (in_array($entity, ['Individual', 'Organization', 'Household'])) {
      $entity = 'Contact';
    }

    // Get the ID of the entity we're updating.
    if (empty($entityData = $triggerData->getEntityData($entity))) {
      throw new Exception("Custom field id $field_id is not compatible "
        . "with the entity this rule was triggered for");
    }

    $entityId = $entityData['id'];

    // get the value from the configuration
    $new_value = $action_params['value'];
    // check if it's json
    $json_value = json_decode($new_value, 1);
    if ($json_value !== null) {
      $new_value = $json_value;
    }

    // Ensure the new value isn't the same, to prevent unnecessary writes and avoid infinite loops.
    $existingRecord = civicrm_api3($entity, 'get', [
      'id'                 => $entityId,
      "custom_{$field_id}" => $new_value,
    ]);
    if (!$existingRecord['count']) {
      // set the new value using the API
      civicrm_api3($entity, 'create', [
        'id'                 => $entityId,
        "custom_{$field_id}" => $new_value,
      ]);
    }

  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportActionParameters() {
    $action_params = parent::exportActionParameters();
    if (!empty($action_params['field_id'])) {
      try {
        $customField = civicrm_api3('CustomField', 'getsingle', [
          'id' => $action_params['field_id'],
        ]);
        $customGroup = civicrm_api3('CustomGroup', 'getsingle', [
          'id' => $customField['custom_group_id'],
        ]);
        unset($action_params['field_id']);
        $action_params['custom_group'] = $customGroup['name'];
        $action_params['custom_field'] = $customField['name'];
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
    if (!empty($action_params['custom_group'])) {
      try {
        $customField = civicrm_api3('CustomField', 'getsingle', [
          'name' => $action_params['custom_field'],
          'custom_group_id' => $action_params['custom_group'],
        ]);
        $action_params['field_id'] = $customField['id'];
        unset($action_params['custom_group']);
        unset($action_params['custom_field']);
      } catch (\CRM_Core_Exception $e) {
        // Do nothing.
      }
    }
    return parent::importActionParameters($action_params);
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/generic/setcustomvalue', $ruleActionId);
  }

  /**
   * This function validates whether this action works with the selected trigger.
   *
   * This function could be overriden in child classes to provide additional validation
   * whether an action is possible in the current setup.
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    return TRUE;
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
        return E::ts('This action will set the custom field to the provided value.');

      case 'actionParamsHelp':
        return E::ts('This action will set the custom field to the provided value. In case of option groups, you need to provide the <code>value</code> instead of the label. You can find this in the "Option Groups" overview in the system administration menu. Complex values can be set using JSON expressions.');
    }

    return $helpText ?? '';
  }

}
