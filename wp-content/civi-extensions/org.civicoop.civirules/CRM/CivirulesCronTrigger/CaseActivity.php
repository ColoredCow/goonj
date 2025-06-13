<?php

use Civi\Api4\CaseContact;

/**
 * Daily trigger for case activity
 */
class CRM_CivirulesCronTrigger_CaseActivity extends CRM_Civirules_Trigger_Cron {

  private $dao = FALSE;

  /**
   * This function returns a CRM_Civirules_TriggerData_TriggerData this entity is used for triggering the rule
   *
   * Return false when no next entity is available
   *
   * @return CRM_Civirules_TriggerData_TriggerData|false
   */
  protected function getNextEntityTriggerData() {
    if (!$this->dao) {
      if (!$this->queryForTriggerEntities()) {
        return FALSE;
      }
    }
    if ($this->dao->fetch()) {
      $data = [];
      CRM_Core_DAO::storeValues($this->dao, $data);
      $triggerData = new CRM_Civirules_TriggerData_Cron(0, 'Case', $data);
      $triggerData->setTrigger($this);
      return $triggerData;
    }
    return FALSE;
  }

  /**
   * Returns an array of entities on which the trigger reacts
   *
   * @return CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function reactOnEntity() {
    return new CRM_Civirules_TriggerData_EntityDefinition('Case', 'Case', 'CRM_Case_DAO_Case', 'Case');
  }

  /**
   * Method to query trigger entities
   *
   */
  private function queryForTriggerEntities() {
    $sql = "SELECT c.*
            FROM `civicrm_case` `c`
            WHERE `c`.`is_deleted` = 0
            ";
    $this->dao = CRM_Core_DAO::executeQuery($sql, [], TRUE, 'CRM_Case_DAO_Case');

    return TRUE;
  }

  /**
   * Returns additional entities provided in this trigger.
   *
   * @return array of CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function getAdditionalEntities() {
    // Adds in "Contact"
    return parent::getAdditionalEntities();
  }

  /**
   * Override alter trigger data.
   *
   * When a contribution is added/updated after an online payment is made
   * contact_id and financial_type_id are not present in the data in the post hook.
   * So we should retrieve this data from the database if it's not present.
   */
  public function alterTriggerData(CRM_Civirules_TriggerData_TriggerData &$triggerData) {
    $caseData = $triggerData->getEntityData('Case');
    if (!isset($caseData['id'])) {
      throw new CRM_Core_Exception('Case ID must be set!');
    }
    // @todo: support multiple case contacts?
    $caseContact = CaseContact::get(FALSE)
      ->addWhere('case_id', '=', $caseData['id'])
      ->execute()
      ->first();
    if (!empty($caseContact['contact_id'])) {
      $triggerData->setEntityData('Contact', ['id' => $caseContact['contact_id']]);
    }

    parent::alterTriggerData($triggerData);
  }

}
