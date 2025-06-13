<?php
use CRM_Civirules_ExtensionUtil as E;

return [
  'name' => 'CiviRulesRuleCondition',
  'table' => 'civirule_rule_condition',
  'class' => 'CRM_Civirules_DAO_CiviRulesRuleCondition',
  'getInfo' => fn() => [
    'title' => E::ts('Civi Rules Rule Condition'),
    'title_plural' => E::ts('Civi Rules Rule Conditions'),
    'description' => E::ts('CiviRules Rule Conditions'),
    'log' => TRUE,
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique RuleCondition ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'rule_id' => [
      'title' => E::ts('Rule ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'entity_reference' => [
        'entity' => 'CiviRulesRule',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'condition_link' => [
      'title' => E::ts('Condition Link'),
      'sql_type' => 'varchar(3)',
      'input_type' => 'Text',
      'default' => NULL,
    ],
    'condition_id' => [
      'title' => E::ts('Condition ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'entity_reference' => [
        'entity' => 'CiviRulesCondition',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'condition_params' => [
      'title' => E::ts('Condition Params'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
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
