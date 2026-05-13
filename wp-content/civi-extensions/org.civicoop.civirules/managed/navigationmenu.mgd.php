<?php

use CRM_CiviRules_ExtensionUtil as E;

return [
  [
    'name' => 'CiviRules',
    'entity' => 'Navigation',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('CiviRules'),
        'name' => 'CiviRules',
        'url' => NULL,
        'permission' => [
          'administer civirules',
          'administer CiviCRM'
        ],
        'permission_operator' => 'OR',
        'parent_id.name' => 'Administer',
        'is_active' => TRUE,
        'has_separator' => 0,
        'weight' => 90,
      ],
      'match' => ['name', 'domain_id'],
    ],
  ],
  [
    'name' => 'Navigation_afsearchManageCiviRules',
    'entity' => 'Navigation',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('Manage Rules'),
        'name' => 'afsearchManageCiviRules',
        'url' => 'civicrm/civirules/form/rulesview',
        'icon' => 'crm-i fa-list-alt',
        'permission' => [
          'administer civirules',
          'administer CiviCRM'
        ],
        'permission_operator' => 'OR',
        'parent_id.name' => 'CiviRules',
        'weight' => 10,
      ],
      'match' => ['name', 'domain_id'],
    ],
  ],
  [
    'name' => 'Navigation_afsearchRulesTriggerHistory',
    'entity' => 'Navigation',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('History of Rules Triggered'),
        'name' => 'afsearchRulesTriggerHistory',
        'url' => 'civicrm/civirules/search/logs',
        'icon' => 'crm-i fa-list-alt',
        'permission' => [
          'administer civirules',
          'administer CiviCRM'
        ],
        'permission_operator' => 'OR',
        'parent_id.name' => 'CiviRules',
        'weight' => 20,
      ],
      'match' => ['name', 'domain_id'],
    ],
  ],
  [
    'name' => 'Navigation_afsearchCiviRulesTags',
    'entity' => 'Navigation',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('Manage Tags'),
        'name' => 'afsearchCiviRulesTags',
        'url' => 'civicrm/admin/civirules/tags',
        'icon' => 'crm-i fa-tags',
        'permission' => [
          'administer civirules',
          'administer CiviCRM'
        ],
        'permission_operator' => 'OR',
        'parent_id.name' => 'CiviRules',
        'weight' => 30,
      ],
      'match' => ['name', 'domain_id'],
    ],
  ],
];
