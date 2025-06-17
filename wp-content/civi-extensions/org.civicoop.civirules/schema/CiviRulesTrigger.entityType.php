<?php
use CRM_Civirules_ExtensionUtil as E;

return [
  'name' => 'CiviRulesTrigger',
  'table' => 'civirule_trigger',
  'class' => 'CRM_Civirules_DAO_CiviRulesTrigger',
  'getInfo' => fn() => [
    'title' => E::ts('Civi Rules Trigger'),
    'title_plural' => E::ts('Civi Rules Triggers'),
    'description' => E::ts('CiviRules Rule Triggers'),
    'log' => TRUE,
    'label_field' => 'label',
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique Trigger ID'),
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
      'readonly' => TRUE,
      'default' => NULL,
      'input_attrs' => [
        'label' => E::ts('Trigger'),
      ],
    ],
    'object_name' => [
      'title' => E::ts('Object Name'),
      'sql_type' => 'varchar(45)',
      'input_type' => 'Text',
      'default' => NULL,
    ],
    'op' => [
      'title' => E::ts('Op'),
      'sql_type' => 'varchar(45)',
      'input_type' => 'Text',
      'default' => NULL,
    ],
    'cron' => [
      'title' => E::ts('Cron'),
      'sql_type' => 'boolean',
      'input_type' => 'CheckBox',
      'default' => FALSE,
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
