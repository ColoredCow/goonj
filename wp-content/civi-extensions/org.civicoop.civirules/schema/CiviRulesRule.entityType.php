<?php
use CRM_Civirules_ExtensionUtil as E;

return [
  'name' => 'CiviRulesRule',
  'table' => 'civirule_rule',
  'class' => 'CRM_Civirules_DAO_CiviRulesRule',
  'getInfo' => fn() => [
    'title' => E::ts('Civi Rules Rule'),
    'title_plural' => E::ts('Civi Rules Rules'),
    'description' => E::ts('CiviRules Rule'),
    'log' => TRUE,
    'label_field' => 'label',
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique Rule ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'name' => [
      'title' => E::ts('Name'),
      'sql_type' => 'varchar(80)',
      'input_type' => 'Text',
      'readonly' => TRUE,
    ],
    'label' => [
      'title' => E::ts('Label'),
      'sql_type' => 'varchar(128)',
      'input_type' => 'Text',
      'required' => TRUE,
      'input_attrs' => [
        'maxlength' => 128,
      ],
    ],
    'trigger_id' => [
      'title' => E::ts('Trigger ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'readonly' => TRUE,
      'default' => NULL,
      'input_attrs' => [
        'label' => E::ts('Trigger'),
      ],
      'pseudoconstant' => [
        'table' => 'civirule_trigger',
        'key_column' => 'id',
        'label_column' => 'label',
      ],
      'entity_reference' => [
        'entity' => 'CiviRulesTrigger',
        'key' => 'id',
        'on_delete' => 'NO ACTION',
      ],
    ],
    'trigger_params' => [
      'title' => E::ts('Trigger Params'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'readonly' => TRUE,
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
    'description' => [
      'title' => E::ts('Description'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'default' => NULL,
    ],
    'help_text' => [
      'title' => E::ts('Help Text'),
      'sql_type' => 'text',
      'input_type' => 'RichTextEditor',
      'default' => NULL,
      'input_attrs' => [
        'rows' => 4,
        'cols' => 60,
      ],
    ],
    'created_date' => [
      'title' => E::ts('Created Date'),
      'sql_type' => 'timestamp',
      'input_type' => NULL,
      'required' => TRUE,
      'description' => E::ts('When was this item created'),
      'default' => 'CURRENT_TIMESTAMP',
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
      'entity_reference' => [
        'entity' => 'Contact',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'modified_date' => [
      'title' => E::ts('Modified Date'),
      'sql_type' => 'timestamp',
      'input_type' => 'Select Date',
      'readonly' => TRUE,
      'description' => E::ts('When was this item modified'),
      'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
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
      'entity_reference' => [
        'entity' => 'Contact',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'is_debug' => [
      'title' => E::ts('Is Debug'),
      'sql_type' => 'boolean',
      'input_type' => 'CheckBox',
      'default' => FALSE,
    ],
  ],
];
