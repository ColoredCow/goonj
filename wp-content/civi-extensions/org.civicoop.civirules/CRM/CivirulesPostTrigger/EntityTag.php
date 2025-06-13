<?php

class CRM_CivirulesPostTrigger_EntityTag extends CRM_Civirules_Trigger_Post {

  /**
   * Returns an array of entities on which the trigger reacts
   *
   * @return CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function reactOnEntity() {
    return new CRM_Civirules_TriggerData_EntityDefinition($this->objectName, $this->objectName, $this->getDaoClassName(), 'EntityTag');
  }

  /**
   * Return the name of the DAO Class. If a dao class does not exist return an empty value
   *
   * @return string
   */
  protected function getDaoClassName() {
    return 'CRM_Core_DAO_EntityTag';
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
    $entity = CRM_Civirules_Utils_ObjectName::convertToEntity($objectName);
    if (empty($objectId) && !empty($objectRef->entity_id) && !empty($objectRef->entity_table)) {
      $objectId = CRM_Civirules_Utils_PreData::getEntityTagId($objectRef->entity_table, $objectRef->entity_id);
    }

    $entityTags = array();
    // $objectRef is either an object or an array.
    if (is_object($objectRef)) {
      $entityTags[] = [
        'id' => $objectId,
        'tag_id' => $objectRef->tag_id,
        'entity_id' => $objectRef->entity_id,
        'entity_table' => $objectRef->entity_table,
        'contact_id' => $objectRef->entity_id,
      ];
    } elseif (is_array($objectRef)) {
      foreach($objectRef['0'] as $entity_id) {
        $entityTags[] = [
          'tag_id' => $objectId,
          'entity_id' => $entity_id,
          'entity_table' => $objectRef['1'],
          'contact_id' => $entity_id,
        ];
      }
    }

    foreach($entityTags as $entityTag) {
      //only execute entity tag for setting or removing tags from contacts
      //because we need to know the contact id for the trigger engine
      //and we only know this when the tag is on contact level
      if ($entityTag['entity_table'] != 'civicrm_contact') {
        continue;
      }
      if ($op === 'edit' || $op === 'delete') {
        //set also original data with an edit event
        $oldData = CRM_Civirules_Utils_PreData::getPreData($entity, $objectId, $eventID);
        $triggerData = new CRM_Civirules_TriggerData_Edit($entity, $objectId, $entityTag, $oldData);
        $triggerData->setTrigger($this);
      } else {
        $triggerData = new CRM_Civirules_TriggerData_Post($entity, $objectId, $entityTag);
        $triggerData->setTrigger($this);
      }
      $this->setTriggerData($triggerData);
      parent::triggerTrigger($op, $objectName, $objectId, $objectRef, $eventID);
    }

  }

}
