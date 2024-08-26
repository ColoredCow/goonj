<?php

/**
 * Collection of upgrade steps for Goonj.
 */
class CRM_Goonjcustom_Upgrader extends CRM_Extension_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * @return TRUE on success
   */
  public function upgrade_1001(): bool {
    $this->ctx->log->info('Applying update 1001');
    $this->executeSqlFile('sql/insertGoonjCustomActions.sql');
    return TRUE;
  }

  /**
   *
   */
  public function upgrade_1002(): bool {
    $this->ctx->log->info('Applying update 1002');
    $this->executeSqlFile('sql/populateInductionSummaryCustomFieldAction.sql');
    return TRUE;
  }

  /**
   *
   */
  public function upgrade_1003(): bool {
    $this->ctx->log->info('Applying update 1003');
    $this->executeSqlFile('sql/createEventForContact.sql');
    return TRUE;
  }

}
