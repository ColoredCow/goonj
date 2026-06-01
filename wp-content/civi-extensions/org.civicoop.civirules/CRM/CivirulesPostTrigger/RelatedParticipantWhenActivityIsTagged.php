<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesPostTrigger_RelatedParticipantWhenActivityIsTagged extends CRM_CivirulesPostTrigger_EntityTag {

  /**
   * Trigger a rule for this trigger
   *
   * @param string $op
   * @param string $objectName
   * @param int $objectId
   * @param object $objectRef
   * @param string $eventID
   */
  public function triggerTrigger($op, $objectName, $objectId, $objectRef, $eventID = NULL) {
    $entity = CRM_Civirules_Utils_ObjectName::convertToEntity($objectName);

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
      if ($entityTag['entity_table'] != 'civicrm_activity') {
        continue;
      }

      if (isset($this->triggerParams['tag_ids']) && is_array($this->triggerParams['tag_ids']) && count(isset($this->triggerParams['tag_ids']))) {
        if (!in_array($entityTag['tag_id'], $this->triggerParams['tag_ids'])) {
          continue;
        }
      }

      $activity = civicrm_api3('Activity', 'getsingle', ['id' => $entityTag['entity_id']]);
      if (isset($this->triggerParams['activity_type_id']) && is_array($this->triggerParams['activity_type_id']) && count(isset($this->triggerParams['activity_type_id']))) {
        if (!in_array($activity['activity_type_id'], $this->triggerParams['activity_type_id'])) {
          continue;
        }
      }

      $triggerData = new CRM_Civirules_TriggerData_Post($entity, $objectId, $entityTag, $this);
      $triggerData->setEntityData('Activity', $activity);

      $custom_field_id = $this->triggerParams['event_id_custom_field'];
      if (empty($activity['custom_'.$custom_field_id])) {
        continue;
      }
      $event_id = $activity['custom_'.$custom_field_id];
      $event = civicrm_api3('Event', 'getsingle', ['id' => $event_id]);
      $triggerData->setEntityData('Event', $event);

      $sql = "SELECT `p`.* FROM `civicrm_participant` `p` WHERE p.event_id = %1";
      $params[1] = array($event_id, 'Integer');
      $params[2] = array($this->ruleId, 'Integer');
      $dao = CRM_Core_DAO::executeQuery($sql, $params, true, 'CRM_Event_DAO_Participant');

      while ($dao->fetch()) {
        $participant = [];
        $t = clone $triggerData;
        CRM_Core_DAO::storeValues($dao, $participant);
        $t->setEntityData('Participant', $participant);
        $t->setContactId($participant['contact_id']);
        $this->setTriggerData($t);
        parent::triggerTrigger($op, $objectName, $objectId, $objectRef, $eventID);
      }
    }


  }

  /**
   * Returns an array of additional entities provided in this trigger
   *
   * @return array of CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function getAdditionalEntities() {
    $entities = parent::getAdditionalEntities();
    $entities[] = new CRM_Civirules_TriggerData_EntityDefinition('Activity', 'Activity', 'CRM_Activity_DAO_Activity', 'Activity');
    $entities[] = new CRM_Civirules_TriggerData_EntityDefinition('Participant', 'Participant', 'CRM_Event_DAO_Participant', 'Participant');
    return $entities;
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a trigger
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleId
   * @return bool|string
   * @access public
   * @abstract
   */
  public function getExtraDataInputUrl($ruleId) {
    return CRM_Utils_System::url('civicrm/civirule/form/trigger/relatedparticipantwhenactivityistagged', 'rule_id='.$ruleId);
  }

}
