<?php
use CRM_Civirules_ExtensionUtil as E;

return [
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
          'administer CiviRules',
        ],
        'permission_operator' => 'AND',
        'parent_id.name' => 'CiviRules',
        'weight' => 4,
      ],
      'match' => ['name', 'domain_id'],
    ],
  ],
];
