<?php
use CRM_Civirules_ExtensionUtil as E;

return [
  'name' => 'CiviRulesRuleLog',
  'table' => 'civirule_rule_log',
  'class' => 'CRM_Civirules_DAO_CiviRulesRuleLog',
  'getInfo' => fn() => [
    'title' => E::ts('Civi Rules Rule Log'),
    'title_plural' => E::ts('Civi Rules Rule Logs'),
    'description' => E::ts('CiviRules Rule Logs'),
    'log' => TRUE,
  ],
  'getIndices' => fn() => [
    'rule_id' => [
      'fields' => [
        'rule_id' => TRUE,
      ],
    ],
    'contact_id' => [
      'fields' => [
        'contact_id' => TRUE,
      ],
    ],
    'rule_contact_id' => [
      'fields' => [
        'rule_id' => TRUE,
        'contact_id' => TRUE,
      ],
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique RuleLog ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'rule_id' => [
      'title' => E::ts('Rule ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'default' => NULL,
      'entity_reference' => [
        'entity' => 'CiviRulesRule',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'contact_id' => [
      'title' => E::ts('Triggered for Contact ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to Contact ID'),
      'default' => NULL,
      'input_attrs' => [
        'label' => E::ts('Triggered For'),
      ],
      'entity_reference' => [
        'entity' => 'Contact',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'entity_table' => [
      'title' => E::ts('Triggered Entity Table'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'default' => NULL,
    ],
    'entity_id' => [
      'title' => E::ts('Triggered Entity ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'default' => NULL,
    ],
    'log_date' => [
      'title' => E::ts('Log Date'),
      'sql_type' => 'datetime',
      'input_type' => 'Select Date',
      'readonly' => TRUE,
      'default' => 'CURRENT_TIMESTAMP',
    ],
  ],
];
