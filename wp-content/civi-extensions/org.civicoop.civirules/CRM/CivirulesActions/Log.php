<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Log extends CRM_Civirules_Action {

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    \Civi::log('civirules')->info('Rule ID: ' . $this->getRuleId()
      . ' was triggered and actions executed. Entity data: '
      . var_export($triggerData->getAllEntityData(), TRUE));
  }

  public function getExtraDataInputUrl($ruleActionId) {
    return FALSE;
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
      case 'actionDescription':
      case 'actionParamsHelp':
      default:
        return E::ts('Writes a log entry to the CiviCRM logs with entity data (useful for troubleshooting/testing rules)');
    }
  }

}
