<?php

use CRM_Ses_ExtensionUtil as E;

return [
  [
    'name' => 'Ses_Settings',
    'entity' => 'Navigation',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('Amazon SES'),
        'name' => 'Ses_Settings',
        'url' => 'civicrm/admin/setting/ses',
        'permission' => 'administer ses',
        'permission_operator' => 'OR',
        'parent_id.name' => 'CiviMail',
        'is_active' => TRUE,
        'has_separator' => 0,
        'weight' => 90,
      ],
      'match' => ['name'],
    ],
  ],
];
