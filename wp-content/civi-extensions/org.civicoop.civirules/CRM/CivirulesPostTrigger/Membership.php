<?php
/**
 * Class for CiviRules post trigger handling - Membership
 *
 * @license AGPL-3.0
 */

class CRM_CivirulesPostTrigger_Membership extends CRM_Civirules_Trigger_Post {

  use CRM_CivirulesTrigger_MembershipTrait;

  /**
   * Returns an array of entities on which the trigger reacts
   *
   * @return CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function reactOnEntity() {
    return new CRM_Civirules_TriggerData_EntityDefinition($this->objectName, $this->objectName, $this->getDaoClassName(), 'Membership');
  }

  /**
   * Return the name of the DAO Class. If a dao class does not exist return an empty value
   *
   * @return string
   */
  protected function getDaoClassName() {
    return 'CRM_Member_DAO_Membership';
  }

}
