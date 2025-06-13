<?php

use Civi\Api4\CiviRulesAction;
use Civi\Api4\CiviRulesCondition;
use Civi\Api4\CiviRulesTrigger;

/**
 * Util functions for upgrading
 */
class CRM_Civirules_Utils_Upgrader {

  /**
   * Read a json file and insert the action into the database.
   *
   * The file has the format of:
   * [
   *   {
   *     "name": "activity_add",
   *     "label": "Add activity to contact",
   *     "class_name": "CRM_CivirulesActions_Activity_Add",
   *   }
   * ]
   *
   * @param string $jsonFile
   *
   * @throws \Exception
   */
  public static function insertActionsFromJson(string $jsonFile) {
    $actions = json_decode(file_get_contents($jsonFile), true);
    foreach($actions as $action) {
      $records[] = [
        'name' => $action['name'],
        'label' => $action['label'],
        'class_name' => $action['class_name'],
      ];
    }
    if (!empty($records)) {
      CiviRulesAction::save(FALSE)
        ->setRecords($records)
        ->setMatch(['name'])
        ->execute();
    }
  }

  /**
   * Read a json file and insert the conditions into the database.
   *
   * The file has the format of:
   * [
   *   {
   *     "name": "activity_in_campaign",
   *     "label": "Activity is (not) in Campaign(s)",
   *     "class_name": "CRM_CivirulesConditions_Activity_Campaign",
   *   }
   * ]
   *
   * @param string $jsonFile
   *
   * @throws \Exception
   */
  public static function insertConditionsFromJson(string $jsonFile) {
    $conditions = json_decode(file_get_contents($jsonFile), true);
    foreach($conditions as $condition) {
      $records[] = [
        'name' => $condition['name'],
        'label' => $condition['label'],
        'class_name' => $condition['class_name'],
      ];
    }
    if (!empty($records)) {
      CiviRulesCondition::save(FALSE)
        ->setRecords($records)
        ->setMatch(['name'])
        ->execute();
    }
  }

  /**
   * Read a json file and insert tre triggers to the database.
   *
   * The file has the format of:
   * [
   *   {
   *     "name": "new_activity",
   *     "label": "Activity is added",
   *     "object_name": "Activity",
   *     "op": "create",
   *     "class_name": "CRM_CivirulesPostTrigger_Activity",
   *     "cron": 0
   *   }
   * ]
   *
   * @param string $jsonFile
   *
   * @throws \Exception
   */
  public static function insertTriggersFromJson(string $jsonFile) {
    $triggers = json_decode(file_get_contents($jsonFile), true);
    foreach($triggers as $trigger) {
      if (!empty($trigger['object_name']) && empty($trigger['op'])) {
        \Civi::log('civirules')->error('CiviRules: Op parameter could not be empty for trigger ' . $trigger['name']);
        continue;
      }
      elseif (empty($trigger['object_name']) && !empty($trigger['op'])) {
        \Civi::log('civirules')->error('CiviRules: Object Name parameter could not be empty for trigger ' . $trigger['name']);
        continue;
      }
      if (empty($trigger['object_name']) && empty($trigger['op']) && empty($trigger['class_name'])) {
        \Civi::log('civirules')->error('CiviRules: Object Name, op and class Name parameter could not be empty for trigger ' . $trigger['name']);
        continue;
      }
      $records[] = [
        'name' => $trigger['name'],
        'label' => $trigger['label'],
        'cron' => (bool) ($trigger['cron'] ?? FALSE),
        'object_name' => $trigger['object_name'] ?? NULL,
        'op' => $trigger['op'] ?? NULL,
        'class_name' => $trigger['class_name'] ?? NULL,
      ];
    }
    if (!empty($records)) {
      CiviRulesTrigger::save(FALSE)
        ->setRecords($records)
        ->setMatch(['name'])
        ->execute();
    }
  }

}
