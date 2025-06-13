<?php

/**
 * Class for CiviRules Group Contact add related action.
 *
 * Adds a user to a group
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_GroupContact_AddRelated extends CRM_CivirulesActions_GroupContact_GroupContact {

  /**
   * Method to set the api action
   *
   * @return string
   */
  protected function getApiAction() {
    return 'create';
  }

  /**
   * @param \CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return array
   */
  protected function getTargetContacts(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $return = [];
    try {
      $actionParams = $this->getActionParameters();
      foreach($actionParams['rel_type_ids'] as $rel_type_id) {
        $params['relationship_type_id'] = substr($rel_type_id, 4);
        $params['is_active'] = '1';
        $params['options']['limit'] = '0';
        if (str_starts_with($rel_type_id, 'a_b_')) {
          $params['contact_id_a'] = $triggerData->getContactId();
          $return_field = 'contact_id_b';
        }
        else {
          $params['contact_id_b'] = $triggerData->getContactId();
          $return_field = 'contact_id_a';
        }
        $apiReturn = civicrm_api3('Relationship', 'get', $params);
        foreach ($apiReturn['values'] as $value) {
          $return[] = $value[$return_field];
        }
      }
    } catch (\Exception $ex) {
      // Do nothing
    }
    return $return;
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

      foreach ($this->getTargetContacts($triggerData) as $targetContactID) {
        $params['contact_id'] = $targetContactID;
        //execute the action
        $this->executeApiAction($entity, $action, $params);
      }
    }
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a
   * action
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleActionId
   *
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/groupcontact/addrelated', $ruleActionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $params = $this->getActionParameters();
    $relationshipTypes = '';

    $relationshipTypeOptions = CRM_Civirules_Utils::getRelationshipTypes();
    foreach($params['rel_type_ids'] as $rel_type_id) {
      if (strlen($relationshipTypes)) {
        $relationshipTypes .= ', ';
      }
      $relationshipTypes .= $relationshipTypeOptions[$rel_type_id];
    }

    if (!empty($params['group_id'])) {
      try {
        $groupName = civicrm_api3('Group', 'getvalue', [
          'return' => 'title',
          'id' => $params['group_id']
        ]);
      } catch (Exception $e) {
        $groupName = 'INVALID GROUP';
      }
    }

    return E::ts('Add related contacts of type "%1" to Group "%2"', [
      1 => $relationshipTypes,
      2 => $groupName ?? '',
    ]);
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportActionParameters() {
    $action_params = parent::exportActionParameters();
    foreach($action_params['tag_ids'] as $i=>$j) {
      try {
        $action_params['tag_ids'][$i] = civicrm_api3('Tag', 'getvalue', [
          'return' => 'name',
          'id' => $j,
        ]);
      } catch (CiviCRM_API3_Exception $e) {
      }
    }
    foreach($action_params['rel_type_ids'] as $i=>$j) {
      $rel_dir = substr($j, 0, 4);
      $rel_type = substr($j, 4);
      try {
        $action_params['rel_type_ids'][$i] = $rel_dir . civicrm_api3('Tag', 'getvalue', [
          'return' => 'name_a_b',
          'id' => $rel_type,
        ]);
      } catch (CiviCRM_API3_Exception $e) {
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
    foreach($action_params['tag_ids'] as $i=>$j) {
      try {
        $action_params['tag_ids'][$i] = civicrm_api3('Tag', 'getvalue', [
          'return' => 'id',
          'name' => $j,
        ]);
      } catch (CiviCRM_API3_Exception $e) {
      }
    }
    foreach($action_params['rel_type_ids'] as $i=>$j) {
      $rel_dir = substr($j, 0, 4);
      $rel_type = substr($j, 4);
      try {
        $action_params['rel_type_ids'][$i] = $rel_dir . civicrm_api3('Tag', 'getvalue', [
            'return' => 'id',
            'name_a_b' => $rel_type,
          ]);
      } catch (CiviCRM_API3_Exception $e) {
      }
    }
    return parent::importActionParameters($action_params);
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
        return E::ts('All related contacts of the selected relationship types will be added to the selected group.');

      case 'actionParamsHelp':
        return E::ts('All related contacts of the selected relationship types will be added to the selected group.')
          . '<br /><strong>' . E::ts('Relationship type') . ':</strong><br />'
          . E::ts('The relationship type to find target contacts.');
    }

    return $helpText ?? '';
  }

}
