<?php
use CRM_Crontab_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Crontab_Upgrader extends CRM_Extension_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed.
   *
  public function install() {
    $this->executeSqlFile('sql/myinstall.sql');
  }

  /**
   * Example: Work with entities usually not available during the install step.
   *
   * This method can be used for any post-install tasks. For example, if a step
   * of your installation depends on accessing an entity that is itself
   * created during the installation (e.g., a setting or a managed entity), do
   * so here to avoid order of operation problems.
   */
  // public function postInstall() {
  //  $customFieldId = civicrm_api3('CustomField', 'getvalue', array(
  //    'return' => array("id"),
  //    'name' => "customFieldCreatedViaManagedHook",
  //  ));
  //  civicrm_api3('Setting', 'create', array(
  //    'myWeirdFieldSetting' => array('id' => $customFieldId, 'weirdness' => 1),
  //  ));
  // }

  /**
   * Example: Run an external SQL script when the module is uninstalled.
   */
  // public function uninstall() {
  //  $this->executeSqlFile('sql/myuninstall.sql');
  // }

  /**
   * Example: Run a simple query when a module is enabled.
   */
  // public function enable() {
  //  CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  // }

  /**
   * Example: Run a simple query when a module is disabled.
   */
  // public function disable() {
  //   CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 0 WHERE bar = "whiz"');
  // }

  /**
   * Add new columns.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1100() {
    $this->ctx->log->info('Adding new columns');
    $fields = [
      'crontab_apply' => 'TINYINT(4) NULL DEFAULT NULL COMMENT "Is Crontab functionality enabled?"',
      'crontab_hour_range' => 'TINYINT(4) NULL DEFAULT NULL COMMENT "Use Hour range to execute job"',
      'crontab_day_range' => 'TINYINT(4) NULL DEFAULT NULL COMMENT "Use Day range to execute job"',
      'crontab_date_time_start' => 'datetime DEFAULT NULL COMMENT "When to Start Job Execution"',
      'crontab_date_time_end' => 'datetime DEFAULT NULL COMMENT "When to Start Job Execution"',
      'crontab_time_from' => 'time DEFAULT NULL',
      'crontab_time_to' => 'time DEFAULT NULL'
    ];
    $queries = [];
    foreach ($fields as $column => $properties) {
      if (!CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civicrm_job', $column, FALSE)) {
        $queries[$column] = "ALTER TABLE `civicrm_job` ADD COLUMN `$column` $properties";
      }
    }
    if (!empty($queries)) {
      foreach ($queries as $query) {
        CRM_Core_DAO::executeQuery($query, [], TRUE, NULL, FALSE, FALSE);
      }
    }
    // Update checkbox for existing records.
    $sql = 'UPDATE civicrm_job SET crontab_apply = 1 WHERE crontab_frequency IS NOT NULL';
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);

    return TRUE;
  }

  /**
   * Example: Run an external SQL script.
   *
   * @return TRUE on success
   * @throws Exception
   */
  // public function upgrade_4201() {
  //   $this->ctx->log->info('Applying update 4201');
  //   // this path is relative to the extension base dir
  //   $this->executeSqlFile('sql/upgrade_4201.sql');
  //   return TRUE;
  // }


  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk.
   *
   * @return TRUE on success
   * @throws Exception
   */
  // public function upgrade_4202() {
  //   $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

  //   $this->addTask(E::ts('Process first step'), 'processPart1', $arg1, $arg2);
  //   $this->addTask(E::ts('Process second step'), 'processPart2', $arg3, $arg4);
  //   $this->addTask(E::ts('Process second step'), 'processPart3', $arg5);
  //   return TRUE;
  // }
  // public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  // public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  // public function processPart3($arg5) { sleep(10); return TRUE; }

  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
   */
  // public function upgrade_4203() {
  //   $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

  //   $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
  //   $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
  //   for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
  //     $endId = $startId + self::BATCH_SIZE - 1;
  //     $title = E::ts('Upgrade Batch (%1 => %2)', array(
  //       1 => $startId,
  //       2 => $endId,
  //     ));
  //     $sql = '
  //       UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
  //       WHERE id BETWEEN %1 and %2
  //     ';
  //     $params = array(
  //       1 => array($startId, 'Integer'),
  //       2 => array($endId, 'Integer'),
  //     );
  //     $this->addTask($title, 'executeSql', $sql, $params);
  //   }
  //   return TRUE;
  // }

  public function upgrade_1001() {
    if (!CRM_Core_BAO_SchemaHandler::checkIfFieldExists('civicrm_job', 'crontab_hour_range')) {
      $this->ctx->log->info('Applying crontab update 1001.  Adding crontab_hour_range to the } table.');
      CRM_Core_DAO::executeQuery("ALTER TABLE civicrm_job ADD crontab_hour_range TINYINT(4) NULL DEFAULT NULL COMMENT 'Use Hour range to execute job'");
    }

    return TRUE;
  }

}
