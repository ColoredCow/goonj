<?php
use CRM_Civirules_ExtensionUtil as E;

return [
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
          'administer CiviRules',
          'administer CiviCRM',
        ],
        'permission_operator' => 'OR',
        'parent_id.name' => 'CiviRules',
        'weight' => 2,
      ],
      'match' => ['name', 'domain_id'],
    ],
  ],
];
