<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesCronTrigger_EventDate extends CRM_Civirules_Trigger_Cron {

  /**
   * @var CRM_Core_DAO
   */
  private $dao = FALSE;

  /**
   * This function returns a CRM_Civirules_TriggerData_TriggerData this entity is used for triggering the rule
   *
   * Return FALSE when no next entity is available
   *
   * @return CRM_Civirules_TriggerData_TriggerData|FALSE
   */
  protected function getNextEntityTriggerData() {
    static $_eventCache = [];
    if (!$this->dao) {
      if (!$this->queryForTriggerEntities()) {
        return FALSE;
      }
    }
    if ($this->dao->fetch()) {
      $participant = [];
      CRM_Core_DAO::storeValues($this->dao, $participant);
      $triggerData = new CRM_Civirules_TriggerData_Cron($this->dao->contact_id, 'Participant', $participant);
      $triggerData->setTrigger($this);
      if (!isset($_eventCache[$participant['event_id']])) {
        $_eventCache[$participant['event_id']] = civicrm_api3('Event', 'getsingle', ['id' => $participant['event_id']]);
      }
      $triggerData->setEntityData('Event', $_eventCache[$participant['event_id']]);
      return $triggerData;
    }
    return FALSE;
  }

  /**
   * Returns an array of entities on which the trigger reacts
   *
   * @return \CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function reactOnEntity() {
    return new CRM_Civirules_TriggerData_EntityDefinition('Participant', 'Participant', 'CRM_Event_DAO_Participant', 'Participant');
  }

  /**
   * Method to query trigger entities
   *
   * @return bool
   * @throws \CRM_Core_Exception
   */
  private function queryForTriggerEntities() {
    if (empty($this->triggerParams['date_field'])) {
      return FALSE;
    }

    $dateField = $this->triggerParams['date_field'];
    if (!empty($this->triggerParams['offset'])) {
      $unit = 'DAY';
      if (!empty($this->triggerParams['offset_unit'])) {
        $unit = $this->triggerParams['offset_unit'];
      }
      $offset = CRM_Utils_Type::escape($this->triggerParams['offset'], 'Integer');
      if ($this->triggerParams['offset_type'] == '-') {
        // Trigger X units BEFORE the event date
        $dateExpression = "DATE_SUB(`e`.`".$dateField."`, INTERVAL ".$offset." ".$unit .") < NOW()";
        // Don't trigger for events with dates before this rule was created.
        $dateExpression .= " AND `e`.`".$dateField."` > DATE_SUB(`rule`.`created_date`, INTERVAL ".$offset." ".$unit .")";
      } else {
        // Trigger X units AFTER the event date
        $dateExpression = "DATE_ADD(`e`.`".$dateField."`, INTERVAL ".$offset." ".$unit .") < NOW()";
        // Don't trigger for events with dates before this rule was created.
        $dateExpression .= " AND `e`.`".$dateField."` > DATE_ADD(`rule`.`created_date`, INTERVAL ".$offset." ".$unit .")";
      }
    } else {
      // Trigger when the event date is reached
      $dateExpression = "`e`.`".$dateField."` < NOW()";
      // Don't trigger for events with dates before this rule was created.
      $dateExpression .= " AND `e`.`".$dateField."` > `rule`.`created_date`";
    }

    $sqlEventTypeID = '';
    if (!empty($this->triggerParams['event_type_id'])) {
      $sqlEventTypeID = 'AND `e`.`event_type_id` = %1';
      $params[1] = [$this->triggerParams['event_type_id'], 'Integer'];
    }
    $sql = "SELECT `p`.*
            FROM `civicrm_participant` `p`
            INNER JOIN `civicrm_event` `e` ON `e`.`id` = `p`.`event_id`
            LEFT JOIN `civirule_rule_log` `rule_log` ON `rule_log`.entity_table = 'civicrm_participant' AND `rule_log`.entity_id = p.id AND `rule_log`.`contact_id` = `p`.`contact_id` AND `rule_log`.`rule_id` = %2
            LEFT JOIN `civirule_rule` `rule` ON `rule`.`id` = %2
            WHERE {$dateExpression}
            AND `e`.`is_active` = 1
            AND `rule_log`.`id` IS NULL
            {$sqlEventTypeID}
            AND `p`.`contact_id` NOT IN (
              SELECT `rule_log2`.`contact_id`
              FROM `civirule_rule_log` `rule_log2`
              WHERE `rule_log2`.`rule_id` = %2 and `rule_log2`.`entity_table` IS NULL AND `rule_log2`.`entity_id` IS NULL
            )";

    $params[2] = [$this->ruleId, 'Integer'];
    $this->dao = CRM_Core_DAO::executeQuery($sql, $params, TRUE, 'CRM_Event_DAO_Participant');

    return TRUE;
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
    return CRM_Utils_System::url('civicrm/civirule/form/trigger/eventdate/', 'rule_id=' . $ruleId);
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

  /**
   * Returns an array of additional entities provided in this trigger
   *
   * @return array of CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function getAdditionalEntities() {
    $entities = parent::getAdditionalEntities();
    $entities[] = new CRM_Civirules_TriggerData_EntityDefinition('Event', 'Event', 'CRM_Event_DAO_Event' , 'Event');
    return $entities;
  }

}
