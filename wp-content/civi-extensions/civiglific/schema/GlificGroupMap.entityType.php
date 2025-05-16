<?php
use CRM_Civiglific_ExtensionUtil as E;

return [
  'name'      => 'GlificGroupMap',
  'table'     => 'civicrm_glific_group_map',
  'class'     => 'CRM_Civiglific_DAO_GlificGroupMap',
  'fields'    => [
    'id' => [
      'title'       => E::ts('ID'),
      'sql_type'    => 'int unsigned',
      'required'    => TRUE,
      'description' => E::ts('Unique ID of this record.'),
    ],
    'group_id' => [
      'title'       => E::ts('Group'),
      'sql_type'    => 'int unsigned',
      'required'    => TRUE,
      'FKClassName' => 'CRM_Core_DAO_Group',
      'description' => E::ts('Foreign key to the civicrm_group table.'),
    ],
    'collection_id' => [
      'title'       => E::ts('Glific Collection ID'),
      'sql_type'    => 'varchar(255)',
      'maxlength'   => 255,
      'description' => E::ts('Identifier of the Glific collection.'),
    ],
    'last_sync_date' => [
      'title'       => E::ts('Last Sync Date'),
      'sql_type'    => 'datetime',
      'description' => E::ts('Timestamp of the last synchronization.'),
    ],
  ],
  'primaryKey' => ['id'],
];
