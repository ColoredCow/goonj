<?php
use CRM_Civirules_ExtensionUtil as E;

return [
  'name' => 'CiviRulesCondition',
  'table' => 'civirule_condition',
  'class' => 'CRM_Civirules_DAO_CiviRulesCondition',
  'getInfo' => fn() => [
    'title' => E::ts('Civi Rules Condition'),
    'title_plural' => E::ts('Civi Rules Conditions'),
    'description' => E::ts('CiviRules conditions'),
    'log' => TRUE,
    'label_field' => 'label',
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique Condition ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'name' => [
      'title' => E::ts('Name'),
      'sql_type' => 'varchar(80)',
      'input_type' => 'Text',
      'default' => NULL,
    ],
    'label' => [
      'title' => E::ts('Label'),
      'sql_type' => 'varchar(128)',
      'input_type' => 'Text',
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
  ],
];
