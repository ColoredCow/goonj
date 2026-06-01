<?php

use Civi\Api4\Membership;

class CRM_Civirules_TriggerData_Post extends CRM_Civirules_TriggerData_TriggerData {

  /**
   * @param string $entity
   * @param int $objectId
   * @param array $data
   * @param ?CRM_Civirules_Trigger_Post $trigger
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function __construct($entity, $objectId, $data, ?CRM_Civirules_Trigger_Post $trigger = NULL) {
    if ($trigger) {
      $this->setTrigger($trigger);
    }

    // Trigger is not always defined when creating triggerData. It is used for adding Rule ID to log messages if possible.
    $ruleIDText = $this->getTrigger() ? 'Rule ID: ' . $this->getTrigger()->getRuleId() . ': ' : '';

    if (empty($objectId)) {
      \Civi::log('civirules')->error('CiviRules ' . $ruleIDText . 'TriggerData_Post entityID is NULL! Entity: ' . $entity . '; Data: ' . print_r($data,TRUE));
      throw new CRM_Core_Exception('CiviRules ' . $ruleIDText . 'TriggerData_Post entityID is NULL! Entity: ' . $entity);
    }

    parent::__construct();
    $this->setEntity($entity);
    $this->setEntityId($objectId);

    // When we are triggered via a Post hook we are not guaranteed to have all values
    // for the entity. Maybe we should load them all here?
    switch ($entity) {
      case 'Contact':
        $this->setContactId($objectId);
        $this->setEntityData($entity, $data);
        break;

      case 'Membership':
        // Load the membership entity (this makes sure we have all fields such as contribution_recur_id if set).
        $membership = Membership::get(FALSE)
          ->addWhere('id', '=', $objectId)
          ->execute()
          ->first();
        $this->setContactId($membership['contact_id']);
        $this->setEntityData('Membership', $membership);
        break;

      case 'Case':
        // $data['contact_id'] will probably be an array for case.
        // We just pick the first one in the array.
        if (isset($data['contact_id'])) {
          $contactID = $data['contact_id'];
          if (is_array($contactID)) {
            $contactID = reset($contactID);
          }
          if (is_numeric($contactID)) {
            $this->setContactId($contactID);
          }
          else {
            \Civi::log('civirules')->warning('CiviRules ' . $ruleIDText . 'Contact ID is not numeric for Case: data: ' . print_r($data, TRUE) . ')');
          }
        }
        $this->setEntityData($entity, $data);
        break;

      default:
        // Generic handler: Just make sure contactID is set.
        if (isset($data['contact_id'])) {
          if (is_numeric($data['contact_id'])) {
            $this->setContactId($data['contact_id']);
          }
          else {
            \Civi::log('civirules')->warning('CiviRules ' . $ruleIDText . 'Contact ID is not numeric (entity: ' . $entity . 'data: ' . print_r($data, TRUE) . ')');
          }
        }
        $this->setEntityData($entity, $data);
    }
  }

}
