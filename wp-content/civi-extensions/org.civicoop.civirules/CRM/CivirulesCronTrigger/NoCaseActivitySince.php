<?php

use Civi\Api4\CaseContact;
use CRM_Civirules_ExtensionUtil as E;

/**
 * Daily trigger for case activity
 */
class CRM_CivirulesCronTrigger_NoCaseActivitySince extends CRM_Civirules_Trigger_Cron {

  /*
   * @var CRM_Core_DAO|FALSE
   */
  private ?CRM_Core_DAO $dao = NULL;

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
      $triggerData = new CRM_Civirules_TriggerData_Cron(0, 'CaseActivity', $data, NULL, $this);
      $case = civicrm_api3('Case', 'getsingle', ['id' => $data['case_id']]);
      $triggerData->setEntityData('Case', $case);
      $activity = civicrm_api3('Activity', 'getsingle', ['id' => $data['activity_id']]);
      $triggerData->setEntityData('Activity', $activity);
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
    return new CRM_Civirules_TriggerData_EntityDefinition('CaseActivity', 'CaseActivity', 'CRM_Case_DAO_CaseActivity' , 'CaseActivity');
  }

  /**
   * Method to query trigger entities
   *
   */
  private function queryForTriggerEntities() {
    $clauses = ["1"];
    if (!empty($this->triggerParams['case_status'])) {
      $clauses[] = "`c`.`status_id` IN (" . implode(',', $this->triggerParams['case_status']) . ")";
    }
    if (!empty($this->triggerParams['case_type'])) {
      $clauses[] = "`c`.`case_type_id` IN (" . implode(',', $this->triggerParams['case_type']) . ")";
    }
    if (!empty($this->triggerParams['activity_type'])) {
      $clauses[] = "`a`.`activity_type_id` IN (" . implode(',', $this->triggerParams['activity_type']) . ")";
    }
    if (!empty($this->triggerParams['activity_status'])) {
      $clauses[] = "`a`.`status_id` IN (" . implode(',', $this->triggerParams['activity_status']) . ")";
    }
    $dateField = $this->triggerParams['date_field'];
    $unit = 'DAY';
    if (!empty($this->triggerParams['offset_unit'])) {
      $unit = $this->triggerParams['offset_unit'];
    }
    $offset = CRM_Utils_Type::escape($this->triggerParams['offset'], 'Integer');
    $dateField = 'activity_date_time';

    $sql = "SELECT ca.*
            FROM `civicrm_case_activity` `ca`
            INNER JOIN `civicrm_activity` `a` ON `a`.`id` = `ca`.`activity_id`
            INNER JOIN `civicrm_case` `c` ON `c`.`id` = `ca`.`case_id`
            LEFT JOIN `civirule_rule_log` `rule_log` ON `rule_log`.entity_table = 'civicrm_case_activity' AND `rule_log`.entity_id = ca.id AND `rule_log`.`rule_id` = %1
            LEFT JOIN `civirule_rule` `rule` ON `rule`.`id` = %1
            WHERE `c`.`is_deleted` = 0 AND `a`.`is_deleted` = '0' AND `a`.`is_current_revision` = '1'
            AND " . implode(" AND ", $clauses) . "
            AND `rule_log`.`id` IS NULL
            GROUP BY `ca`.`case_id`
            HAVING DATE_ADD(MAX(`a`.`" . $dateField . "`), INTERVAL ".$offset." ".$unit .") <= NOW()";
    $params[1] = [$this->ruleId, 'Integer'];
    $this->dao = CRM_Core_DAO::executeQuery($sql, $params, TRUE, 'CRM_Case_DAO_CaseActivity');

    return TRUE;
  }

  /**
   * Returns additional entities provided in this trigger.
   *
   * @return array of CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function getAdditionalEntities() {
    // Adds in "Contact"
    $entities = parent::getAdditionalEntities();
    $entities[] = new CRM_Civirules_TriggerData_EntityDefinition('Case', 'Case', 'CRM_Case_DAO_Case', 'Case');
    $entities[] = new CRM_Civirules_TriggerData_EntityDefinition('Activity', 'Activity', 'CRM_Activity_DAO_Activity', 'Activity');
    return $entities;
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

  /**
   * Returns a redirect url to extra data input from the user after adding a condition
   *
   * Return FALSE if you do not need extra data input
   *
   * @param int $ruleId
   *
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleId) {
    return CRM_Utils_System::url('civicrm/civirule/form/trigger/nocaseactivitysince/', 'rule_id=' . $ruleId);
  }

  /**
   * Returns a description of this trigger
   *
   * @return string
   */
  public function getTriggerDescription(): string {
    $fields = [
      'start_date' => E::ts('Start Date'),
      'end_date' => E::ts('End Date'),
    ];
    $fieldLabel = $fields[$this->triggerParams['date_field']];
    $offsetLabel = 'on';
    if (!empty($this->triggerParams['offset'])) {
      $offsetTypes = [
        '-' => E::ts('before'),
        '+' => E::ts('after'),
      ];
      $offsetUnits = [
        'HOUR' => E::ts('Hour(s)'),
        'DAY' => E::ts('Day(s)'),
        'WEEK' => E::ts('Week(s)'),
        'MONTH' => E::ts('Month(s)'),
        'YEAR' => E::ts('Year(s)')
      ];
      $offsetLabel = "{$this->triggerParams['offset']} {$offsetUnits[$this->triggerParams['offset_unit']]} {$offsetTypes[$this->triggerParams['offset_type']]}";
    }

    $eventTypeLabel = 'any';
    if (!empty($this->triggerParams['event_type_id'])) {
      $eventTypeLabel = CRM_Civirules_Utils::getOptionLabelWithValue(CRM_Civirules_Utils::getOptionGroupIdWithName('event_type'), $this->triggerParams['event_type_id']);
    }
    $description = E::ts('Trigger for Event with type "%1" %3 "%2".', [
        1 => $eventTypeLabel,
        2 => $fieldLabel,
        3 => $offsetLabel
      ]);
    $description .=  ' <br/><em>This rule will not trigger for event dates before the rule was created.</em>';
    return $description;
  }

}
