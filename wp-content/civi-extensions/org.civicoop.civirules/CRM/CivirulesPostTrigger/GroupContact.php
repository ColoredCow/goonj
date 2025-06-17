<?php

class CRM_CivirulesPostTrigger_GroupContact extends CRM_Civirules_Trigger_Post {

  /**
   * Returns an array of entities on which the trigger reacts
   *
   * @return CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function reactOnEntity() {
    return new CRM_Civirules_TriggerData_EntityDefinition($this->objectName, $this->objectName, $this->getDaoClassName(), 'GroupContact');
  }

  /**
   * Returns an array of additional entities provided in this trigger
   *
   * @return array of CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function getAdditionalEntities() {
    $entities = parent::getAdditionalEntities();
    $entities[] = new CRM_Civirules_TriggerData_EntityDefinition('Group', 'Group', 'CRM_Contact_DAO_Group', 'Group');
    return $entities;
  }

  /**
   * Return the name of the DAO Class. If a dao class does not exist return an empty value
   *
   * @return string
   */
  protected function getDaoClassName() {
    return 'CRM_Contact_DAO_GroupContact';
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
    // $objectRef could be either an array of contact ids or it is an object of type CRM_Contact_BAO_GroupContact.
    // So check with which signature we are dealing.
    if (is_object($objectRef)) {
      // We are dealing with the objectRef is an instance of CRM_Contact_DAO_GroupContact.
      $group = civicrm_api3('Group', 'getsingle', ['id' => $objectRef->group_id]);
      $data = [];
      CRM_Core_DAO::storeValues($objectRef, $data);
      $triggerData = $this->getTriggerDataFromPost($op, $objectName, $objectId, $data, $eventID);
      $triggerData->setEntityData('Group', $group);
      $this->setTriggerData($triggerData);
      parent::triggerTrigger($op, $objectName, $objectId, $objectRef, $eventID);
    } else {
      // We are dealing with an array of contact ids.
      $sql = "SELECT MAX(`id`) AS id, `group_id`, `contact_id`, `status`, `location_id`, `email_id`
            FROM `civicrm_group_contact`
            WHERE `group_id` = %1 AND `contact_id` IN (" . implode(", ", $objectRef) . ")
            GROUP BY `contact_id`";
      $params[1] = [$objectId, 'Integer'];
      $dao = CRM_Core_DAO::executeQuery($sql, $params, TRUE, 'CRM_Contact_DAO_GroupContact');
      $group = civicrm_api3('Group', 'getsingle', ['id' => $objectId]);
      while ($dao->fetch()) {
        $triggerData = $this->getTriggerDataFromPost($op, $objectName, $dao->id, $dao, $eventID);
        $triggerData->setEntityData('Group', $group);
        CRM_Civirules_Engine::triggerRule($this, $triggerData);
      }
    }
  }
}
