<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesCronTrigger_MembershipEndDate extends CRM_Civirules_Trigger_Cron {

  use CRM_CivirulesTrigger_MembershipTrait;

  /**
   * @var \CRM_Member_DAO_Membership $dao
   */
  private $dao = NULL;

  public static function intervals() {
    return [
      '-days' => ts('Day(s) before end date'),
      '-weeks' => ts('Week(s) before end date'),
      '-months' => ts('Month(s) before end date'),
      '+days' => ts('Day(s) after end date'),
      '+weeks' => ts('Week(s) after end date'),
      '+months' => ts('Month(s) after end date'),
    ];
  }

  /**
   * This function returns a CRM_Civirules_TriggerData_TriggerData this entity is used for triggering the rule
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
      $triggerData = new CRM_Civirules_TriggerData_Cron($this->dao->contact_id, 'Membership', $data);
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
    return new CRM_Civirules_TriggerData_EntityDefinition(ts('Membership'), 'Membership', 'CRM_Member_DAO_Membership', 'Membership');
  }

  /**
   * Method to query trigger entities
   */
  private function queryForTriggerEntities() {
    if (empty($this->triggerParams['membership_type_id'])) {
      return false;
    }

    // membership_type_id used to be a single value, but now we can have multiple membership types
    if (is_array($this->triggerParams['membership_type_id'])) {
      $params[1] = [implode(',', $this->triggerParams['membership_type_id']), 'CommaSeparatedIntegers'];
    }
    else {
      $params[1] = [$this->triggerParams['membership_type_id'], 'Integer'];
    }

    $end_date_statement = "AND DATE(m.end_date) = CURRENT_DATE()";
    switch ($this->triggerParams['interval_unit']) {
      case '-days':
        $end_date_statement = "AND DATE_SUB(m.end_date, INTERVAL %2 DAY) = CURRENT_DATE()";
        $params[2] = [$this->triggerParams['interval'], 'Integer'];
        break;
      case '-weeks':
        $end_date_statement = "AND DATE_SUB(m.end_date, INTERVAL %2 WEEK) = CURRENT_DATE()";
        $params[2] = [$this->triggerParams['interval'], 'Integer'];
        break;
      case '-months':
        $end_date_statement = "AND DATE_SUB(m.end_date, INTERVAL %2 MONTH) = CURRENT_DATE()";
        $params[2] = [$this->triggerParams['interval'], 'Integer'];
        break;
      case '+days':
        $end_date_statement = "AND DATE_ADD(m.end_date, INTERVAL %2 DAY) = CURRENT_DATE()";
        $params[2] = [$this->triggerParams['interval'], 'Integer'];
        break;
      case '+weeks':
        $end_date_statement = "AND DATE_ADD(m.end_date, INTERVAL %2 WEEK) = CURRENT_DATE()";
        $params[2] = [$this->triggerParams['interval'], 'Integer'];
        break;
      case '+months':
        $end_date_statement = "AND DATE_ADD(m.end_date, INTERVAL %2 MONTH) = CURRENT_DATE()";
        $params[2] = [$this->triggerParams['interval'], 'Integer'];
        break;
    }

    $sql = "SELECT m.*
            FROM `civicrm_membership` `m`
            LEFT JOIN `civirule_rule_log` `rule_log`
              ON `rule_log`.entity_table = 'civicrm_membership'
              AND `rule_log`.entity_id = m.id
              AND `rule_log`.`contact_id` = `m`.`contact_id`
              AND DATE(`rule_log`.`log_date`) = DATE(NOW())
              AND `rule_log`.`rule_id` = %3
            WHERE `m`.`membership_type_id` IN (%1)
            AND `rule_log`.`id` IS NULL
            {$end_date_statement}
            AND `m`.`contact_id` NOT IN (
              SELECT `rule_log2`.`contact_id`
              FROM `civirule_rule_log` `rule_log2`
              WHERE `rule_log2`.`rule_id` = %3 AND DATE(`rule_log2`.`log_date`) = DATE(NOW()) and `rule_log2`.`entity_table` IS NULL AND `rule_log2`.`entity_id` IS NULL
            )";
    $params[3] = [$this->ruleId, 'Integer'];
    $this->dao = CRM_Core_DAO::executeQuery($sql, $params, true, 'CRM_Member_DAO_Membership');

    return true;
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a condition
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleId
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleId) {
    return CRM_Utils_System::url('civicrm/civirule/form/trigger/membershipenddate/', 'rule_id='.$ruleId);
  }

  /**
   * Returns a description of this trigger
   *
   * @return string
   */
  public function getTriggerDescription(): string {
    $membershipTypes = CRM_Civirules_Utils::getMembershipTypes();
    $intervalUnits = self::intervals();
    $intervalUnitLabel = $intervalUnits[$this->triggerParams['interval_unit']];

    if (is_array($this->triggerParams['membership_type_id'])) {
      $membershipTypeLabels = [];
      foreach ($this->triggerParams['membership_type_id'] as $membershipTypeID) {
        $membershipTypeLabels[] = $membershipTypes[$membershipTypeID];
      }
      $membershipTypeLabel = implode(',', $membershipTypeLabels);
    }
    else {
      $membershipTypeLabel = $membershipTypes[$this->triggerParams['membership_type_id']];
    }

    return E::ts('Membership Types %1 - %2 %3', [
      1 => $membershipTypeLabel,
      2 => $this->triggerParams['interval'],
      3 => $intervalUnitLabel,
    ]);
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
      case 'triggerDescriptionWithParams':
        return $this->getTriggerDescription();

      case 'triggerDescription':
      case 'triggerParamsHelp':
        return E::ts('Trigger for memberships of selected membership types when the end date is X days/weeks/months before or after.');

      default:
        return parent::getHelpText($context);
    }
  }

}
