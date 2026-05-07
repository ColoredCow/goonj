<?php

/**
 * Class for CiviRules Group Contact remove related action.
 *
 * Removes a related user from a group
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_GroupContact_RemoveRelated extends CRM_CivirulesActions_GroupContact_RelatedGroupContact {

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $entity = $this->getApiEntity();
    $action = $this->getApiAction();

    $actionParams = $this->getActionParameters();
    $groupIds = [];
    if (!empty($actionParams['group_id'])) {
      $groupIds = [$actionParams['group_id']];
    }
    elseif (!empty($actionParams['group_ids']) && is_array($actionParams['group_ids'])) {
      $groupIds = $actionParams['group_ids'];
    }
    foreach ($groupIds as $groupId) {
      $params['group_id'] = $groupId;

      foreach ($this->getTargetContacts($triggerData) as $targetContactID) {
        $params['contact_id'] = $targetContactID;
        $params['status'] = 'Removed';
        //execute the action
        $this->executeApiAction($entity, $action, $params);
      }
    }
  }

}
