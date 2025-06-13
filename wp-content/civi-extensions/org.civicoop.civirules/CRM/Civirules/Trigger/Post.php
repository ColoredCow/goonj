<?php
/**
 * Class for CiviRules post trigger handling
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_Civirules_Trigger_Post extends CRM_Civirules_Trigger {

  /**
   * @var string
   */
  protected $objectName;

  /**
   * @var string
   */
  protected $op;

  public static ?CRM_Civirules_TriggerData_TriggerData $triggerDataCache = NULL;

  public function __construct($trigger = NULL) {
    if (isset($trigger)) {
      $this->op = $trigger['op'] ?? NULL;
      $this->objectName = $trigger['object_name'] ?? NULL;
    }
    parent::__construct($trigger);
  }

  /**
   * Getter for object name
   *
   * @return mixed
   */
  public function getObjectName() {
    return $this->objectName;
  }

  /**
   * Getter for op(eration)
   *
   * @return String
   */
  public function getOp() {
    return $this->op;
  }

  /**
   * Returns the name of the trigger data class.
   *
   * This function could be overridden in a child class.
   *
   * @return string
   */
  public function getTriggerDataClassName() {
    if ($this->op === 'edit') {
      return 'CRM_Civirules_TriggerData_Edit';
    }
    return 'CRM_Civirules_TriggerData_Post';
  }

  public function setTriggerId($triggerId) {
    parent::setTriggerId($triggerId);

    $trigger = new CRM_Civirules_BAO_CiviRulesTrigger();
    $trigger->id = $this->triggerId;
    if (!$trigger->find(true)) {
      throw new CRM_Core_Exception('Civirules: could not find trigger with ID: '.$this->triggerId);
    }
    $this->objectName = $trigger->object_name;
    $this->op = $trigger->op;
  }

  /**
   * Returns an array of entities on which the trigger reacts
   *
   * @return CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function reactOnEntity() {
    $entity = CRM_Civirules_Utils_ObjectName::convertToEntity($this->objectName);
    return new CRM_Civirules_TriggerData_EntityDefinition($this->objectName, $entity, $this->getDaoClassName(), $entity);
  }

  /**
   * Return the name of the DAO Class. If a dao class does not exist return an empty value
   *
   * @return string
   */
  protected function getDaoClassName() {
    // @todo remove old method when CiviCRM min version > 5.72
    if (method_exists('CRM_Core_DAO_AllCoreTables', 'getDAONameForEntity')) {
      // getDAONameForEntity added in 5.72
      return CRM_Core_DAO_AllCoreTables::getDAONameForEntity($this->objectName);
    }
    return CRM_Core_DAO_AllCoreTables::getFullName($this->objectName);
  }

  /**
   * Method post
   *
   * @param string $op
   * @param string $objectName
   * @param int $objectId
   * @param object $objectRef
   * @param string $eventID
   */
  public static function post($op, $objectName, $objectId, &$objectRef, $eventID) {
    // Do not trigger when objectName is empty. See issue #19
    if (empty($objectName)) {
      return;
    }
    $extensionConfig = CRM_Civirules_Config::singleton();
    if (!in_array($op, $extensionConfig->getValidTriggerOperations())) {
      return;
    }
    // Delete the cached trigger data in case we modify the same record twice in one process.
    self::$triggerDataCache = NULL;

    // find matching rules for this objectName and op
    $triggers = CRM_Civirules_BAO_CiviRulesRule::findRulesByObjectNameAndOp($objectName, $op);
    foreach($triggers as $trigger) {
      if ($trigger instanceof CRM_Civirules_Trigger_Post) {
        if (self::$triggerDataCache) {
          $trigger->setTriggerData(self::$triggerDataCache);
        }
        $trigger->triggerTrigger($op, $objectName, $objectId, $objectRef, $eventID);
        // Capture the trigger data for the first trigger so we don't have to query again on future triggers.
        if ($trigger->hasTriggerData()) {
          self::$triggerDataCache ??= $trigger->getTriggerData();
        }
      }
    }
  }

  /**
   * Trigger a rule for this trigger
   *
   * @param string $op
   * @param string $objectName
   * @param int $objectId
   * @param object $objectRef
   * @param string $eventID
   */
  public function triggerTrigger($op, $objectName, $objectId, $objectRef, $eventID) {
    if (!$this->hasTriggerData()) {
      // Many classes inherit from CRM_Civirules_Trigger_Post and already set the triggerData
      // Only set it here if not already set by child class
      $this->setTriggerData($this->getTriggerDataFromPost($op, $objectName, $objectId, $objectRef, $eventID));
    }
    parent::triggerTrigger($op, $objectName, $objectId, $objectRef, $eventID);
  }

  /**
   * Get trigger data belonging to this specific post event
   *
   * Sub classes could override this method. E.g. a post on GroupContact doesn't give on object of GroupContact
   * it rather gives an array with contact Id's
   *
   * @param string $op
   * @param string $objectName
   * @param int $objectId
   * @param object $objectRef
   * @param string $eventID
   *
   * @return CRM_Civirules_TriggerData_Edit|CRM_Civirules_TriggerData_Post
   */
  protected function getTriggerDataFromPost($op, $objectName, $objectId, $objectRef, $eventID = NULL) {
    $entity = CRM_Civirules_Utils_ObjectName::convertToEntity($objectName);
    $data = $this->convertObjectRefToDataArray($entity, $objectRef, $objectId);
    if ($op === 'edit' || $op === 'delete') {
      //set also original data with an edit event
      $oldData = CRM_Civirules_Utils_PreData::getPreData($entity, $objectId, $eventID);
      $triggerData = new CRM_Civirules_TriggerData_Edit($entity, $objectId, $data, $oldData);
      $triggerData->setTrigger($this);
    } else {
      $triggerData = new CRM_Civirules_TriggerData_Post($entity, $objectId, $data);
      $triggerData->setTrigger($this);
    }

    $this->alterTriggerData($triggerData);

    return $triggerData;
  }

  /**
   * @param string $entity
   * @param object $objectRef
   * @param int $id
   *
   * @return array
   */
  protected function convertObjectRefToDataArray($entity, $objectRef, $id) {
    //set data
    $data = [];
    if (is_object($objectRef)) {
      CRM_Core_DAO::storeValues($objectRef, $data);
    } elseif (is_array($objectRef)) {
      $data = $objectRef;
    }

    return $data;
  }

  /**
   * Alter the pre data
   *
   * Could be overridden by child classes.
   *
   * @param $data
   * @param $op
   * @param $objectName
   * @param $objectId
   * @param $params
   * @param $eventID
   *
   * @return mixed
   */
  public function alterPreData($data, $op, $objectName, $objectId, $params, $eventID) {
    return $data;
  }

}
