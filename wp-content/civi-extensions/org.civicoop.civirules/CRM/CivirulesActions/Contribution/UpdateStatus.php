<?php

/**
 * Class for CiviRules Update Contribution Status for Contribution Action
 */
class CRM_CivirulesActions_Contribution_UpdateStatus extends CRM_CivirulesActions_Generic_Api {

  protected function getApiEntity() {
    return 'Contribution';
  }

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
    $data = $triggerData->getEntityData('Contribution');
    if (empty($data)) {
      return $parameters;
    }
    // Reset the params - API4 does not accept invalid params
    $parameters = [];
    // Add the API4 params
    $parameters['where'][] = ['id', '=', $data['id']];
    $parameters['values']['contribution_status_id'] = $actionParams['status_id'];
    $parameters['values']['cancel_date'] = date('Y-m-d H:i:s');
    if (!empty($actionParams['cancel_reason'])) {
      $parameters['values']['cancel_reason'] = $actionParams['cancel_reason'];
    }
    return $parameters;
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a action
   *
   * @param int $ruleActionId
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/contribution_update_status', $ruleActionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @throws \CiviCRM_API3_Exception
   */
  public function userFriendlyConditionParams() {
    $return = '';
    $params = $this->getActionParameters();
    $status = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('value', 'label')
      ->addWhere('value', '=', $params['status_id'])
      ->addWhere('option_group_id:name', '=', 'contribution_status')
      ->execute()
      ->first()['label'];
    $return .= ts("Set Status to: %1", [1 => $status]);
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
    if (isset($entities['Contribution'])) {
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
    $params = $this->alterApiParameters($params, $triggerData);
    $data = $triggerData->getEntityData('Contribution');

    if (empty($data['id'])) {
      $this->logAction('Could not find a contribution to update');
    }
    else {
      $this->executeApiAction($entity, $action, $params);
    }
  }

}
