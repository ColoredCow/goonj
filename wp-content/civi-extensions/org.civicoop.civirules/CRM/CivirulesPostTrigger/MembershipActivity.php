<?php

use Civi\Api4\Membership;
use Civi\Api4\OptionValue;
use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesPostTrigger_MembershipActivity extends CRM_CivirulesPostTrigger_Activity {

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
    if (!isset($triggerData->getEntityData('Activity')['activity_type_id'])) {
      $activity = \Civi\Api4\Activity::get(FALSE)
        ->addWhere('id', '=', $objectId)
        ->execute()
        ->first();
      $triggerData->setEntityData('Activity', $activity);
    }
    $activityTypeID = $triggerData->getEntityData('Activity')['activity_type_id'];
    if (empty($activityTypeID)) {
      \Civi::log()->warning('CiviRules MembershipActivity trigger: Empty activity_type_id. Not triggering');
      return;
    }

    // Check if this trigger is enabled for this op
    if (str_contains($this->triggerParams['trigger_op'], '$op')) {
      // \Civi::log()->debug('CiviRules MembershipActivity trigger: Trigger op not enabled: ' . $op);
      return;
    }

    // Check if our trigger is a membership activity
    $membershipActivityNames = [
      'Membership Signup',
      'Membership Renewal',
      'Change Membership Status',
      'Change Membership Type'
    ];

    $activityTypes = Civi::entity('Activity')->getOptions('activity_type_id');
    foreach ($activityTypes as $activityType) {
      if (in_array($activityType['name'], $membershipActivityNames)) {
        $membershipActivityTypes[$activityType['id']] = $activityType['name'];
      }
    }
    if (empty($membershipActivityTypes[$activityTypeID])) {
      // \Civi::log()->debug('CiviRules MembershipActivity trigger: activity_type_id ' . $activityTypeID . ' not a membership activity');
      return;
    }

    $sourceRecordID = $triggerData->getEntityData('Activity')['source_record_id'];
    if (empty($sourceRecordID)) {
      \Civi::log()->warning('CiviRules MembershipActivity trigger: Empty source_record_id. Not triggering');
      return;
    }

    // Populate the membership data
    $membership = Membership::get(FALSE)
      ->addWhere('id', '=', $sourceRecordID)
      ->execute()
      ->first();
    $triggerData->setEntityData('Membership', $membership);
    $this->setTriggerData($triggerData);

    parent::triggerTrigger($op, $objectName, $objectId, $objectRef, $eventID);
  }

  /**
   * Returns an array of additional entities provided in this trigger
   *
   * @return array of CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function getAdditionalEntities() {
    $entities = parent::getAdditionalEntities();
    $entities[] = new CRM_Civirules_TriggerData_EntityDefinition('Membership', 'Membership', 'CRM_Member_DAO_Membership' , 'Membership');
    return $entities;
  }

  /**
   * Returns a description of this trigger
   *
   * @return string
   */
  public function getTriggerDescription(): string {
    $text = parent::getTriggerDescription();
    $options = CRM_CivirulesTrigger_Form_Form::getTriggerOptions();
    $triggerOps = explode(',', $this->triggerParams['trigger_op'] ?? '');
    foreach ($options as $option) {
      if (in_array($option['id'], $triggerOps)) {
        $triggerOptions[] = $option['text'];
      }
    }
    $text .= ' on ' . implode(', ', $triggerOptions ?? []);
    return $text;
  }

  /**
   * Get various types of help text for the trigger:
   *   - triggerDescription: When choosing from a list of triggers, explains what the trigger does.
   *   - triggerDescriptionWithParams: When a trigger has been configured for a rule provides a
   *       user friendly description of the trigger and params (see $this->getTriggerDescription())
   *   - triggerParamsHelp (default): If the trigger has configurable params, show this help text when configuring
   * @param string $context
   *
   * @return string
   */
  public function getHelpText(string $context = 'triggerParamsHelp'): string {
    switch ($context) {
      case 'triggerDescription':
        return E::ts('Trigger on Membership Activities');

      case 'triggerDescriptionWithParams':
        return $this->getTriggerDescription();

      case 'triggerParamsHelp':
        return E::ts('Select the activity contact type that you want to trigger for. Also select if you want to trigger on Create and/or Edit')
          . '<br/>'
          . E::ts('This will trigger on all "Membership" activity types. Add conditions to restrict to specific activity types, status etc.');
      default:
        return parent::getHelpText($context);
    }
  }

}
