<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesPostTrigger_RelatedParticipantWhenActivityChanged extends CRM_Civirules_Trigger_Post {

  /**
   * Returns an array of entities on which the trigger reacts
   *
   * @return CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function reactOnEntity() {
    return new CRM_Civirules_TriggerData_EntityDefinition($this->objectName, $this->objectName, $this->getDaoClassName(), 'Activity');
  }

  /**
   * Return the name of the DAO Class. If a dao class does not exist return an empty value
   *
   * @return string
   */
  protected function getDaoClassName() {
    return 'CRM_Activity_DAO_Activity';
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
    $triggerData = $this->getTriggerDataFromPost($op, $objectName, $objectId, $objectRef, $eventID);
    if (empty($triggerData->getEntityId())) {
      $triggerData->setEntityId($objectId);
    }

    if (isset($this->triggerParams['activity_type_id']) && is_array($this->triggerParams['activity_type_id']) && count($this->triggerParams['activity_type_id'])) {
      $activity = civicrm_api3('Activity', 'getsingle', ['id' => $triggerData->getEntityId()]);
      if (!in_array($activity['activity_type_id'], $this->triggerParams['activity_type_id'])) {
        return;
      }
    }

    $custom_field_id = $this->triggerParams['event_id_custom_field'];
    $event_id = civicrm_api3('Activity', 'getvalue', ['id' => $triggerData->getEntityId(), 'return' => 'custom_'.$custom_field_id]);
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

  /**
   * Returns an array of additional entities provided in this trigger
   *
   * @return array of CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function getAdditionalEntities() {
    $entities = parent::getAdditionalEntities();
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
    return CRM_Utils_System::url('civicrm/civirule/form/trigger/relatedparticipantwhenactivitychanged', 'rule_id='.$ruleId);
  }

}
