<?php
/**
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesCronTrigger_NextContributionDate extends CRM_Civirules_Trigger_Cron {

  /**
   * @var \CRM_Contribute_DAO_ContributionRecur $dao
   */
  private $_dao = NULL;

  public static function intervals() {
    return [
      '-days' => ts('Day(s) before recurring next scheduled contribution date'),
      '-weeks' => ts('Week(s) before recurring next scheduled contribution date'),
      '-months' => ts('Month(s) before recurring next scheduled contribution date'),
      '+days' => ts('Day(s) after recurring next scheduled contribution date'),
      '+weeks' => ts('Week(s) after recurring next scheduled contribution date'),
      '+months' => ts('Month(s) after recurring next scheduled contribution date'),
    ];
  }

  /**
   * This function returns a CRM_Civirules_TriggerData_TriggerData this entity is used for triggering the rule
   * Return false when no next entity is available
   *
   * @return CRM_Civirules_TriggerData_TriggerData|false
   */
  protected function getNextEntityTriggerData() {
    if (!$this->_dao) {
      if (!$this->queryForTriggerEntities()) {
        return FALSE;
      }
    }
    if ($this->_dao->fetch()) {
      $data = [];
      CRM_Core_DAO::storeValues($this->_dao, $data);
      $triggerData = new CRM_Civirules_TriggerData_Cron($this->_dao->contact_id, 'ContributionRecur', $data, $data['contribution_recur_id']);
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
    return new CRM_Civirules_TriggerData_EntityDefinition(ts('Recurring Contribution'), 'ContributionRecur', 'CRM_Contribute_DAO_ContributionRecur', 'ContributionRecur');
  }

  /**
   * Method to query trigger entities
   */
  private function queryForTriggerEntities() {
    switch ($this->triggerParams['interval_unit']) {
      case '-days':
        $dateCalcStatement = 'DATE_SUB(r.next_sched_contribution_date, INTERVAL %2 DAY)';
        break;
      case '-weeks':
        $dateCalcStatement = 'DATE_SUB(r.next_sched_contribution_date, INTERVAL %2 WEEK)';
        break;
      case '-months':
        $dateCalcStatement = 'DATE_SUB(r.next_sched_contribution_date, INTERVAL %2 MONTH)';
        break;
      case '+days':
        $dateCalcStatement = 'DATE_ADD(r.next_sched_contribution_date, INTERVAL %2 DAY)';
        break;
      case '+weeks':
        $dateCalcStatement = 'DATE_ADD(r.next_sched_contribution_date, INTERVAL %2 WEEK)';
        break;
      case '+months':
        $dateCalcStatement = 'DATE_ADD(r.next_sched_contribution_date, INTERVAL %2 MONTH)';
        break;

      default:
        throw new CRM_Core_Exception('Unknown interval_unit: ' . $this->triggerParams['interval_unit']);
    }

    $nextDateStatement = "AND DATE({$dateCalcStatement}) = CURRENT_DATE()";
    $params = [
      // is_test
      1 => [0, 'Integer'],
      // date numeric interval (eg. 2)
      2 => [$this->triggerParams['interval'], 'Integer'],
      // The rule ID
      3 => [$this->ruleId, 'Integer'],
    ];

    $sql = "SELECT r.id AS `contribution_recur_id`, r.*
            FROM `civicrm_contribution_recur` `r`
            LEFT JOIN `civirule_rule_log` `rule_log`
              ON `rule_log`.entity_table = 'civicrm_contribution_recur'
              AND `rule_log`.entity_id = r.id
              AND `rule_log`.`contact_id` = `r`.`contact_id`
              AND DATE(`rule_log`.`log_date`) = DATE(NOW())
              AND `rule_log`.`rule_id` = %3
            WHERE `r`.`is_test` = %1
              AND `rule_log`.`id` IS NULL
              {$nextDateStatement}
              AND `r`.`contact_id` NOT IN (
                SELECT `rule_log2`.`contact_id`
                FROM `civirule_rule_log` `rule_log2`
                WHERE `rule_log2`.`rule_id` = %3 AND DATE(`rule_log2`.`log_date`) = DATE(NOW()) and `rule_log2`.`entity_table` IS NULL AND `rule_log2`.`entity_id` IS NULL
            )";

    $this->_dao = CRM_Core_DAO::executeQuery($sql, $params, TRUE, 'CRM_Contribute_DAO_ContributionRecur');
    return TRUE;
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
    return CRM_Utils_System::url('civicrm/civirule/form/trigger/nextcontributiondate/', 'rule_id='.$ruleId);
  }

  /**
   * Returns a description of this trigger
   *
   * @return string
   */
  public function getTriggerDescription(): string {
    $intervalUnits = self::intervals();
    $intervalUnitLabel = $intervalUnits[$this->triggerParams['interval_unit']];
    return E::ts('Next Scheduled Contribution Date: %1 %2', [
      1 => $this->triggerParams['interval'],
      2 => $intervalUnitLabel,
    ]);
  }

  /**
   * Returns additional entities provided in this trigger.
   *
   * @return array of CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function getAdditionalEntities() {
    return parent::getAdditionalEntities();
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
        return E::ts('Trigger for recurring contributions when the next scheduled contribution date is X days/weeks/months before or after.');

      default:
        return parent::getHelpText($context);
    }
  }

}
