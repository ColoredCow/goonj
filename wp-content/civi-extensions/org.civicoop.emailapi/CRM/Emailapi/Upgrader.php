<?php

use CRM_Emailapi_ExtensionUtil as E;
/**
 * Collection of upgrade steps
 */
class CRM_Emailapi_Upgrader extends CRM_Extension_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Upgrader to update old civicrm_queue_items so they reflect the new class names.
   */
  public function upgrade_1008() {
    if (civicrm_api3('Extension', 'get', ['full_name' => 'org.civicoop.civirules', 'status' => 'installed'])['count']){
      CRM_Core_DAO::executeQuery("UPDATE `civicrm_queue_item` SET data = REPLACE(data, '\"class_name\";s:28:\"CRM_Emailapi_CivirulesAction\"', '\"class_name\";s:33:\"CRM_Emailapi_CivirulesAction_Send\"')  WHERE data like '%\"class_name\";s:28:\"CRM_Emailapi_CivirulesAction\"%'");
      CRM_Core_DAO::executeQuery("UPDATE `civicrm_queue_item` SET data = REPLACE(data, 'O:28:\"CRM_Emailapi_CivirulesAction\"', 'O:33:\"CRM_Emailapi_CivirulesAction_Send\"') WHERE data like '%O:28:\"CRM_Emailapi_CivirulesAction\"%' ");
    }
    return true;
  }

  // Do not add upgrade steps for new actions. Add them to civirules/actions.json and they will be automatically loaded!

}
