<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesCronTrigger_ActivityScheduledDate extends CRM_CivirulesCronTrigger_Activity {

  public static function intervals() {
    return [
      '-minutes' => ts('Minutes(s) before scheduled date/time'),
      '-hours' => ts('Hours(s) before scheduled date/time'),
      '-days' => ts('Day(s) before scheduled date'),
      '-weeks' => ts('Week(s) before scheduled date'),
      '-months' => ts('Month(s) before scheduled date'),
      '+minutes' => ts('Minutes(s) after scheduled date/time'),
      '+hours' => ts('Hours(s) after scheduled date/time'),
      '+days' => ts('Day(s) after scheduled date'),
      '+weeks' => ts('Week(s) after scheduled date'),
      '+months' => ts('Month(s) after scheduled date'),
    ];
  }

  /**
   * Method to query trigger entities
   */
  protected function queryForTriggerEntities() {
    if (empty($this->triggerParams['activity_type_id']) || empty($this->triggerParams['activity_status_id'])) {
      return FALSE;
    }

    $currentDateTime = "CONCAT(CURRENT_DATE(), ' ', CURRENT_TIME())";
    $activity_date_time_statement = "AND DATE(a.activity_date_time) = {$currentDateTime}";
    switch ($this->triggerParams['interval_unit']) {
      case '-minutes':
        $activity_date_time_statement = "AND DATE_SUB(a.activity_date_time, INTERVAL %2 MINUTE) < {$currentDateTime}";
        $params[2] = [$this->triggerParams['interval'], 'Integer'];
        break;
      case '-hours':
        $activity_date_time_statement = "AND DATE_SUB(a.activity_date_time, INTERVAL %2 HOUR) < {$currentDateTime}";
        $params[2] = [$this->triggerParams['interval'], 'Integer'];
        break;
      case '-days':
        $activity_date_time_statement = "AND DATE_SUB(a.activity_date_time, INTERVAL %2 DAY) < {$currentDateTime}";
        $params[2] = [$this->triggerParams['interval'], 'Integer'];
        break;
      case '-weeks':
        $activity_date_time_statement = "AND DATE_SUB(a.activity_date_time, INTERVAL %2 WEEK) < {$currentDateTime}";
        $params[2] = [$this->triggerParams['interval'], 'Integer'];
        break;
      case '-months':
        $activity_date_time_statement = "AND DATE_SUB(a.activity_date_time, INTERVAL %2 MONTH) < {$currentDateTime}";
        $params[2] = [$this->triggerParams['interval'], 'Integer'];
        break;
      case '+minutes':
        $activity_date_time_statement = "AND DATE_ADD(a.activity_date_time, INTERVAL %2 MINUTE) < {$currentDateTime}";
        $params[2] = [$this->triggerParams['interval'], 'Integer'];
        break;
      case '+hours':
        $activity_date_time_statement = "AND DATE_ADD(a.activity_date_time, INTERVAL %2 HOUR) < {$currentDateTime}";
        $params[2] = [$this->triggerParams['interval'], 'Integer'];
        break;
      case '+days':
        $activity_date_time_statement = "AND DATE_ADD(a.activity_date_time, INTERVAL %2 DAY) < {$currentDateTime}";
        $params[2] = [$this->triggerParams['interval'], 'Integer'];
        break;
      case '+weeks':
        $activity_date_time_statement = "AND DATE_ADD(a.activity_date_time, INTERVAL %2 WEEK) < {$currentDateTime}";
        $params[2] = [$this->triggerParams['interval'], 'Integer'];
        break;
      case '+months':
        $activity_date_time_statement = "AND DATE_ADD(a.activity_date_time, INTERVAL %2 MONTH) < {$currentDateTime}";
        $params[2] = [$this->triggerParams['interval'], 'Integer'];
        break;
    }

    $activityContactWhereClause = '';
    if (!empty($this->triggerParams['record_type'])) {
      $activityContactWhereClause = "AND `ac`.`record_type_id` = %5";
      $params[5] = [$this->triggerParams['record_type'], 'Integer'];
    }

    $activityCaseWhereClause = 'AND `ca`.`case_id` IS NULL';
    if (!empty($this->triggerParams['case_activity'])) {
      $activityCaseWhereClause = 'AND `ca`.`case_id` IS NOT NULL';
    }

    $sql = "SELECT a.*, ac.contact_id as contact_id, ac.record_type_id as record_type_id, ac.id as activity_contact_id, ca.case_id as case_id
            FROM `civicrm_activity` `a`
            INNER JOIN `civicrm_activity_contact` ac ON a.id = ac.activity_id
            LEFT JOIN `civicrm_case_activity` ca ON a.id = ca.activity_id
            LEFT JOIN `civirule_rule_log` `rule_log` ON `rule_log`.entity_table = 'civicrm_activity'
              AND `rule_log`.entity_id = a.id
              AND `rule_log`.`contact_id` = `ac`.`contact_id`
              AND `rule_log`.`rule_id` = %1
            WHERE `a`.`activity_type_id` IN (%3)
              AND `a`.`status_id` IN (%4)
              AND `a`.`is_deleted` = 0
              AND `rule_log`.`id` IS NULL
              {$activity_date_time_statement}
              {$activityContactWhereClause}
              {$activityCaseWhereClause}
              AND `ac`.`contact_id` NOT IN (
                SELECT `rule_log2`.`contact_id`
                FROM `civirule_rule_log` `rule_log2`
                WHERE `rule_log2`.`rule_id` = %1
                  AND DATE(`rule_log2`.`log_date`) = DATE(NOW())
                  AND `rule_log2`.`entity_table` IS NULL
                  AND `rule_log2`.`entity_id` IS NULL
            )";
    $params[1] = [$this->ruleId, 'Integer'];
    $params[3] = [implode(',', $this->triggerParams['activity_type_id']), 'CommaSeparatedIntegers'];
    $params[4] = [implode(',', $this->triggerParams['activity_status_id']), 'CommaSeparatedIntegers'];

    $this->activityDAO = CRM_Core_DAO::executeQuery($sql, $params, TRUE, 'CRM_Activity_DAO_Activity');

    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    $dao->fetch();

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
    return CRM_Utils_System::url('civicrm/civirule/form/trigger/activityscheduleddate', "rule_id={$ruleId}");
  }

  /**
   * Returns a description of this trigger
   *
   * @return string
   */
  public function getTriggerDescription(): string {
    $activityTypes = CRM_Civirules_Utils::getActivityTypeList();
    $activityStatuses = CRM_Civirules_Utils::getActivityStatusList();

    $activityTypeLabels = [];
    foreach ($this->triggerParams['activity_type_id'] as $activityTypeID) {
      $activityTypeLabels[] = $activityTypes[$activityTypeID];
    }
    $activityTypeLabel = implode(',', $activityTypeLabels);

    $activityStatusLabels = [];
    foreach ($this->triggerParams['activity_status_id'] as $activityStatusID) {
      $activityStatusLabels[] = $activityStatuses[$activityStatusID];
    }
    $activityStatusLabel = implode(',', $activityStatusLabels);

    $caseActivity = 'Not case activity';
    if (!empty($this->triggerParams['case_activity'])) {
      $caseActivity = 'Case activity';
    }

    $intervalUnits = CRM_CivirulesCronTrigger_ActivityScheduledDate::intervals();
    $intervalUnitLabel = $intervalUnits[$this->triggerParams['interval_unit']];

    $result = civicrm_api3('ActivityContact', 'getoptions', [
      'field' => "record_type_id",
    ]);
    $options[0] = E::ts('All contacts');
    $options = $options + $result['values'];

    return E::ts('%6 Types %1 and status %2 - %3 %4. Trigger for %5', [
      1 => $activityTypeLabel,
      2 => $activityStatusLabel,
      3 => $this->triggerParams['interval'],
      4 => $intervalUnitLabel,
      5 => $options[$this->triggerParams['record_type']],
      6 => $caseActivity,
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
      case 'triggerDescription':
        return E::ts('Trigger for activities of selected types when the scheduled date is X days/weeks/months before or after.');

      case 'triggerDescriptionWithParams':
        return $this->getTriggerDescription();

      case 'triggerParamsHelp':
        return E::ts('Trigger for activities of selected types when the scheduled date is X days/weeks/months before or after.')
          . '<br/>'
          . E::ts('If "Trigger for case activities" is "Yes" then this will only trigger for case activities. If it is "No" then it will only trigger for activities that are not linked to a case.');

      default:
        return parent::getHelpText($context);
    }
  }

}
