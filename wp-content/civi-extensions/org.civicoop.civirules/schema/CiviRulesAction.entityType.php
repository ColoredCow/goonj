<?php
use CRM_Civirules_ExtensionUtil as E;

return [
  'name' => 'CiviRulesAction',
  'table' => 'civirule_action',
  'class' => 'CRM_Civirules_DAO_CiviRulesAction',
  'getInfo' => fn() => [
    'title' => E::ts('Civi Rules Action'),
    'title_plural' => E::ts('Civi Rules Actions'),
    'description' => E::ts('CiviRules Actions'),
    'log' => TRUE,
    'label_field' => 'label',
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique Action ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'name' => [
      'title' => E::ts('Name'),
      'sql_type' => 'varchar(80)',
      'input_type' => 'Text',
      'required' => TRUE,
    ],
    'label' => [
      'title' => E::ts('Label'),
      'sql_type' => 'varchar(128)',
      'input_type' => 'Select',
      'default' => NULL,
    ],
    'class_name' => [
      'title' => E::ts('Class Name'),
      'sql_type' => 'varchar(128)',
      'input_type' => 'Text',
      'default' => NULL,
    ],
    'is_active' => [
      'title' => E::ts('Enabled'),
      'sql_type' => 'boolean',
      'input_type' => 'CheckBox',
      'required' => TRUE,
      'default' => TRUE,
      'input_attrs' => [
        'label' => E::ts('Enabled'),
      ],
    ],
    'created_date' => [
      'title' => E::ts('Created Date'),
      'sql_type' => 'date',
      'input_type' => 'Select Date',
      'default' => NULL,
    ],
    'created_user_id' => [
      'title' => E::ts('Created User ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'readonly' => TRUE,
      'description' => E::ts('FK to Contact ID'),
      'default' => NULL,
      'input_attrs' => [
        'label' => E::ts('Created By'),
      ],
    ],
    'modified_date' => [
      'title' => E::ts('Modified Date'),
      'sql_type' => 'date',
      'input_type' => 'Select Date',
      'default' => NULL,
    ],
    'modified_user_id' => [
      'title' => E::ts('Modified User ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'readonly' => TRUE,
      'description' => E::ts('FK to Contact ID'),
      'default' => NULL,
      'input_attrs' => [
        'label' => E::ts('Modified By'),
      ],
    ],
  ],
];
