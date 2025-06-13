<?php
use CRM_Civirules_ExtensionUtil as E;

return [
  'name' => 'CiviRulesRuleAction',
  'table' => 'civirule_rule_action',
  'class' => 'CRM_Civirules_DAO_CiviRulesRuleAction',
  'getInfo' => fn() => [
    'title' => E::ts('Civi Rules Rule Action'),
    'title_plural' => E::ts('Civi Rules Rule Actions'),
    'description' => E::ts('CiviRules Rule Actions'),
    'log' => TRUE,
  ],
  'getPaths' => fn() => [
    'add' => 'civicrm/civirule/form/rule_action?reset=1&action=add&rule_id=[rule_id]',
    'update' => 'civicrm/civirule/form/rule_action?reset=1&action=update&id=[id]',
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique RuleAction ID'),
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
        'on_delete' => 'CASCADE',
      ],
    ],
    'action_id' => [
      'title' => E::ts('Action ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'default' => NULL,
      'entity_reference' => [
        'entity' => 'CiviRulesAction',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'action_params' => [
      'title' => E::ts('Action Params'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'default' => NULL,
    ],
    'delay' => [
      'title' => E::ts('Delay'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'readonly' => TRUE,
      'default' => NULL,
    ],
    'ignore_condition_with_delay' => [
      'title' => E::ts('Ignore Condition With Delay'),
      'sql_type' => 'boolean',
      'input_type' => 'CheckBox',
      'default' => FALSE,
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
    'weight' => [
      'title' => ts('Order'),
      'sql_type' => 'int',
      'input_type' => 'Number',
      'description' => ts('Ordering of the RuleActions'),
      'default' => 0,
      'required' => TRUE,
    ],
    'created_date' => [
      'title' => ts('Created Date'),
      'sql_type' => 'timestamp',
      'input_type' => NULL,
      'required' => TRUE,
      'description' => ts('RuleAction Created Date'),
      'default' => 'CURRENT_TIMESTAMP',
    ],
    'modified_date' => [
      'title' => ts('Modified Date'),
      'sql_type' => 'timestamp',
      'input_type' => NULL,
      'readonly' => TRUE,
      'description' => ts('RuleAction Modified Date'),
      'default' => 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ],
  ],
];
