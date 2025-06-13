<?php
/**
 * Class for CiviRules setting/unsetting a contact tag
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

abstract class CRM_CivirulesActions_Tag_Tag extends CRM_CivirulesActions_Generic_Api {

  /**
   * Returns an array with parameters used for processing an action
   *
   * @param array $params
   * @param object CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return array $params
   */
  protected function alterApiParameters($params, CRM_Civirules_TriggerData_TriggerData $triggerData) {
    //this function could be overridden in subclasses to alter parameters to meet certain criteria
    if ($triggerData->getEntity() == 'GroupContact' || $triggerData->getEntity() == 'Membership' || $triggerData->getEntity() == 'EntityTag') {
      $params['entity_id'] = $triggerData->getContactId();
    }
    else {
      $params['entity_id'] = $triggerData->getEntityId();
    }

    switch ($triggerData->getEntity()) {
      case 'Contact':
      case 'GroupContact':
      case 'Membership':
      case 'EntityTag':
        $tableName = 'civicrm_contact';
        break;

      case 'Activity':
        $tableName = 'civicrm_activity';
        break;

      case 'Case':
        $tableName = 'civicrm_case';
        break;

      case 'File':
        $tableName = 'civicrm_file';
        break;

      default:
        $tableName = '';
    }
    $params['entity_table'] = $tableName;
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

    $action_params = $this->getActionParameters();
    $tag_ids = [];
    if (!empty($action_params['tag_id'])) {
      $tag_ids = [$action_params['tag_id']];
    } elseif (!empty($action_params['tag_ids']) && is_array($action_params['tag_ids'])) {
      $tag_ids = $action_params['tag_ids'];
    }
    foreach($tag_ids as $tag_id) {
      $params = [];
      $params['tag_id'] = $tag_id;

      //alter parameters by subclass
      $params = $this->alterApiParameters($params, $triggerData);

      // check if record already exists in db.
      try {
        $id = civicrm_api3('EntityTag', 'getvalue', $params + ['return' => 'id']);
        if (strtolower($action) == 'create') {
          continue;
        }
      }
      catch (CRM_Core_Exception $e) {
        if (strtolower($action) == 'delete') {
          continue;
        }
      }
      //execute the action
      $this->executeApiAction($entity, $action, $params);
    }
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a action
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleActionId
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/tag', $ruleActionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    $params = $this->getActionParameters();
    if (!empty($params['tag_id'])) {
      $tag = civicrm_api3('Tag', 'getvalue', ['return' => 'name', 'id' => $params['tag_id']]);
      return $this->getActionLabel($tag);
    } elseif (!empty($params['tag_ids']) && is_array($params['tag_ids'])) {
      $tags = '';
      foreach($params['tag_ids'] as $tag_id) {
        $tag = civicrm_api3('Tag', 'getvalue', ['return' => 'name', 'id' => $tag_id]);
        if (strlen($tags)) {
          $tags .= ', ';
        }
        $tags .= $tag;
      }
      return $this->getActionLabel($tags);
    }
    return '';
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportActionParameters() {
    $action_params = parent::exportActionParameters();
    if (!empty($params['tag_id'])) {
      try {
        $action_params['tag_id'] = civicrm_api3('Tag', 'getvalue', [
          'return' => 'name',
          'id' => $action_params['tag_id'],
        ]);
      } catch (CRM_Core_Exception $e) {
      }
    } elseif (!empty($params['tag_ids']) && is_array($params['tag_ids'])) {
      foreach ($action_params['tag_ids'] as $i => $j) {
        try {
          $action_params['tag_ids'][$i] = civicrm_api3('Tag', 'getvalue', [
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
    if (!empty($params['tag_id'])) {
      try {
        $action_params['tag_id'] = civicrm_api3('Tag', 'getvalue', [
          'return' => 'id',
          'name' => $action_params['tag_id'],
        ]);
      } catch (CRM_Core_Exception $e) {
      }
    } elseif (!empty($params['tag_ids']) && is_array($params['tag_ids'])) {
      foreach ($action_params['tag_ids'] as $i => $j) {
        try {
          $action_params['tag_ids'][$i] = civicrm_api3('Tag', 'getvalue', [
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
   * Method to set the api entity
   *
   * @return string
   */
  protected function getApiEntity() {
    return 'EntityTag';
  }

  protected function getActionLabel($tag) {
    switch ($this->getApiAction()) {
      case 'create':
        return ts('Add tag (%1)', [1 => $tag]);

      case 'delete':
        return ts('Remove tag (%1)', [1 => $tag]);

    }
    return '';
  }

}
