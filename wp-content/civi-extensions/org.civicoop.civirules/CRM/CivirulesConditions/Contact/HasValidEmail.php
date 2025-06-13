<?php

class CRM_CivirulesConditions_Contact_HasValidEmail extends CRM_Civirules_Condition {

  /**
   * This method returns true or false when an condition is valid or not
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   * @access public
   * @abstract
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $count = \Civi\Api4\Email::get(FALSE)
      ->addWhere('contact_id', '=', $triggerData->getContactId())
      ->addWhere('on_hold', '=', 0)
      ->addWhere('is_primary', '=', TRUE)
      ->execute()
      ->count();
    if ($count>0) {
      return true;
    }
    return false;
  }


  /**
   * Returns false, meaning that this condition has no further configuration
   *
   * @param $ruleConditionId
   * @return false
   */
  public function getExtraDataInputUrl($ruleConditionId) {
    return false;
  }

  /**
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    return $trigger->doesProvideEntity('Contact');
  }

  /**
   * @return string
   */
  public function userFriendlyConditionParams() {
    return 'Has valid email';
  }
}
