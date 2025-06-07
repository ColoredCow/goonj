<?php

/**
 *
 */
class CRM_Civiglific_Upgrader extends CRM_Extension_Upgrader_Base {

  /**
   *
   */
  public function upgrade_1001(): bool {
    CRM_Core_DAO::executeQuery("
      CREATE TABLE IF NOT EXISTS civicrm_glific_group_map (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        group_id INT UNSIGNED NOT NULL,
        collection_id VARCHAR(255) NOT NULL,
        last_sync_date DATETIME DEFAULT NULL,
        FOREIGN KEY (group_id) REFERENCES civicrm_group(id) ON DELETE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    return TRUE;
  }

}
