<?php
/**
 * Class for CiviRules Set Custom Field
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Generic_SetField extends CRM_Civirules_Action {

  /**
   * Method processAction to execute the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   *
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $action_params = $this->getActionParameters();
    $fieldName = $action_params['field'];
    $entity = $action_params['entity'];
    $entityId = $triggerData->getEntityData($entity)['id'];
    $value = $action_params['value'];

    // Ensure the new value isn't the same, to prevent unnecessary writes and avoid infinite loops.
    $exists = (bool) civicrm_api4($entity, 'get', [
      'select' => [$fieldName],
      'where' => [
        ['id', '=', $entityId],
        [$fieldName, '=', $value],
      ],
      'checkPermissions' => FALSE,
    ])->count();

    if (!$exists) {
      // set the new value using the API
      civicrm_api4($entity, 'update', [
        'values' => [
          $fieldName => $value,
        ],
        'where' => [['id', '=', $entityId]],
        'checkPermissions' => FALSE,
      ]);
    }

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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/set_field_value', $ruleActionId);
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
        return E::ts('This action will set the field to the provided value.');

      case 'actionParamsHelp':
        return E::ts('This action will set the field to the provided value.');
    }

    return $helpText ?? '';
  }

  /**
   * Returns a user friendly text explaining the action.
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $params = $this->getActionParameters();
    $field = civicrm_api4($params['entity'], 'getfields', [
      'where' => [
        ['name', '=', $params['field']],
      ],
      'select' => [
        'label', 'options',
      ],
      'loadOptions' => TRUE,
      'checkPermissions' => FALSE,
    ])->first();
    return E::ts('Set %1 field %2 to value "%3"', [
      1 => $params['entity'],
      2 => $field['label'],
      3 => $field['options'][$params['value']] ?? $params['value'],
    ]);
  }

}
