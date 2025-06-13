<?php
/**
 * Class for CiviRules Group Contact Action
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

abstract class CRM_CivirulesActions_GroupContact_GroupContact extends CRM_CivirulesActions_Generic_Api {

  /**
   * Returns an array with parameters used for processing an action
   *
   * @param array $params
   * @param object CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return array $params
   */
  protected function alterApiParameters($params, CRM_Civirules_TriggerData_TriggerData $triggerData) {
    // this function could be overridden in subclasses to alter parameters to meet certain criteria
    $params['contact_id'] = $triggerData->getContactId();
    return $params;
  }

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
    } elseif (!empty($actionParams['group_ids']) && is_array($actionParams['group_ids'])) {
      $groupIds = $actionParams['group_ids'];
    }
    foreach($groupIds as $groupId) {
      $params = [];
      $params['group_id'] = $groupId;

      //alter parameters by subclass
      $params = $this->alterApiParameters($params, $triggerData);

      //execute the action
      $this->executeApiAction($entity, $action, $params);
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
    if (!empty($actionParams['group_id'])) {
      try {
        $action_params['group_id'] = civicrm_api3('Group', 'getvalue', [
          'return' => 'name',
          'id' => $action_params['group_id'],
        ]);
      } catch (CiviCRM_API3_Exception $e) {
      }
    } elseif (!empty($actionParams['group_ids']) && is_array($actionParams['group_ids'])) {
      foreach ($action_params['group_ids'] as $i => $j) {
        try {
          $action_params['group_ids'][$i] = civicrm_api3('Group', 'getvalue', [
            'return' => 'name',
            'id' => $j,
          ]);
        } catch (CiviCRM_API3_Exception $e) {
        }
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
    if (!empty($actionParams['group_id'])) {
      try {
        $action_params['group_id'] = civicrm_api3('Group', 'getvalue', [
          'return' => 'id',
          'name' => $action_params['group_id'],
        ]);
      } catch (CiviCRM_API3_Exception $e) {
      }
    } elseif (!empty($actionParams['group_ids']) && is_array($actionParams['group_ids'])) {
      foreach ($action_params['group_ids'] as $i => $j) {
        try {
          $action_params['group_ids'][$i] = civicrm_api3('Group', 'getvalue', [
            'return' => 'id',
            'name' => $j,
          ]);
        } catch (CiviCRM_API3_Exception $e) {
        }
      }
    }
    return parent::importActionParameters($action_params);
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a action
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleActionId
   *
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/groupcontact', $ruleActionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    $params = $this->getActionParameters();
    if (!empty($params['group_id'])) {
      try {
        $group = civicrm_api3('Group', 'getvalue', [
          'return' => 'title',
          'id' => $params['group_id']
        ]);
        return $this->getActionLabel($group);
      } catch (Exception $e) {
        return '';
      }
    } elseif (!empty($params['group_ids']) && is_array($params['group_ids'])) {
      $groups = '';
      foreach($params['group_ids'] as $group_id) {
        try {
          $group = civicrm_api3('Group', 'getvalue', [
            'return' => 'title',
            'id' => $group_id
          ]);
          if (strlen($groups)) {
            $groups .= ', ';
          }
          $groups .= $group;
        } catch (Exception $e) {
          // Do nothing.
        }
      }
      return $this->getActionLabel($groups);
    }
    return '';
  }

  /**
   * Method to set the api entity
   *
   * @return string
   */
  protected function getApiEntity() {
    return 'GroupContact';
  }

  protected function getActionLabel($group) {
    switch ($this->getApiAction()) {
      case 'create':
        return ts('Add contact to group(s): %1', [
          1 => $group
        ]);

      case 'delete':
        return ts('Remove contact from group(s): %1', [
          1 => $group
        ]);
    }
    return '';
  }

}
