<?php

/**
 * Class for CiviRules Group Contact remove action.
 *
 * Adds a user to a group
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */
class CRM_CivirulesActions_GroupContact_Remove extends CRM_CivirulesActions_GroupContact_GroupContact {

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $entity = $this->getApiEntity();
    $action = $this->getApiAction();
    $contactId = $triggerData->getContactId();

    $action_params = $this->getActionParameters();
    $group_ids = [];
    if (!empty($action_params['group_id'])) {
      $group_ids = [$action_params['group_id']];
    } elseif (!empty($action_params['group_ids']) && is_array($action_params['group_ids'])) {
      $group_ids = $action_params['group_ids'];
    }
    foreach($group_ids as $group_id) {
      if (CRM_CivirulesConditions_Utils_GroupContact::isContactInGroup($contactId, $group_id)) {
        $params = [];
        $params['group_id'] = $group_id;
        $params['status'] = 'Removed';

        //alter parameters by subclass
        $params = $this->alterApiParameters($params, $triggerData);

        //execute the action
        $this->executeApiAction($entity, $action, $params);
      }
    }
  }
}
