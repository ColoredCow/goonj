<?php
use CRM_Civirules_ExtensionUtil as E;

/**
 * Copyright (C) 2015 Coöperatieve CiviCooP U.A. <http://www.civicoop.org>
 * Licensed to CiviCRM under the AGPL-3.0
 */
class CRM_Civirules_Upgrader extends CRM_Extension_Upgrader_Base {

  /**
   * Perform actions after install
   */
  public function postInstall() {
    $ruleTagOptionGroup = CRM_Civirules_Utils_OptionGroup::getSingleWithName('civirule_rule_tag');
    if (empty($ruleTagOptionGroup)) {
      CRM_Civirules_Utils_OptionGroup::create('civirule_rule_tag', 'Tags for CiviRules', 'Tags used to filter CiviRules on the CiviRules page');
    }
    self::postUpgrade();
  }

  public function uninstall() {
  }

  /**
   * This inserts the triggers/conditions/actions every time extension upgrades are run
   * @return true
   * @throws \Exception
   */
  public static function postUpgrade() {
    // First create a backup because the managed entities are gone
    // so the actions and conditions table are first going to be emptied
    self::civirules_upgrade_to_2x_backup();
    // Check and add/update triggers, actions and conditions
    CRM_Civirules_Utils_Upgrader::insertTriggersFromJson(E::path('sql/triggers.json'));
    CRM_Civirules_Utils_Upgrader::insertConditionsFromJson(E::path('sql/conditions.json'));
    CRM_Civirules_Utils_Upgrader::insertActionsFromJson(E::path('sql/actions.json'));
    return TRUE;
  }

  /**
   * Helper function to create a backup if the current schema version is of a 1.x version.
   * We need this backup to restore missing actions and rules after upgrading.
   */
  public static function civirules_upgrade_to_2x_backup() {
    // Check schema version
    // Schema version 1023 is inserted by a 2x version
    // So if the schema version is lower than 1023 we are still on a 1x version.
    // If empty, we are installing
    $schemaVersion = CRM_Core_DAO::singleValueQuery("SELECT schema_version FROM civicrm_extension WHERE `name` = 'CiviRules'");
    if ($schemaVersion >= 1023 || empty($schemaVersion)) {
      return; // No need for preparing the update.
    }

    if (!CRM_Core_DAO::checkTableExists('civirule_rule_action_backup')) {
      // Backup the current action and condition connected to a civirule
      CRM_Core_DAO::executeQuery("
      CREATE TABLE `civirule_rule_action_backup`
      SELECT `civirule_rule_action`.*, `civirule_action`.`class_name` as `action_class_name`
      FROM `civirule_rule_action`
      INNER JOIN `civirule_action` ON `civirule_rule_action`.`action_id` = `civirule_action`.`id`
    ");
    }
    if (!CRM_Core_DAO::checkTableExists('civirule_rule_action_backup')) {
      CRM_Core_DAO::executeQuery("
      CREATE TABLE `civirule_rule_condition_backup`
      SELECT `civirule_rule_condition`.*, `civirule_condition`.`class_name` as `condition_class_name`
      FROM `civirule_rule_condition`
      INNER JOIN `civirule_condition` ON `civirule_rule_condition`.`condition_id` = `civirule_condition`.`id`
    ");
    }
  }

  public function upgrade_1001() {
    if (CRM_Core_DAO::checkTableExists('civirule_rule')) {
      if (CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civirule_rule', 'event_id')) {
        CRM_Core_DAO::executeQuery("ALTER TABLE `civirule_rule` ADD event_params TEXT NULL AFTER event_id");
      }
    }

    if (CRM_Core_DAO::checkTableExists("civirule_event")) {
      CRM_Core_DAO::executeQuery("
        INSERT INTO civirule_event (name, label, object_name, op, cron, class_name, created_date, created_user_id)
        VALUES
          ('groupmembership', 'Daily trigger for group members', NULL, NULL, 1, 'CRM_CivirulesCronTrigger_GroupMembership',  CURDATE(), 1);
        ");
    }
    return true;
  }
  /**
   * Method for upgrade 1002
   * (rename events to trigger, check https://github.com/CiviCooP/org.civicoop.civirules/issues/42)
   * - rename table civirule_event to civirule_trigger
   * - rename columns event_id, event_params in table civirule_rule to trigger_id, trigger_params
   * - remove index on event_id
   * - add index on trigger_id
   */
  public function upgrade_1002() {
    // rename table civirule_event to civirule_trigger
    if (CRM_Core_DAO::checkTableExists("civirule_event")) {
      CRM_Core_DAO::executeQuery("RENAME TABLE civirule_event TO civirule_trigger");
    } else {
      $this->executeSqlFile('sql/upgrade_1002.sql');
    }
    // rename columns event_id and event_params in civirule_rule
    if (CRM_Core_DAO::checkTableExists("civirule_rule")) {
      $this->ctx->log->info('civirules 1002: Drop fk_rule_event, fk_rule_event_idx.');
      if (CRM_Core_DAO::checkConstraintExists('civirule_rule', 'fk_rule_event')) {
        CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule DROP FOREIGN KEY fk_rule_event;");
      }
      if (CRM_Core_DAO::checkConstraintExists('civirule_rule', 'fk_rule_event_idx')) {
        CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule DROP INDEX fk_rule_event_idx;");
      }
      if (CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civirule_rule', 'event_id')) {
        CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule CHANGE event_id trigger_id INT UNSIGNED;");
      }
      if (CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civirule_rule', 'event_params')) {
        CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule CHANGE event_params trigger_params TEXT;");
      }
      if (!CRM_Core_DAO::checkConstraintExists('civirule_rule', 'fk_rule_trigger')) {
        CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule ADD CONSTRAINT fk_rule_trigger FOREIGN KEY (trigger_id) REFERENCES civirule_trigger(id);");
      }
      if (!CRM_Core_DAO::checkConstraintExists('civirule_rule', 'fk_rule_trigger_idx')) {
        CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule ADD INDEX fk_rule_trigger_idx (trigger_id);");
      }
    }
    return true;
  }

  /**
   * Executes upgrade 1003
   *
   * Changes the class names in civirule_trigger table becasue those have been changed as well
   *
   * @return bool
   */
  public function upgrade_1003() {
    $this->executeSqlFile('sql/update_1003.sql');
    return true;
  }

  /**
   * Executes upgrade 1004
   *
   * Changes the class for entity triggers
   *
   * @return bool
   */
  public function upgrade_1004() {
    CRM_Core_DAO::executeQuery("update `civirule_trigger` set `class_name` = 'CRM_CivirulesPostTrigger_EntityTag' where `object_name` = 'EntityTag';");
    if (!CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civirule_rule_action', 'ignore_condition_with_delay')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE `civirule_rule_action` ADD COLUMN `ignore_condition_with_delay` TINYINT NULL default 0 AFTER `delay`");
    }
    return true;
  }

  public function upgrade_1005() {
    CRM_Core_DAO::executeQuery("update `civirule_trigger` SET `class_name` = 'CRM_CivirulesPostTrigger_Case' where `object_name` = 'Case'");
    return true;
  }

  /**
   * Update for a trigger class for relationships
   *
   * See https://github.com/CiviCooP/org.civicoop.civirules/issues/83
   * @return bool
   */
  public function upgrade_1006() {
    CRM_Core_DAO::executeQuery("update `civirule_trigger` SET `class_name` = 'CRM_CivirulesPostTrigger_Relationship' where `object_name` = 'Relationship'");
    return true;
  }

  /**
   * Update for issue 97 - add description and help_text to civirule_rule
   * See https://github.com/CiviCooP/org.civicoop.civirules/issues/97
   * @return bool
   */
  public function upgrade_1007() {
    if (CRM_Core_DAO::checkTableExists('civirule_rule')) {
      if (!CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civirule_rule', 'description')) {
        CRM_Core_DAO::executeQuery("ALTER TABLE `civirule_rule` ADD COLUMN `description` VARCHAR(256) NULL AFTER `is_active`");
      }
      if (!CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civirule_rule', 'help_text')) {
        CRM_Core_DAO::executeQuery("ALTER TABLE `civirule_rule` ADD COLUMN `help_text` TEXT NULL AFTER `description`");
      }
    }
    return true;
  }

  /**
   * Update for changed recurring contribution class names
   */
  public function upgrade_1008() {
    $query = 'UPDATE civirule_condition SET class_name = %1 WHERE class_name = %2';
    $paramsRecurCount = array(
      1 => array('CRM_CivirulesConditions_ContributionRecur_Count', 'String'),
      2 => array('CRM_CivirulesConditions_Contribution_CountRecurring', 'String'));
    CRM_Core_DAO::executeQuery($query, $paramsRecurCount);

    $paramsRecurIs = array(
      1 => array('CRM_CivirulesConditions_ContributionRecur_DonorIsRecurring', 'String'),
      2 => array('CRM_CivirulesConditions_Contribution_DonorIsRecurring', 'String'));
    CRM_Core_DAO::executeQuery($query, $paramsRecurIs);

    $paramsRecurEnd = array(
      1 => array('CRM_CivirulesConditions_ContributionRecur_EndDate', 'String'),
      2 => array('CRM_CivirulesConditions_Contribution_RecurringEndDate', 'String'));
    CRM_Core_DAO::executeQuery($query, $paramsRecurEnd);

    return true;
  }

  /**
   * Update for rule tag (check <https://github.com/CiviCooP/org.civicoop.civirules/issues/98>)
   */
  public function upgrade_1020() {
    $this->executeSqlFile('sql/upgrade_1020.sql');
    $ruleTagOptionGroup = CRM_Civirules_Utils_OptionGroup::getSingleWithName('civirule_rule_tag');
    if (empty($ruleTagOptionGroup)) {
      CRM_Civirules_Utils_OptionGroup::create('civirule_rule_tag', 'Tags for CiviRules', 'Tags used to filter CiviRules on the CiviRules page');
    }
    return TRUE;
  }

  /**
   * Update to update class for entity tag triggers
   */
  public function upgrade_1021() {
    $query = 'UPDATE civirule_trigger SET class_name = %1 WHERE name LiKE %2';
    CRM_Core_DAO::executeQuery($query, array(
      1 => array('CRM_CivirulesPostTrigger_EntityTag', 'String'),
      2 => array('%entity_tag%', 'String'),
    ));
    $query = 'UPDATE civirule_trigger SET label = %1 WHERE name LiKE %2';
    CRM_Core_DAO::executeQuery($query, array(
      1 => array('Contact is tagged (tag is added to contact)', 'String'),
      2 => array('new_entity_tag', 'String'),
    ));
    $query = 'UPDATE civirule_trigger SET label = %1 WHERE name LiKE %2';
    CRM_Core_DAO::executeQuery($query, array(
      1 => array('Contact is un-tagged (tag is removed from contact)', 'String'),
      2 => array('deleted_entity_tag', 'String'),
    ));
    return TRUE;
  }

  public function upgrade_1022() {
    CRM_Core_DAO::executeQuery("
      UPDATE civirule_trigger
      SET class_name = 'CRM_CivirulesPostTrigger_Contribution'
      WHERE object_name = 'Contribution'
    ");
    return TRUE;
  }

  /**
   * Upgrade 1023 (issue #189 - replace managed entities with inserts
   *
   * @return bool
   */
  public function upgrade_1023() {
    $this->ctx->log->info('Applying update 1023 - remove unwanted managed entities');
    $query = "DELETE FROM civicrm_managed WHERE module = %1 AND entity_type IN(%2, %3, %4)";
    $params = array(
      1 => array("org.civicoop.civirules", "String"),
      2 => array("CiviRuleAction", "String"),
      3 => array("CiviRuleCondition", "String"),
      4 => array("CiviRuleTrigger", "String"),
    );
    if (CRM_Core_DAO::checkTableExists("civicrm_managed")) {
      CRM_Core_DAO::executeQuery($query, $params);
    }

    // now insert all Civirules Actions and Conditions
    // commented obsolete insert actions
    // $this->executeSqlFile('sql/insertCivirulesActions.sql');
    // $this->executeSqlFile('sql/insertCivirulesConditions.sql');

    // Now check whether we have a backup and restore the backup
    if (CRM_Core_DAO::checkTableExists('civirule_rule_action_backup')) {
      CRM_Core_DAO::executeQuery("TRUNCATE `civirule_rule_action`");
      CRM_Core_DAO::executeQuery("
        INSERT INTO `civirule_rule_action`
        SELECT `civirule_rule_action_backup`.`id`,
        `civirule_rule_action_backup`.`rule_id`,
        `civirule_action`.`id` as `action_id`,
        `civirule_rule_action_backup`.`action_params`,
        `civirule_rule_action_backup`.`delay`,
        `civirule_rule_action_backup`.`ignore_condition_with_delay`,
        `civirule_rule_action_backup`.`is_active`
        FROM `civirule_rule_action_backup`
        INNER JOIN `civirule_action` ON `civirule_rule_action_backup`.`action_class_name` = `civirule_action`.`class_name`
      ");
      CRM_Core_DAO::executeQuery("DROP TABLE `civirule_rule_action_backup`");
    }
    if (CRM_Core_DAO::checkTableExists('civirule_rule_condition_backup')) {
      CRM_Core_DAO::executeQuery("TRUNCATE `civirule_rule_condition`");
      CRM_Core_DAO::executeQuery("
        INSERT INTO `civirule_rule_condition`
        SELECT `civirule_rule_condition_backup`.`id`,
        `civirule_rule_condition_backup`.`rule_id`,
        `civirule_rule_condition_backup`.`condition_link`,
        `civirule_condition`.`id` as `condition_id`,
        `civirule_rule_condition_backup`.`condition_params`,
        `civirule_rule_condition_backup`.`is_active`
        FROM `civirule_rule_condition_backup`
        INNER JOIN `civirule_condition` ON `civirule_rule_condition_backup`.`condition_class_name` = `civirule_condition`.`class_name`
      ");
      CRM_Core_DAO::executeQuery("DROP TABLE `civirule_rule_condition_backup`");
    }


    // Update the participant trigger and add the event conditions
    CRM_Core_DAO::executeQuery("UPDATE `civirule_trigger` SET `class_name` = 'CRM_CivirulesPostTrigger_Participant' WHERE `object_name` = 'Participant'");

    return TRUE;
  }

  /**
   * Upgrade 1024 (issue #138 rules for trash en untrash)
   *
   * @return bool
   */
  public function upgrade_1024() {
    CRM_Core_DAO::executeQuery("UPDATE `civirule_trigger` SET `class_name`='CRM_CivirulesPostTrigger_ContactTrashed', `op`='update' WHERE `name` in ('trashed_contact','trashed_individual','trashed_organization','trashed_household')");
    CRM_Core_DAO::executeQuery("UPDATE `civirule_trigger` SET `class_name`='CRM_CivirulesPostTrigger_ContactRestored', `op`='update' WHERE `name` in ('restored_contact','restored_individual','restored_organization','restored_household')");
    return TRUE;
  }

  public function upgrade_2000() {
    // Stub function to make sure the schema version jumps to 2000, indicating we are on 2.x version.
    return TRUE;
  }

  /**
   * Upgrade 2015 remove custom search and add manage rules form
   */
  public function upgrade_2015() {
    $this->ctx->log->info('Applying update 2015');
    // remove custom search
    try {
      $optionValueId = civicrm_api3('OptionValue', 'getvalue', [
        'option_group_id' => 'custom_search',
        'name' => 'CRM_Civirules_Form_Search_Rules',
        'return' => 'id'
      ]);
      if ($optionValueId) {
        civicrm_api3('OptionValue', 'delete', ['id' => $optionValueId]);
      }
    } catch (CRM_Core_Exception $ex) {
    }
    return TRUE;
  }

  /**
   * Upgrade 2020 - change constraints for civirule_rule to ON DELETE CASCADE
   *
   * @return bool
   */
  public function upgrade_2020() {
    $this->ctx->log->info('Applying update 2020');
    // civirule_rule_tag table
    $drop = "ALTER TABLE civirule_rule_tag DROP FOREIGN KEY fk_rule_id";
    CRM_Core_DAO::executeQuery($drop);
    $cascade = "ALTER TABLE civirule_rule_tag ADD CONSTRAINT fk_rule_id
    FOREIGN KEY (rule_id) REFERENCES civirule_rule (id) ON DELETE CASCADE";
    CRM_Core_DAO::executeQuery($cascade);
    // civirule_rule_condition table
    $drop = "ALTER TABLE civirule_rule_condition DROP FOREIGN KEY fk_rc_condition";
    CRM_Core_DAO::executeQuery($drop);
    $cascade = "ALTER TABLE civirule_rule_condition ADD CONSTRAINT fk_rc_condition
    FOREIGN KEY (condition_id) REFERENCES civirule_condition (id) ON DELETE CASCADE";
    CRM_Core_DAO::executeQuery($cascade);
    $drop = "ALTER TABLE civirule_rule_condition DROP FOREIGN KEY fk_rc_rule";
    CRM_Core_DAO::executeQuery($drop);
    $cascade = "ALTER TABLE civirule_rule_condition ADD CONSTRAINT fk_rc_rule
    FOREIGN KEY (rule_id) REFERENCES civirule_rule (id) ON DELETE CASCADE";
    CRM_Core_DAO::executeQuery($cascade);
    // civirule_rule_action table
    $drop = "ALTER TABLE civirule_rule_action DROP FOREIGN KEY fk_ra_action";
    CRM_Core_DAO::executeQuery($drop);
    $cascade = "ALTER TABLE civirule_rule_action ADD CONSTRAINT fk_ra_action
    FOREIGN KEY (action_id) REFERENCES civirule_action (id) ON DELETE CASCADE";
    CRM_Core_DAO::executeQuery($cascade);
    $drop = "ALTER TABLE civirule_rule_action DROP FOREIGN KEY fk_ra_rule";
    CRM_Core_DAO::executeQuery($drop);
    $cascade = "ALTER TABLE civirule_rule_action ADD CONSTRAINT fk_ra_rule
    FOREIGN KEY (rule_id) REFERENCES civirule_rule (id) ON DELETE CASCADE";
    CRM_Core_DAO::executeQuery($cascade);
    return TRUE;
  }

  /**
   * Upgrade 2025 - change constraints for civirule_rule to ON DELETE CASCADE
   * (this is a repeat of upgrade_2020 because of issue 40 (https://lab.civicrm.org/extensions/civirules/issues/40)
   *
   * @return bool
   */
  public function upgrade_2025() {
    $this->ctx->log->info('Applying update 2025');
    // civirule_rule_tag table
    $drop = "ALTER TABLE civirule_rule_tag DROP FOREIGN KEY fk_rule_id";
    CRM_Core_DAO::executeQuery($drop);
    $cascade = "ALTER TABLE civirule_rule_tag ADD CONSTRAINT fk_rule_id
    FOREIGN KEY (rule_id) REFERENCES civirule_rule (id) ON DELETE CASCADE";
    CRM_Core_DAO::executeQuery($cascade);
    // civirule_rule_condition table
    $drop = "ALTER TABLE civirule_rule_condition DROP FOREIGN KEY fk_rc_condition";
    CRM_Core_DAO::executeQuery($drop);
    $cascade = "ALTER TABLE civirule_rule_condition ADD CONSTRAINT fk_rc_condition
    FOREIGN KEY (condition_id) REFERENCES civirule_condition (id) ON DELETE CASCADE";
    CRM_Core_DAO::executeQuery($cascade);
    $drop = "ALTER TABLE civirule_rule_condition DROP FOREIGN KEY fk_rc_rule";
    CRM_Core_DAO::executeQuery($drop);
    $cascade = "ALTER TABLE civirule_rule_condition ADD CONSTRAINT fk_rc_rule
    FOREIGN KEY (rule_id) REFERENCES civirule_rule (id) ON DELETE CASCADE";
    CRM_Core_DAO::executeQuery($cascade);
    // civirule_rule_action table
    $drop = "ALTER TABLE civirule_rule_action DROP FOREIGN KEY fk_ra_action";
    CRM_Core_DAO::executeQuery($drop);
    $cascade = "ALTER TABLE civirule_rule_action ADD CONSTRAINT fk_ra_action
    FOREIGN KEY (action_id) REFERENCES civirule_action (id) ON DELETE CASCADE";
    CRM_Core_DAO::executeQuery($cascade);
    $drop = "ALTER TABLE civirule_rule_action DROP FOREIGN KEY fk_ra_rule";
    CRM_Core_DAO::executeQuery($drop);
    $cascade = "ALTER TABLE civirule_rule_action ADD CONSTRAINT fk_ra_rule
    FOREIGN KEY (rule_id) REFERENCES civirule_rule (id) ON DELETE CASCADE";
    CRM_Core_DAO::executeQuery($cascade);
    return TRUE;
  }

  /**
   * Upgrade 2035 - remove trigger case added (see https://lab.civicrm.org/extensions/civirules/issues/45)
   *
   * @return bool
   */
  public function upgrade_2035() {
    $this->ctx->log->info('Applying update 2035 - disabling all rules with trigger Case Added and remove trigger Case Added');
    $caseAddedRules = [];
    // retrieve id of trigger case added
    $query = "SELECT id FROM civirule_trigger WHERE name = %1";
    $triggerId = (int) CRM_Core_DAO::singleValueQuery($query, [1 => ["new_case", "String"]]);
    if ($triggerId) {
      // check if there are any rules with the case added trigger and if so, add message line and copy rule to table
      $count = (int) CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM civirule_rule WHERE trigger_id = %1", [1 => [$triggerId, "Integer"]]);
      if ($count > 0) {
        // keep all relevant records in separate tables
        $this->executeSqlFile('sql/createUpgrade2035Tables.sql');
        $pre210 = new CRM_Civirules_SaveUpgrade2035($triggerId);
        $pre210->saveOldRecords();
        CRM_Core_Session::setStatus(E::ts("The upgrade has deleted ") . $count . E::ts(" rules with trigger Case is added. \n\n These rules and their data have been saved in tables civirule_per210_rule, civirule_pre210_rule_action and civirule_pre210_condition. \n\n You need to manually re-create those rules with the trigger Case Activitity added with condition activity_type is Open Case (see https://lab.civicrm.org/extensions/civirules/issues/45"), E::ts("Upgrade has DELETED rules!"), "info");
        // delete all current rules with trigger case added
        CRM_Core_DAO::executeQuery("DELETE FROM civirule_rule  WHERE trigger_id = %1", [1 => [$triggerId, "Integer"]]);
      }
      // delete trigger case added
      CRM_Core_DAO::executeQuery("DELETE FROM civirule_trigger WHERE id = %1", [1 => [$triggerId, "Integer"]]);
    }
    else {
      Civi::log()->warning(E::ts('Could not find a Civirules trigger with name new_case, this could be a problem? Please check carefully if you do not have any rules with the trigger Case is added and do not have a trigger called Case is added. If that is true, you are fine. If not, read the README.md of the Civirules extension on upgrade to 2.10.'));
    }
    return TRUE;
  }

  public function upgrade_2045() {
    \CRM_Core_DAO::executeQuery("
    ALTER TABLE civirule_rule_log
    ADD COLUMN entity_table VARCHAR (255) NULL,
    ADD COLUMN entity_id INT UNSIGNED NULL");
    return TRUE;
  }

  public function upgrade_2067() {
    $this->ctx->log->info('Applying update 2067 - Add "is_debug" field to civirule_rule.');
    if (!CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civirule_rule', 'is_debug')) {
      CRM_Core_DAO::executeQuery('ALTER TABLE civirule_rule ADD COLUMN `is_debug` tinyint DEFAULT 0');
    }
    return TRUE;
  }

  public function upgrade_2072() {
    $this->ctx->log->info('Applying update 2071 - Add conditions contact has tag, activity has tag, case has tag, file has tag');
    // rename existing contact has tag and warn user of changes
    $conditionName = "contact_has_tag";
    $className = "CRM_CivirulesConditions_Contact_HasTag";
    $conditionId = CRM_Core_DAO::singleValueQuery("SELECT id FROM civirule_condition WHERE name = %1", [
      1 => [$conditionName, "String"],
      2 => [$className, "String"],
      ]);
    if ($conditionId) {
      // check if there are any usages of this condition and if so, warn user
      $query = "SELECT a.rule_id, b.label FROM civirule_rule_condition AS a
        JOIN civirule_rule AS b ON a.rule_id = b.id WHERE a.condition_id = %1";
      $dao = CRM_Core_DAO::executeQuery($query, [1 => [(int) $conditionId, "Integer"]]);
      while ($dao->fetch()) {
        $message = E::ts("The condition Entity Has/Does Not Have Tag is used in the rule ") . $dao->label . E::ts(" (rule ID ") . $dao->rule_id
          . E::ts(") but this condition is now changed to Contact Has/Does Not Have Tag. Please inspect this rule to see if the configuration is still applicable");
        Civi::log()->warning($message);
        CRM_Core_Session::setStatus($message, E::ts("Condition on rule [%1] changed", [1 => $dao->rule_id]), 'error');
      }
      $update = "UPDATE civirule_condition SET class_name = %1, label = %2 WHERE id = %3";
      CRM_Core_DAO::executeQuery($update, [
        1 => [$className, "String"],
        2 => ["Contact Has/Does Not Have Tag", "String"],
        3 => [(int) $conditionId, "Integer"],
      ]);
    }
    return TRUE;
  }

  public function upgrade_2082() {
    $this->ctx->log->info('Update tables to match schema cleanup');
    CRM_Core_DAO::executeQuery("ALTER TABLE civirule_action MODIFY COLUMN is_active tinyint NOT NULL DEFAULT 1");
    CRM_Core_DAO::executeQuery("ALTER TABLE civirule_condition MODIFY COLUMN is_active tinyint NOT NULL DEFAULT 1");

    CRM_Core_DAO::executeQuery("ALTER TABLE civirule_trigger MODIFY COLUMN is_active tinyint NOT NULL DEFAULT 1");
    CRM_Core_DAO::executeQuery("ALTER TABLE civirule_trigger MODIFY COLUMN cron tinyint DEFAULT 0");

    CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule_action MODIFY COLUMN is_active tinyint NOT NULL DEFAULT 1");
    CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule_condition MODIFY COLUMN is_active tinyint NOT NULL DEFAULT 1");

    CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule MODIFY COLUMN is_active tinyint NOT NULL DEFAULT 1");
    CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule MODIFY COLUMN created_user_id int unsigned DEFAULT NULL COMMENT 'FK to Contact ID'");
    CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule MODIFY COLUMN modified_user_id int unsigned DEFAULT NULL COMMENT 'FK to Contact ID'");

    if (!CRM_Core_BAO_SchemaHandler::checkFKExists('civirule_rule', 'FK_civirule_rule_created_user_id')) {
      CRM_Core_DAO::executeQuery("UPDATE civirule_rule rl SET created_user_id  = NULL
WHERE created_user_id NOT IN (select id from civicrm_contact cc where rl.created_user_id = cc.id)");
      CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule ADD CONSTRAINT FK_civirule_rule_created_user_id FOREIGN KEY (`created_user_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL");
    }
    if (!CRM_Core_BAO_SchemaHandler::checkFKExists('civirule_rule', 'FK_civirule_rule_modified_user_id')) {
      CRM_Core_DAO::executeQuery("UPDATE civirule_rule rl SET modified_user_id  = NULL
WHERE modified_user_id NOT IN (select id from civicrm_contact cc where rl.modified_user_id = cc.id)");
      CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule ADD CONSTRAINT FK_civirule_rule_modified_user_id FOREIGN KEY (`modified_user_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL");
    }

    if (!CRM_Core_BAO_SchemaHandler::checkFKExists('civirule_rule_log', 'FK_civirule_rule_log_rule_id')) {
      CRM_Core_DAO::executeQuery("
UPDATE civirule_rule_log rl SET rule_id = NULL
WHERE rule_id NOT IN (select id from civirule_rule r where rl.rule_id = r.id)
      ");
      CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule_log ADD CONSTRAINT FK_civirule_rule_log_rule_id FOREIGN KEY (`rule_id`) REFERENCES `civirule_rule`(`id`) ON DELETE SET NULL");
    }

    if (!CRM_Core_BAO_SchemaHandler::checkFKExists('civirule_rule_log', 'FK_civirule_rule_log_contact_id')) {
      CRM_Core_DAO::executeQuery("
UPDATE civirule_rule_log rl SET contact_id = NULL
WHERE contact_id NOT IN (select id from civicrm_contact c where c.id=rl.contact_id)
      ");
      CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule_log ADD CONSTRAINT FK_civirule_rule_log_contact_id FOREIGN KEY (`contact_id`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL");
    }

    return TRUE;
  }

  public function upgrade_2084() {
    $this->ctx->log->info('Applying update 2084');

    $this->ctx->log->info('Convert CiviRulesRule.created_date/modified_date to timestamp and default to CURRENT_TIMESTAMP');
    CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule MODIFY COLUMN created_date timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL COMMENT 'When was this item created'");
    CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule MODIFY COLUMN modified_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When was this item modified'");
    $this->ctx->log->info('Convert CiviRulesRuleLog.log_date to timestamp and default to CURRENT_TIMESTAMP');
    CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule_log MODIFY COLUMN log_date timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL");
    $this->ctx->log->info('Adding created/modified date to civirule_rule_action');
    if (!CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civirule_rule_action', 'created_date')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule_action ADD COLUMN created_date timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL COMMENT 'RuleAction Created Date'");
    }
    if (!CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civirule_rule_action', 'modified_date')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule_action ADD COLUMN modified_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'RuleAction Modified Date'");
    }
    $this->ctx->log->info('Adding created/modified date to civirule_rule_condition');
    if (!CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civirule_rule_condition', 'created_date')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule_condition ADD COLUMN created_date timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL COMMENT 'RuleCondition Created Date'");
    }
    if (!CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civirule_rule_condition', 'modified_date')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule_condition ADD COLUMN modified_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'RuleCondition Modified Date'");
    }

    $this->ctx->log->info('Adding Weight to RuleConditions and RuleActions');
    if (!CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civirule_rule_action', 'weight')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule_action ADD COLUMN `weight` int DEFAULT 0 NOT NULL COMMENT 'Ordering of the RuleActions'");
    }
    if (!CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civirule_rule_condition', 'weight')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE civirule_rule_condition ADD COLUMN `weight` int DEFAULT 0 NOT NULL COMMENT 'Ordering of the RuleConditions'");
    }

    $this->ctx->log->info('Dropping created/modified date/user from action,condition,trigger tables');
    $tablesToDropFields = ['civirule_action', 'civirule_condition', 'civirule_trigger'];
    $fieldsToDrop = ['created_date', 'modified_date', 'created_user_id', 'modified_user_id'];
    foreach ($tablesToDropFields as $tableName) {
      foreach ($fieldsToDrop as $field) {
        if (CRM_Core_BAO_SchemaHandler::checkIfFieldExists($tableName, $field)) {
          CRM_Core_BAO_SchemaHandler::dropColumn($tableName, $field);
        }
      }
    }
    return TRUE;
  }

  /**
   * For developers:
   * since CiviRules 2.28 it is not needed to create an upgrade if you created a new condition, action or trigger.
   * This is done in the function civirules_civicrm_managed which is called as soon as the cached is cleared.
   */

}

