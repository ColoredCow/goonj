<?php

class CRM_CivirulesCronTrigger_RelationshipEndDate extends CRM_Civirules_Trigger_Cron {

  private $dao = false;

  /**
   * Returns an array of entities on which the triggerreacts
   *
   * @return CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function reactOnEntity() {
    return new CRM_Civirules_TriggerData_EntityDefinition('Relationship', 'Relationship', 'CRM_Contact_DAO_Relationship', 'Relationship');
  }

  /**
   * This method returns a CRM_Civirules_TriggerData_TriggerData this entity is used for triggering the rule
   *
   * Return false when no next entity is available
   *
   * @return object|bool CRM_Civirules_TriggerData_TriggerData|false
   * @access protected
   */
  protected function getNextEntityTriggerData() {
    if (!$this->dao) {
      $this->queryForTriggerEntities();
    }
    if ($this->dao->fetch()) {
      $data = array();
      CRM_Core_DAO::storeValues($this->dao, $data);
      $triggerData = new CRM_Civirules_TriggerData_Cron($this->dao->contact_id_a, 'Relationship', $data);
      $triggerData->setTrigger($this);
      return $triggerData;
    }
    return false;
  }

  /**
   * Method to query trigger entities
   *
   * @access private
   */
  private function queryForTriggerEntities() {
    $sql = "SELECT r.*
            FROM `civicrm_relationship` `r`
            LEFT JOIN (
                SELECT * FROM `civicrm_relationship`
            ) `r_inner`
            ON `r`.`relationship_type_id` = `r_inner`.`relationship_type_id`
            AND `r`.`contact_id_a` = `r_inner`.`contact_id_a`
            AND (`r`.`end_date` < `r_inner`.`end_date` or `r_inner`.`end_date` is null)
            AND `r`.`id` != `r_inner`.`id`
            WHERE `r_inner`.`id` IS NULL
            AND `r`.`end_date` IS NOT NULL
            AND `r`.`end_date` < CURDATE()
            AND `r`.`contact_id_a` NOT IN (
              SELECT `rule_log`.`contact_id`
              FROM `civirule_rule_log` `rule_log`
              WHERE `rule_log`.`rule_id` = %1 AND DATE(`rule_log`.`log_date`) = DATE(NOW())
            );";
    $params[1] = array($this->ruleId, 'Integer');
    $this->dao = CRM_Core_DAO::executeQuery($sql, $params, true, 'CRM_Contact_BAO_Relationship');
  }
}
