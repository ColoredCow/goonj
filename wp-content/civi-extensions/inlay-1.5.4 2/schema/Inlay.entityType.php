<?php
use CRM_Inlay_ExtensionUtil as E;

return [
  'name' => 'Inlay',
  'table' => 'civicrm_inlay',
  'class' => 'CRM_Inlay_DAO_Inlay',
  'getInfo' => fn() => [
    'title' => E::ts('Inlay'),
    'title_plural' => E::ts('Inlays'),
    'description' => E::ts('Instances of different Inlay Types'),
    'log' => FALSE,
  ],
  'getIndices' => fn() => [
    'public_id' => [
      'fields' => [
        'public_id' => TRUE,
      ],
      'unique' => TRUE,
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique Inlay ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'public_id' => [
      'title' => E::ts('Public ID'),
      'sql_type' => 'char(12)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('Public Inlay ID used in script tags.'),
    ],
    'name' => [
      'title' => E::ts('Name'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('Administrative name'),
    ],
    'class' => [
      'title' => E::ts('Class'),
      'sql_type' => 'varchar(140)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('Class name that implements this Inlay Type'),
    ],
    'config' => [
      'title' => E::ts('Config'),
      'sql_type' => 'longtext',
      'input_type' => 'TextArea',
      'required' => TRUE,
      'description' => E::ts('JSON blob of config.'),
    ],
    'status' => [
      'title' => E::ts('Status'),
      'sql_type' => 'varchar(20)',
      'input_type' => 'Select',
      'required' => TRUE,
      'description' => E::ts('on, off or broken'),
      'default' => 'on',
      'pseudoconstant' => [
        'option_group_name' => 'inlay_status',
      ],
    ],
  ],
];
