<?php
/**
 * @author Shane Bill (SymbioTIC Coop) <shane@symbiotic.coop>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */
use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_ContributionRecur_CancelRecurring extends CRM_CivirulesActions_Generic_Api {

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
    $date = new \DateTime('now');

    // Add the API4 params
    $parameters['where'][] = ['id', '=', $contributionRecurData['id']];
    $parameters['values'] = [
      'contribution_status_id' => $actionParams['status_id'],
      'cancel_reason' => $actionParams['cancel_reason'],
      'cancel_date' => $date->format('Y-m-d H:m:s'),
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/contributionrecur_cancel_recurring', $ruleActionId);
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
    $params = $this->getActionParameters();
    $status = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('value', 'label')
      ->addWhere('value', '=', $params['status_id'])
      ->addWhere('option_group_id:name', '=', 'contribution_recur_status')
      ->execute()
      ->first()['label'];
    $return .= E::ts("Cancel Recurring Contribution (Status: %1, Reason: %2)", [1 => $status, 2 => $params['cancel_reason']]);
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
    else {
      // execute the action
      $this->executeApiAction($entity, $action, $params);
    }
  }


}
