<?php
use CRM_Cityselector_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Cityselector_Upgrader extends CRM_Extension_Upgrader_Base {

  public function uninstall() {
    Civi::settings()->set('cityselector_parent', NULL);
    $this->executeSqlFile('sql/uninstall.sql');
    return TRUE;
  }

}
