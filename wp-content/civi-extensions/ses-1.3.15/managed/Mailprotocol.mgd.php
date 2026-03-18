<?php

use CRM_Ses_ExtensionUtil as E;

return [
  [
    'name' => 'OptionValue_MailProtocol_SES',
    'entity' => 'OptionValue',
    'cleanup' => 'always',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'mail_protocol',
        'label' => E::ts('SES'),
        'name' => 'SES',
        'is_default' => NULL,
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
];