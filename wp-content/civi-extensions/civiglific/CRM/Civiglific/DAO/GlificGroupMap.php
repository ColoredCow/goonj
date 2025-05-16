<?php

class CRM_Civiglific_DAO_GlificGroupMap extends CRM_Core_DAO {

  public $__table = 'civicrm_glific_group_map';
  public $id;
  public $group_id;
  public $collection_id;
  public $last_sync_date;

  public static function getTableName() {
    return 'civicrm_glific_group_map';
  }

  public static function fields() {
    return [
      'id' => [
        'name' => 'id',
        'type' => CRM_Utils_Type::T_INT,
        'title' => 'ID',
        'required' => TRUE,
      ],
      'group_id' => [
        'name' => 'group_id',
        'type' => CRM_Utils_Type::T_INT,
        'title' => 'Group ID',
        'required' => TRUE,
      ],
      'collection_id' => [
        'name' => 'collection_id',
        'type' => CRM_Utils_Type::T_STRING,
        'title' => 'Collection ID',
        'maxlength' => 255,
      ],
      'last_sync_date' => [
        'name' => 'last_sync_date',
        'type' => CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME,
        'title' => 'Last Sync Date',
      ],
    ];
  }
  

  public function __construct() {
    parent::__construct();
    \Civi::log()->debug('GlificGroupMap DAO instantiated');

  }

}
