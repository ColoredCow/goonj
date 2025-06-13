<?php
use CRM_Civirules_ExtensionUtil as E;

return [
  'name' => 'CiviRulesRuleTag',
  'table' => 'civirule_rule_tag',
  'class' => 'CRM_Civirules_DAO_CiviRulesRuleTag',
  'getInfo' => fn() => [
    'title' => E::ts('Civi Rules Rule Tag'),
    'title_plural' => E::ts('Civi Rules Rule Tags'),
    'description' => E::ts('Tags for CiviRules rule'),
    'log' => TRUE,
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => NULL,
      'required' => TRUE,
      'readonly' => TRUE,
      'description' => E::ts('CiviRules Rule Tags'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'rule_id' => [
      'title' => E::ts('Rule ID'),
      'sql_type' => 'int unsigned',
      'input_type' => NULL,
      'readonly' => TRUE,
      'default' => NULL,
      'entity_reference' => [
        'entity' => 'CiviRulesRule',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'rule_tag_id' => [
      'title' => E::ts('Tag'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Select',
      'default' => NULL,
      'input_attrs' => [
        'label' => E::ts('Tag'),
        'multiple' => TRUE,
      ],
      'pseudoconstant' => [
        'option_group_name' => 'civirule_rule_tag',
      ],
    ],
  ],
];
