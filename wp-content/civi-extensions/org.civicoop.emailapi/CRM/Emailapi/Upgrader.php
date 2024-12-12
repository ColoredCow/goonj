<?php

use CRM_Emailapi_ExtensionUtil as E;
/**
 * Collection of upgrade steps
 */
class CRM_Emailapi_Upgrader extends CRM_Extension_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Install CiviRule Action Send Email
   */
  public function install() {
    // if extension CiviRules installed, add action send email
    if (civicrm_api3('Extension', 'get', ['full_name' => 'org.civicoop.civirules', 'status' => 'installed'])['count']){
      $this->executeSqlFile('sql/insertSendEmailAction.sql');
    }
  }

  /**
   * remove managed entity
   */
  public function upgrade_1001() {
    $this->ctx->log->info('Applying update 1001 (remove managed entity');
    if (CRM_Core_DAO::checkTableExists('civicrm_managed')) {
      $query = 'DELETE FROM civicrm_managed WHERE module = %1 AND entity_type = %2';
      CRM_Core_DAO::executeQuery($query, [
        1 => [E::LONG_NAME, 'String'],
        2 => ['CiviRuleAction', 'String'],
      ]);
    }
    return TRUE;
  }

  /**
   * re-add send email action if required
   */
  public function upgrade_1002() {
    if(civicrm_api3('Extension', 'get', ['full_name' => 'org.civicoop.civirules', 'status' => 'installed'])['count']){
      $this->ctx->log->info('Applying update 1002');
      $select = "SELECT COUNT(*) FROM civirule_action WHERE class_name = %1";
      $count = CRM_Core_DAO::singleValueQuery($select, [1 => ['CRM_Emailapi_CivirulesAction', 'String']]);
      if ($count == 0) {
        $this->executeSqlFile('sql/insertSendEmailAction.sql');
      }
    }
    return TRUE;
  }

  /**
   * update class name of the send email action and add the send email to related contact
   */
  public function upgrade_1003() {
    if(civicrm_api3('Extension', 'get', ['full_name' => 'org.civicoop.civirules', 'status' => 'installed'])['count']){
      CRM_Core_DAO::executeQuery("UPDATE civirule_action SET class_name = 'CRM_Emailapi_CivirulesAction_Send' WHERE `name` = 'emailapi_send'");
      CRM_Core_DAO::executeQuery("INSERT INTO civirule_action (name, label, class_name, is_active)
        VALUES('emailapi_send_relationship', 'Send Email to a related contact', 'CRM_Emailapi_CivirulesAction_SendToRelatedContact', 1);"
      );
    }
    return true;
  }

  /**
   * Upgrader to update old civicrm_queue_items so they reflect the new class names.
   */
  public function upgrade_1004() {
    if (civicrm_api3('Extension', 'get', ['full_name' => 'org.civicoop.civirules', 'status' => 'installed'])['count']){
      CRM_Core_DAO::executeQuery("UPDATE `civicrm_queue_item` SET data = REPLACE(data, '\"class_name\";s:28:\"CRM_Emailapi_CivirulesAction\"', '\"class_name\";s:33:\"CRM_Emailapi_CivirulesAction_Send\"')  WHERE data like '%\"class_name\";s:28:\"CRM_Emailapi_CivirulesAction\"%'");
      CRM_Core_DAO::executeQuery("UPDATE `civicrm_queue_item` SET data = REPLACE(data, 'O:28:\"CRM_Emailapi_CivirulesAction\"', 'O:33:\"CRM_Emailapi_CivirulesAction_Send\"') WHERE data like '%O:28:\"CRM_Emailapi_CivirulesAction\"%' ");
    }
    return true;
  }

  public function upgrade_1005() {
    if (civicrm_api3('Extension', 'get', ['full_name' => 'org.civicoop.civirules', 'status' => 'installed'])['count']){
      CRM_Core_DAO::executeQuery("INSERT INTO civirule_action (name, label, class_name, is_active) VALUES('emailapi_send_rolesoncase', 'Send Email to related contacts on a case', 'CRM_Emailapi_CivirulesAction_SendToRolesOnCase', 1);");
    }
    return true;
  }

  public function upgrade_1006() {
    if (civicrm_api3('Extension', 'get', ['full_name' => 'org.civicoop.civirules', 'status' => 'installed'])['count']){
      CRM_Core_DAO::executeQuery("INSERT INTO civirule_action (name, label, class_name, is_active) VALUES('emailapi_send_contactreference', 'Send E-mail to a contact reference', 'CRM_Emailapi_CivirulesAction_SendToContactReference', 1);");
    }
    return true;
  }

  public function upgrade_1007() {
    if (civicrm_api3('Extension', 'get', ['full_name' => 'org.civicoop.civirules', 'status' => 'installed'])['count']){
      CRM_Core_DAO::executeQuery("INSERT INTO civirule_action (name, label, class_name, is_active) VALUES('emailapi_send_customfieldvalue', 'Send Email to a custom field value', 'CRM_Emailapi_CivirulesAction_SendToCustomFieldValue', 1);");
    }
    return true;
  }

  public function upgrade_1008() {
    if (civicrm_api3('Extension', 'get', ['full_name' => 'org.civicoop.civirules', 'status' => 'installed'])['count']){
      CRM_Core_DAO::executeQuery("UPDATE civirule_action SET label = 'Send Email to related contacts on a case' WHERE `name` = 'emailapi_send_rolesoncase'");
      CRM_Core_DAO::executeQuery("INSERT INTO civirule_action (name, label, class_name, is_active) VALUES('emailapi_send_casecontactsoncase', 'Send Email to case contacts on a case', 'CRM_Emailapi_CivirulesAction_SendToCaseContactsOnCase', 1);");
    }
    return true;
  }

}
