<?php
/**
 * Copyright (C) 2021  Jaap Jansma (jaap.jansma@civicoop.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Activity_AddTargetContact extends CRM_Civirules_Action {

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @throws Exception
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $target_record_id = 3;
    $possible_targets = $this->getTargetContacts($triggerData);
    $activity = $triggerData->getEntityData('Activity');
    if (count($possible_targets)) {
      $strImplodedTargetContacts = implode(", ", $possible_targets);
      $sql = "
        SELECT `civicrm_contact`.`id` as `contact_id`
        FROM `civicrm_contact`
        WHERE `id` IN ({$strImplodedTargetContacts})
        AND `id` NOT IN (
            SELECT `contact_id`
            FROM `civicrm_activity_contact`
            WHERE `record_type_id` = %1 AND `activity_id` = %2
        )
      ";
      $sqlParams[1] = [$target_record_id, 'Integer'];
      $sqlParams[2] = [$activity['id'], 'Integer'];
      $dao = \CRM_Core_DAO::executeQuery($sql, $sqlParams);
      while($dao->fetch()) {
        civicrm_api3('ActivityContact', 'create', [
          'contact_id' => $dao->contact_id,
          'activity_id' => $activity['id'],
          'record_type_id' => $target_record_id
        ]);
      }
    }
  }

  protected function getTargetContacts(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $return = [];
    try {
      $actionParams = $this->getActionParameters();
      if (isset($actionParams['rel_type_id'])) {
        $params['relationship_type_id'] = substr($actionParams['rel_type_id'], 4);
        $params['is_active'] = '1';
        $params['options']['limit'] = '0';
        if (str_starts_with($actionParams['rel_type_id'], 'a_b_')) {
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
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportActionParameters() {
    $action_params = parent::exportActionParameters();
    if (isset($action_params['rel_type_id'])) {
      $rel_type_id = substr($action_params['rel_type_id'], 4);
      $rel_dir = substr($action_params['rel_type_id'], 0, 4);
      try {
        $rel_type_id = civicrm_api3('RelationshipType', 'getvalue', [
          'return' => 'name_a_b',
          'id' => $rel_type_id,
        ]);
      } catch (CRM_Core_Exception $e) {
      }
      $action_params['rel_type_id'] .= $rel_dir . $rel_type_id;
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
    if (isset($action_params['rel_type_id'])) {
      $rel_type_id = substr($action_params['rel_type_id'], 4);
      $rel_dir = substr($action_params['rel_type_id'], 0, 4);
      try {
        $rel_type_id = civicrm_api3('RelationshipType', 'getvalue', [
          'return' => 'id',
          'name_a_b' => $rel_type_id,
        ]);
      } catch (CRM_Core_Exception $e) {
      }
      $action_params['rel_type_id'] .= $rel_dir . $rel_type_id;
    }
    return parent::importActionParameters($action_params);
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
   * $access public
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/activity_add_target_contact', $ruleActionId);
  }


  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    try {
      $actionParams = $this->getActionParameters();
      if (isset($actionParams['rel_type_id'])) {
        $params['id'] = substr($actionParams['rel_type_id'], 4);
        if (str_starts_with($actionParams['rel_type_id'], 'a_b_')) {
          $params['return'] = 'label_a_b';
        }
        else {
          $params['return'] = 'label_b_a';
        }
        $label = civicrm_api3('RelationshipType', 'getvalue', $params);
        return E::ts('Set to related contact: %1', [1=> $label]);
      }
    } catch (\Exception $ex) {
      return '';
    }
  }

  /**
   * This function validates whether this action works with the selected
   * trigger.
   *
   * This function could be overriden in child classes to provide additional
   * validation whether an action is possible in the current setup.
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   *
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    if ($trigger->doesProvideEntity('Activity')) {
      return TRUE;
    }
    return FALSE;
  }




}
