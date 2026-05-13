<?php
/**
 * Abstract Class for CiviRules action
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

abstract class CRM_Civirules_Action {

  protected array $ruleAction = [];

  protected array $action = [];

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @throws Exception
   */
  abstract public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData);

  /**
   * You could override this method to create a delay for your action
   *
   * You might have a specific action which is Send Thank you and which
   * includes sending thank you SMS to the donor but only between office hours
   *
   * If you have a delay you should return a DateTime object with a future date and time
   * for when this action should be processed.
   *
   * If you don't have a delay you should return false
   *
   * @param DateTime $date the current scheduled date/time
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return bool|DateTime
   */
  public function delayTo(DateTime $date, CRM_Civirules_TriggerData_TriggerData $triggerData) {
    return FALSE;
  }

  /**
   * Method to set RuleActionData
   *
   * @param $ruleAction
   */
  public function setRuleActionData($ruleAction) {
    $this->ruleAction = [];
    if (is_array($ruleAction)) {
      $this->ruleAction = $ruleAction;
    }
  }

  /**
   * Method to set actionData
   *
   * @param $action
   */
  public function setActionData($action) {
    $this->action = $action;
  }


  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportActionParameters() {
    return $this->getActionParameters();
  }

  /**
   * Returns condition data as an array and ready for import.
   * E.g. replace name for ids.
   *
   * @return string
   */
  public function importActionParameters($action_params=null) {
    if (!empty($action_params)) {
      return serialize($action_params);
    }
    return '';
  }

  /**
   * Convert parameters to an array of parameters
   *
   * @return array
   */
  protected function getActionParameters() {
    $params = [];
    if (!empty($this->ruleAction['action_params'])) {
      $params = unserialize($this->ruleAction['action_params']);
    }
    return $params;
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a action
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleActionId
   * @return bool|string
   */
  abstract public function getExtraDataInputUrl($ruleActionId);

  /**
   * @param string $url
   * @param int $ruleActionID
   *
   * @return string
   */
  public function getFormattedExtraDataInputUrl(string $url, int $ruleActionID): string {
    return CRM_Utils_System::url($url, 'rule_action_id=' . $ruleActionID, FALSE, NULL, FALSE, FALSE, TRUE);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    return '';
  }

  /**
   * This function validates whether this action works with the selected trigger.
   *
   * This function could be overridden in child classes to provide additional validation
   * whether an action is possible in the current setup.
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   *
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    return TRUE;
  }

  /**
   * Logs a message to the logger
   *
   * @param $message
   * @param \CRM_Civirules_TriggerData_TriggerData|NULL $triggerData
   * @param string $level Should be one of \Psr\Log\LogLevel
   */
  protected function logAction($message, ?CRM_Civirules_TriggerData_TriggerData $triggerData=null, $level=\Psr\Log\LogLevel::INFO) {
    $context = [];
    $context['message'] = $message;
    $context['rule_id'] = $this->ruleAction['rule_id'];
    $rule = new CRM_Civirules_BAO_Rule();
    $rule->id = $this->ruleAction['rule_id'];
    $context['rule_title'] = '';
    if ($rule->find(TRUE)) {
      $context['rule_title'] = $rule->label;
    }
    $context['rule_action_id'] = $this->ruleAction['id'];
    $context['action_label'] = CRM_Civirules_BAO_Action::getActionLabelWithId($this->ruleAction['action_id']);
    $context['action_parameters'] = $this->userFriendlyConditionParams();
    $context['contact_id'] = $triggerData ? $triggerData->getContactId() : - 1;
    $msg = E::ts(
      "Rule: '%1' with id %2: Action: %3 with id %4: %5",
      [
        1 => $context['rule_title'],
        2 => $context['rule_id'],
        3 => $context['action_label'],
        4 => $context['rule_action_id'],
        5 => $message,
      ]
    );
    if ($context['contact_id'] > 0) {
      $msg .= ": " . E::ts('For contact: %1', [1 => $context['contact_id']]);
    }
    CRM_Civirules_Utils_LoggerFactory::log($msg, $context, $level);
  }

  /**
   * @return int
   */
  public function getRuleId() {
    return $this->ruleAction['rule_id'];
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
        // Historically getHelpText() was on the form class.
        // But we have no way to get the form class - only the path via getExtraDataInputUrl()
        // The Form *does* have access to the action class via $this->actionClass so if getHelpText()
        //   is on the actionClass we can just do $this->actionClass->getHelpText().

        // getHelpText() doesn't exist on action class.
        // Try to get Form class for action and see if getHelpText() exists there
        $classBits = explode('_', get_class($this));

        $formClass = $classBits[0] . '_' . $classBits[1] . '_Form';
        for ($i = 2; $i < count($classBits); $i++) {
          $formClass .= '_' . $classBits[$i];
        }
        if (class_exists($formClass) && method_exists($formClass, 'getHelpText')) {
          $helpText = (new $formClass())->getHelpText();
        }
    }

    return $helpText ?? '';
  }

}
