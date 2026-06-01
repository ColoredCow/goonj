<?php

/**
 * Class for CiviRules Group Contact remove action.
 *
 * Adds a user to a group
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_GroupContact_RebuildSmartGroup extends CRM_Civirules_Action {

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $action_params = $this->getActionParameters();
    $group_ids = [];
    if (!empty($action_params['group_id'])) {
      $group_ids = [$action_params['group_id']];
    } elseif (!empty($action_params['group_ids']) && is_array($action_params['group_ids'])) {
      $group_ids = $action_params['group_ids'];
    }
    if (count($group_ids)) {
      // Reset the cache date for this smart group.
      // By setting this to null it would be picked up for rebuilding.
      CRM_Core_DAO::executeQuery("UPDATE civicrm_group g
        SET cache_date = NULL
        WHERE   ( g.saved_search_id IS NOT NULL OR g.children IS NOT NULL )
        AND     g.is_active = 1
        AND g.id IN (" . implode(",", $group_ids) .")");

      CRM_Contact_BAO_GroupContactCache::loadAll($group_ids, 0);
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
      } catch (CRM_Core_Exception $e) {
      }
    } elseif (!empty($actionParams['group_ids']) && is_array($actionParams['group_ids'])) {
      foreach ($action_params['group_ids'] as $i => $j) {
        try {
          $action_params['group_ids'][$i] = civicrm_api3('Group', 'getvalue', [
            'return' => 'name',
            'id' => $j,
          ]);
        } catch (CRM_Core_Exception $e) {
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
      } catch (CRM_Core_Exception $e) {
      }
    } elseif (!empty($actionParams['group_ids']) && is_array($actionParams['group_ids'])) {
      foreach ($action_params['group_ids'] as $i => $j) {
        try {
          $action_params['group_ids'][$i] = civicrm_api3('Group', 'getvalue', [
            'return' => 'id',
            'name' => $j,
          ]);
        } catch (CRM_Core_Exception $e) {
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
        return E::ts('Rebuild Smart Group: %1', [1=>$group]);
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
      return E::ts('Rebuild Smart Group(s): %1', [1=>$groups]);
    }
    return '';
  }
}
