<?php
/**
 * Class for CiviRules Copy Custom Field
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Generic_CopyCustomField extends CRM_Civirules_Action {

  /**
   * Method processAction to execute the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   *
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $action_params = $this->getActionParameters();

    // Source field
    $copy_from_field_id = $action_params['copy_from_field_id'];

    // Get the entity the custom field extends.
    $from_entity = civicrm_api3('CustomField', 'getsingle', [
      'return' => ['custom_group_id.extends'],
      'id' => $copy_from_field_id,
    ])['custom_group_id.extends'];
    if (in_array($from_entity, ['Individual', 'Organization', 'Household'])) {
      $from_entity = 'Contact';
    }

    // Get the ID of the entity we're updating.
    if (empty($entityData = $triggerData->getEntityData($from_entity))) {
      throw new Exception("Custom field id $copy_from_field_id is not compatible "
        . "with the entity this rule was triggered for");
    }

    $fromEntityId = $entityData['id'];

    // Target field
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

    // Get new value
    $new_value = $this->getCustomValue($from_entity, $fromEntityId, $copy_from_field_id);
    $existing_value =  $this->getCustomValue($entity, $entityId, $field_id);

    if ($new_value !== $existing_value) {
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
    if (!empty($action_params['copy_from_field_id'])) {
      try {
        $customField = civicrm_api3('CustomField', 'getsingle', [
          'id' => $action_params['copy_from_field_id'],
        ]);
        $customGroup = civicrm_api3('CustomGroup', 'getsingle', [
          'id' => $customField['custom_group_id'],
        ]);
        unset($action_params['copy_from_field_id']);
        $action_params['copy_from_custom_group'] = $customGroup['name'];
        $action_params['copy_from_custom_field'] = $customField['name'];
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
    if (!empty($action_params['copy_from_custom_group'])) {
      try {
        $customField = civicrm_api3('CustomField', 'getsingle', [
          'name' => $action_params['copy_from_custom_field'],
          'custom_group_id' => $action_params['copy_from_custom_group'],
        ]);
        $action_params['copy_from_field_id'] = $customField['id'];
        unset($action_params['copy_from_custom_group']);
        unset($action_params['copy_from_custom_field']);
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/generic/copycustomvalue', $ruleActionId);
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
    // Child classes should override this function

    switch ($context) {
      case 'actionDescriptionWithParams':
        return $this->userFriendlyConditionParams();

      case 'actionDescription':
      case 'actionParamsHelp':
      default:
        return E::ts('This action copies the value of a custom field from any entity in the rule to another custom field.');
    }
  }

  /**
   * @param $field_id
   *
   * @return string
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  private function formatCustomField($field_id) {
    $customField = \Civi\Api4\CustomField::get(FALSE)
      ->addSelect('label', 'custom_group_id')
      ->addWhere('id', '=', $field_id)
      ->execute()
      ->single();
    $customGroup = \Civi\Api4\CustomGroup::get(FALSE)
      ->addSelect('extends:label', 'title')
      ->addWhere('id', '=', $customField['custom_group_id'])
      ->execute()
      ->single();
    return E::ts("Field '%1' (Entity '%3', Group '%2')", [
      1 => $customField['label'],
      2 => $customGroup['title'],
      3 => $customGroup['extends:label'],

    ]);
  }

  /**
   * @return string
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function userFriendlyConditionParams() : string {
    $action_params = $this->getActionParameters();
    if(empty($action_params)) {
      return '';
    }
    return E::ts('Copy from %1 to %2',[
         1 => $this->formatCustomField($action_params['copy_from_field_id']),
         2 => $this->formatCustomField($action_params['field_id'])
        ]);
  }

  /**
   * @param mixed $from_entity
   * @param mixed $fromEntityId
   * @param mixed $copy_from_field_id
   *
   * @return mixed|string
   */
  public function getCustomValue(mixed $from_entity, mixed $fromEntityId, mixed $copy_from_field_id): mixed {
    $new_value = "";
    try {
      $result = civicrm_api3($from_entity, 'getsingle', [
        'id' => $fromEntityId,
        'return' => 'custom_' . $copy_from_field_id
      ]);
      // A field of the type ContactReference returns its value in two formats. custom_<nr> returns the display_name
      // and the custom_<nr>_id returns the contact id. The expression below find the contact_id form ContactReference fields
      // and for other fields it returns the value. If the value is null, it returns "", so it is possible to clear a field.
      $new_value = $result['custom_' . $copy_from_field_id . '_id'] ?? $result['custom_' . $copy_from_field_id] ?? "";
    }
    catch (\CRM_Core_Exception $ex) {
      // Do nothing.
    }
    return $new_value;
  }

}
