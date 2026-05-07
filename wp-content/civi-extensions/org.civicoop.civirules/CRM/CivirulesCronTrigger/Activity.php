<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

abstract class CRM_CivirulesCronTrigger_Activity extends CRM_Civirules_Trigger_Cron {

  /**
   * @var \CRM_Activity_DAO_Activity
   */
  protected $activityDAO = NULL;

  /**
   * This function returns a CRM_Civirules_TriggerData_TriggerData this entity is used for triggering the rule
   *
   * Return false when no next entity is available
   *
   * @return CRM_Civirules_TriggerData_TriggerData|false
   */
  protected function getNextEntityTriggerData() {
    if (!$this->activityDAO) {
      if (!$this->queryForTriggerEntities()) {
        return FALSE;
      }
    }
    if ($this->activityDAO->fetch()) {
      $data = [];
      CRM_Core_DAO::storeValues($this->activityDAO, $data);
      unset($data['activity_contact_id']);
      unset($data['contact_id']);
      unset($data['record_type_id']);
      $triggerData = new CRM_Civirules_TriggerData_Cron($this->activityDAO->contact_id, 'Activity', $data, NULL, $this);
      $activityContact = [];
      $activityContact['id'] = $this->activityDAO->activity_contact_id;
      $activityContact['activity_id'] = $this->activityDAO->id;
      $activityContact['contact_id'] = $this->activityDAO->contact_id;
      $activityContact['record_type_id'] = $this->activityDAO->record_type_id;
      $triggerData->setEntityData('ActivityContact', $activityContact);

      if (!empty($this->triggerParams['case_activity'])) {
        $case = new CRM_Case_BAO_Case();
        $case->id = $this->activityDAO->case_id;
        if ($case->id && $case->find(TRUE)) {
          $data = [];
          CRM_Core_DAO::storeValues($case, $data);
          $triggerData->setEntityData('Case', $data);
        }
      }

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
    return new CRM_Civirules_TriggerData_EntityDefinition('Activity', 'Activity', 'CRM_Activity_DAO_Activity', 'Activity');
  }

  /**
   * Returns an array of additional entities provided in this trigger
   *
   * @return array of CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function getAdditionalEntities() {
    $entities = parent::getAdditionalEntities();
    $entities[] = new CRM_Civirules_TriggerData_EntityDefinition('ActivityContact', 'ActivityContact', 'CRM_Activity_DAO_ActivityContact' , 'ActivityContact');
    $entities[] = new CRM_Civirules_TriggerData_EntityDefinition('Case', 'Case', 'CRM_Case_DAO_Case' , 'Case');
    $entities[] = new CRM_Civirules_TriggerData_EntityDefinition('CaseActivity', 'CaseActivity', 'CRM_Case_DAO_CaseActivity' , 'CaseActivity');
    return $entities;
  }

}
