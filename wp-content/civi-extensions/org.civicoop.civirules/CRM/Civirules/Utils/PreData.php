<?php

use Civi\Api4\Service\Schema\Joinable\CustomGroupJoinable;

class CRM_Civirules_Utils_PreData {

  /**
   * Data set in pre and used for compare which field is changed
   *
   * @var array $preData
   */
  protected static $preData = array();

  /**
   * Method pre to store the entity data before the data in the database is changed
   * for the edit operation
   *
   * @param string $op
   * @param string $objectName
   * @param int $objectId
   * @param array $params
   * @param string $eventID
   */
  public static function pre($op, $objectName, $objectId, $params, $eventID) {
    // Do not trigger when objectName is empty. See issue #19
    if (empty($objectName)) {
      return;
    }
    $nonPreEntities = array('GroupContact', 'ActionLog');
    if (($op != 'edit' && $op != 'delete') || in_array($objectName, $nonPreEntities)) {
      return;
    }
    // Don't execute this if no rules exist for this entity.
    $triggers = CRM_Civirules_BAO_Rule::findRulesByObjectNameAndOp($objectName, $op);
    if (empty($triggers)) {
      return;
    }

    /**
     * Not every object in CiviCRM sets the object id in the pre hook
     * But we need this to fetch the current data state from the database.
     * So we check if the ID is in the params array and if so we use that id
     * for fetching the data
     *
     */
    $id = $objectId;
    if (empty($id) && isset($params['id']) && !empty($params['id'])) {
      $id = $params['id'];
    } elseif ($objectName == 'EntityTag') {
      try {
        $id = civicrm_api3('EntityTag', 'getvalue', [
          'return' => 'id',
          'entity_id' => $params['entity_id'],
          'entity_table' => $params['entity_table'],
        ]);
      }
      catch (CRM_Core_Exception $e) {
        return;
      }
    }

    if (empty($id)) {
      return;
    }

    //retrieve data as it is currently in the database
    $entity = CRM_Civirules_Utils_ObjectName::convertToEntity($objectName);
    if (!$entity) {
      return;
    }
    try {
      $data = civicrm_api3($entity, 'getsingle', array('id' => $id));
    } catch (Exception $e) {
      return;
    }
    // add custom data fields
    try {
      $customData = civicrm_api3('CustomValue', 'get', array(
        'sequential' => 1,
        'entity_id' => $id,
        'entity_table' => ucfirst($entity),
      ));
    } catch (Exception $e ) {
      $customData = array();
    }
    if ( empty($customData['is_error']) && ! empty($customData['count']) ) {
      foreach ($customData['values'] as $customField ) {
        $data['custom_' . $customField['id']] = $customField['latest'];
      }
    }

    foreach($triggers as $trigger) {
      if ($trigger instanceof CRM_Civirules_Trigger_Post) {
        $data = $trigger->alterPreData($data, $op, $objectName, $objectId, $params, $eventID);
      }
    }

    self::setPreData($entity, $id, $data, $eventID);
  }

  /**
   * Retrieve the original data when the customPre hook is called.
   *
   * @param $op
   * @param $groupID
   * @param $entityID
   * @param $params
   * @param $eventID
   */
  public static function customPre($op, $groupID, $entityID, $params, $eventID=1) {
    // We use api version 3 here as there is no api v4 for the CustomValue table.
    if ($op != 'edit' && $op != 'delete') {
      return;
    }
    $config = \Civi\CiviRules\Config\ConfigContainer::getInstance();
    $custom_group = $config->getCustomGroupById($groupID);
    if (version_compare(CRM_Utils_System::version(), '5.67', '<')) {
      $entity = CustomGroupJoinable::getEntityFromExtends($custom_group['extends']);
    }
    else {
      $entity = CRM_Core_BAO_CustomGroup::getEntityFromExtends($custom_group['extends']);
    }
    $data = [];
    if (!isset(self::$preData[$entity][$entityID][$eventID])) {
      try {
        $data = civicrm_api3($entity, 'getsingle', ['id' => $entityID]);
      } catch (Exception $e) {
        // Do nothing.
      }
      $customDataApiResult = civicrm_api3('CustomValue', 'get', [
        'entity_id' => $entityID,
        'entity_table' => $entity
      ]);
      foreach ($customDataApiResult['values'] as $customField) {
        $data['custom_' . $customField['id']] = $customField['latest'];
      }
    }
    self::setPreData($entity, $entityID, $data, $eventID);
  }

  /**
   * Method to set the pre operation data
   *
   * @param string $entity
   * @param int $entityId
   * @param array $data
   * @access protected
   * @static
   */
  protected static function setPreData($entity, $entityId, $data, $eventID) {
    self::$preData[$entity][$entityId][$eventID] = $data;
  }

  /**
   * Method to get the pre operation data
   *
   * @param string $entity
   * @param int $entityId
   * @return array
   * @access protected
   * @static
   */
  public static function getPreData($entity, $entityId, $eventID) {
    $return = [];
    $entityNames = [$entity];
    switch ($entity) {
      case 'Contact':
        $entityNames = ['Contact', 'Individual', 'Organization', 'Household'];
        break;
      case 'Individual':
        $entityNames = ['Contact', 'Individual'];
        break;
      case 'Organization':
        $entityNames = ['Contact', 'Organization'];
        break;
      case 'Household':
        $entityNames = ['Contact', 'Household'];
        break;
    }
    foreach ($entityNames as $entity) {
      if (isset(self::$preData[$entity][$entityId][$eventID])) {
        $return = array_merge($return, self::$preData[$entity][$entityId][$eventID]);
      }
    }
    return $return;
  }

  /**
   * Little Hack function to find the ID of the entity tag record later on.
   *
   * @param string $entity_table
   * @param int $entity_id
   *
   * @return int|null
   */
  public static function getEntityTagId(string $entity_table, int $entity_id):? int {
    if (isset(self::$preData['EntityTag'])) {
      foreach(self::$preData['EntityTag'] as $id => $entityTags) {
        foreach($entityTags as $entityTag) {
          if (isset($entityTag['entity_table']) && $entityTag['entity_table'] == $entity_table && isset($entityTag['entity_id']) && $entityTag['entity_id'] == $entity_id) {
            return $id;
          }
        }
      }
    }
    return null;
  }

}
