<?php

/**
 * DAO for civicrm_glific_group_map.
 */
class CRM_Civiglific_DAO_GlificGroupMap extends CRM_Core_DAO {

  public $id;
  public $group_id;
  public $collection_id;
  public $last_sync_date;

  /**
   * Static instance to avoid multiple calls.
   */
  static $_fields = NULL;

  /**
   * Return the table name.
   */
  public static function getTableName() {
    return 'civicrm_glific_group_map';
  }

  /**
   * Returns foreign keys for the table.
   */
  public static function getReferenceColumns() {
    if (!self::$_links) {
      self::$_links = [
        new CRM_Core_Reference_Basic(self::getTableName(), 'group_id', 'civicrm_group', 'id'),
      ];
    }
    return self::$_links;
  }

  /**
   * Define table fields.
   */
  public static function &fields() {
    if (!self::$_fields) {
      self::$_fields = [
        'id' => [
          'name' => 'id',
          'type' => CRM_Utils_Type::T_INT,
          'required' => TRUE,
          'title' => ts('ID'),
        ],
        'group_id' => [
          'name' => 'group_id',
          'type' => CRM_Utils_Type::T_INT,
          'required' => TRUE,
          'FKClassName' => 'CRM_Core_DAO_Group',
          'title' => ts('Group'),
        ],
        'collection_id' => [
          'name' => 'collection_id',
          'type' => CRM_Utils_Type::T_STRING,
          'title' => ts('Collection ID'),
          'maxlength' => 255,
          'size' => CRM_Utils_Type::BIG,
        ],
        'last_sync_date' => [
          'name' => 'last_sync_date',
          'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
          'title' => ts('Last Sync Date'),
        ],
      ];
    }
    return self::$_fields;
  }

  /**
   * Return a mapping from field name to the corresponding database column name.
   */
  public static function fieldKeys() {
    $keys = [];
    foreach (self::fields() as $name => $field) {
      $keys[$name] = $name;
    }
    return $keys;
  }

}
