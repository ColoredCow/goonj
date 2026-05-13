<?php
/**
 * Created by PhpStorm.
 * User: jaap
 * Date: 6/23/15
 * Time: 10:26 AM
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesCronTrigger_GroupMembership extends CRM_Civirules_Trigger_Cron {

  /**
   * @var bool|CRM_Core_DAO
   */
  private $dao = FALSE;

  /**
   * This function returns a CRM_Civirules_TriggerData_TriggerData this entity is used for triggering the rule
   *
   * Return false when no next entity is available
   *
   * @return CRM_Civirules_TriggerData_Cron|false
   * @throws \CRM_Core_Exception
   * @throws \Civi\Core\Exception\DBQueryException
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
      return new CRM_Civirules_TriggerData_Cron($this->dao->contact_id, 'GroupContact', $data, $this->dao->contact_id, $this);
    }
    return FALSE;
  }

  /**
   * Returns an array of entities on which the trigger reacts
   *
   * @return CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function reactOnEntity() {
    return new CRM_Civirules_TriggerData_EntityDefinition('GroupContact', 'GroupContact', 'CRM_Contact_DAO_GroupContact', 'GroupContact');
  }

  /**
   * Method to query trigger entities
   *
   * @return bool
   * @throws \CRM_Core_Exception
   * @throws \Civi\Core\Exception\DBQueryException
   */
  private function queryForTriggerEntities() {

    if (empty($this->triggerParams['group_id'])) {
      return FALSE;
    }

    if (is_array($this->triggerParams['group_id'])) {
      $this->triggerParams['group_id'] = CRM_Utils_Type::escapeAll($this->triggerParams['group_id'], 'Integer');
      $groupWhereStatement = "`c`.`group_id` IN (".implode(", ", $this->triggerParams['group_id']).")";
    } else {
      $groupWhereStatement = "`c`.`group_id` = '".CRM_Utils_Type::escape($this->triggerParams['group_id'], 'Integer', true)."'";
    }

    CRM_Contact_BAO_GroupContactCache::loadAll($this->triggerParams['group_id']);
    $sql = "SELECT c.group_id, c.contact_id
            FROM `civicrm_group_contact` `c`
            WHERE {$groupWhereStatement}
              AND c.status = 'Added'
              AND `c`.`contact_id` NOT IN (
                SELECT `rule_log`.`contact_id`
                FROM `civirule_rule_log` `rule_log`
                WHERE `rule_log`.`rule_id` = %1 AND DATE(`rule_log`.`log_date`) = DATE(NOW())
              )
            UNION
            SELECT c.group_id, c.contact_id
            FROM `civicrm_group_contact_cache` c
            WHERE {$groupWhereStatement}
              AND `c`.`contact_id` NOT IN (
                SELECT `rule_log`.`contact_id`
                FROM `civirule_rule_log` `rule_log`
                WHERE `rule_log`.`rule_id` = %1 AND DATE(`rule_log`.`log_date`) = DATE(NOW())
              )
    ";

    $params[1] = [$this->ruleId, 'Integer'];
    $this->dao = CRM_Core_DAO::executeQuery($sql, $params, true, 'CRM_Contact_DAO_GroupContact');

    return true;
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a condition
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleId
   *
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleId) {
    return CRM_Utils_System::url('civicrm/civirule/form/trigger/groupmembership/', 'rule_id=' . $ruleId);
  }

  /**
   * Returns a description of this trigger
   *
   * @return string
   */
  public function getTriggerDescription(): string {
    $groupName = E::ts('Unknown');
    if (is_array($this->triggerParams['group_id'])) {
      $groupApi = civicrm_api3('Group', 'get', ['id' => ['IN' => $this->triggerParams['group_id']], 'options' => ['limit' => 0]]);
      $groupNames = [];
      foreach($groupApi['values'] as $group) {
        $groupNames[] = $group['title'];
      }
      if (!empty($groupNames)) {
        $groupName = implode(", ", $groupNames);
      }
    } else {
      try {
        $groupName = civicrm_api3('Group', 'getvalue', [
          'return' => 'title',
          'id' => $this->triggerParams['group_id']
        ]);
      } catch (Exception $e) {
        //do nothing
      }
    }
    return E::ts('Daily trigger for all members of group %1', [
      1 => $groupName
    ]);
  }

}
