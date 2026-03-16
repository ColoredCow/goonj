<?php

use CRM_Ses_ExtensionUtil as E;

return [
  'ses_access_key' => [
    'name' => 'ses_access_key',
    'type' => 'String',
    'html_type' => 'text',
    'default' => NULL,
    'add' => '1.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('SES Access Key'),
    'description' => E::ts('Usually a 20-ish character alphanumeric key.'),
    'html_attributes' => [
      'size' => 60,
    ],
    'settings_pages' => [
      'ses' => [
        'weight' => 5,
      ],
    ],
  ],
  'ses_secret_key' => [
    'name' => 'ses_secret_key',
    'type' => 'String',
    'html_type' => 'text',
    'default' => NULL,
    'add' => '1.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('SES Secret Key'),
    'description' => E::ts('Usually a 40-ish character alphanumeric key.'),
    'html_attributes' => [
      'size' => 60,
    ],
    'settings_pages' => [
      'ses' => [
        'weight' => 10,
      ],
    ],
  ],
  'ses_region' => [
    'name' => 'ses_region',
    'type' => 'String',
    'html_type' => 'text',
    'default' => NULL,
    'add' => '1.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('SES Region'),
    'description' => E::ts('Must be the one setup on your account. Ex: ca-central-1, us-east-1, etc.'),
    'html_attributes' => [],
    'settings_pages' => [
      'ses' => [
        'weight' => 15,
      ],
    ],
  ],
];
