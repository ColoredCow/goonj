<?php
use CRM_Inlay_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Inlay_Upgrader extends CRM_Extension_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Create option groups
   */
  public function install() {
    $this->executeSqlFile('sql/createAssetTable.sql');

    // Create OptionGroup for CSRF Origins.
    $optionGroupID = \Civi\Api4\OptionGroup::get(FALSE)
      ->setCheckPermissions(FALSE)
      ->addWhere('name', '=', 'inlay_cors_origins')
      ->execute()->first()['id'] ?? NULL;

    if (!$optionGroupID) {
      $optionGroupID = \Civi\Api4\OptionGroup::create(FALSE)
        ->setCheckPermissions(FALSE)
        ->addValue('name', 'inlay_cors_origins')
        ->addValue('title', ts('Inlay CORS origins'))
        ->addValue('description', 'List of origins that are valid CORS origins for Inlays (from the Inlay extension)')
        ->addValue('data_type', 'String')
        ->addValue('is_active', TRUE)
        ->execute()->first()['id'];
    }

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
  public function uninstall() {
    $this->executeSqlFile('sql/dropAssetTable.sql');
    $filenameStub = Civi::paths()->getPath("[civicrm.files]/inlay-");
    $foundFiles = glob($filenameStub . '*.js');
    foreach ($foundFiles as $file) {
      \Civi::log()->warning("Uninstalling Inlay: deleting bundle: $file");
      unlink($file);
    }
    $assetDir = Civi::paths()->getPath("[civicrm.files]/inlay");
    if (is_dir($assetDir)) {
      foreach (glob("$assetDir/*") as $file) {
        \Civi::log()->warning("Uninstalling Inlay: deleting asset: $file");
        unlink($file);
      }
      \Civi::log()->warning("Uninstalling Inlay: deleting asset dir: $assetDir");
      rmdir($assetDir);
    }
  }

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
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   */
  // public function upgrade_4200() {
  //   $this->ctx->log->info('Applying update 4200');
  //   CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
  //   CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
  //   return TRUE;
  // }


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
   * Add the InlayConfigSet table.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_001() {
    $this->ctx->log->info('Update 001: adding config sets table');
    // this path is relative to the extension base dir
    $this->executeSqlFile('sql/upgrade_001.sql');
    return TRUE;
  }

  /**
   * Add the inlay_assets table.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_002() {
    $this->ctx->log->info('Update 002: add inlay_assets table');
    // this path is relative to the extension base dir
    $this->executeSqlFile('sql/createAssetTable.sql');
    return TRUE;
  }

  /**
   * Add inlay_status.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_003() {
    $this->ctx->log->info('Update 003: add inlay_status_column');
    // this path is relative to the extension base dir
    $this->addColumn('civicrm_inlay', 'status', "varchar(20) NOT NULL COMMENT 'on, off or broken'");
    $this->executeSql('UPDATE civicrm_inlay SET status = "on";');
    return TRUE;
  }

  /**
   * Add inlay_status.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_004() {
    $this->ctx->log->info('Update 004: add default to inlay_status_column');
    $this->executeSql('ALTER TABLE civicrm_inlay MODIFY status varchar(20) NOT NULL DEFAULT "on" COMMENT "on, off or broken";');
    return TRUE;
  }

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

}
