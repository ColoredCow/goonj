<?php
/**
 * @author Shane Bill (SymbioTIC Coop) <shane@symbiotic.coop>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */
use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_ContributionRecur_ChangeNextScheduleDate extends CRM_CivirulesActions_Generic_Api {

  /**
   * Method to get the api entity to process in this CiviRule action
   *
   */
  protected function getApiEntity() {
    return 'ContributionRecur';
  }

  /**
   * Method to get the api action to process in this CiviRule action
   *
   */
  protected function getApiAction() {
    return 'Update';
  }

  protected function getApiVersion(): int {
    return 4;
  }

  /**
   * Returns an array with parameters used for processing an action
   *
   * @param array $parameters
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return array
   */
  protected function alterApiParameters(array $parameters, CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $actionParams = $this->getActionParameters();
    $contributionRecurData = $triggerData->getEntityData('ContributionRecur');
    if (empty($contributionRecurData)) {
      return $parameters;
    }
    // Reset the params - API4 does not accept invalid params
    $parameters = [];
    $date_raw = '';
    if (!empty($actionParams['schedule_option'])) {
      $date_raw = $actionParams['schedule_option'];
    }
    elseif (!empty($actionParams['schedule_date'])) {
      $date_raw = $actionParams['schedule_date'];
    }
    else {
      return $parameters;
    }

    // Add the API4 params
    $date = new \DateTime($date_raw);
    $parameters['where'][] = ['id', '=', $contributionRecurData['id']];
    $parameters['values'] = [
      'next_sched_contribution_date' => $date->format('Y-m-d H:m:s'),
    ];
    return $parameters;
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a action
   *
   * Return FALSE if you do not need extra data input
   *
   * @param int $ruleActionId
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/contributionrecur_change_nextscheduledate', $ruleActionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @throws \CRM_Core_Exception
   */
  public function userFriendlyConditionParams() {
    $return = '';
    $actionParams = $this->getActionParameters();

    $date_raw = '';
    $date_type = '';
    if (!empty($actionParams['schedule_option'])) {
      $date_type = $actionParams['schedule_option'];
      $date_raw = $actionParams['schedule_option'];
    }
    elseif (!empty($actionParams['schedule_date'])) {
      $date_type = $actionParams['schedule_date'];
      $date_raw = $actionParams['schedule_date'];
    }
    $date = new \DateTime($date_raw);
    $return .= E::ts("Change Next Scheduled Date for Recurring Contribution (%1 gives %2)", [1 => $date_type, 2 => $date->format('Y-m-d H:m
:s')]);
    return $return;
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
    $entities = $trigger->getProvidedEntities();
    if (isset($entities['ContributionRecur'])) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $entity = $this->getApiEntity();
    $action = $this->getApiAction();

    $params = $this->getActionParameters();

    // alter parameters by subclass
    $params = $this->alterApiParameters($params, $triggerData);

    $contributionRecurData = $triggerData->getEntityData('ContributionRecur');
    if (empty($contributionRecurData['id'])) {
      $this->logAction('No Recur ID found to update status of ContributionRecur');
    }
    elseif (empty($params['values'])) {
      $this->logAction('No Schedule date or option chosen');
    }
    else {
      // execute the action
      $this->executeApiAction($entity, $action, $params);
    }
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
        return E::ts('This action will change the next scheduled date for a recurring contribution.');

      case 'actionParamsHelp':
        return E::ts('This action will change the next scheduled date for a recurring contribution. Do not include both fields as it priori
tizes the first <strong>options</strong>.
<br/<br/>
It is important to choose either a <strong>relative option</strong> or enter your own date. You can also use the syntax of <a href="https:/
/www.php.net/manual/en/function.strtotime.php" target="_blank">strtotime</a> to derive better dates.
<br /><br />
For example: +1 day, +1 week, etc.');
    }

    return $helpText ?? '';
 }

}
