<?php
use CRM_Cityselector_ExtensionUtil as E;

return [
  'cityselector_parent' => [
    'name' => 'cityselector_parent',
    'type' => 'String',
    'html_type' => 'radio',
    'default' => '',
    'add' => '1.0',
    'title' => E::ts('City parent field'),
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => E::ts('Depending on the use, city selector could be chained to State/Province or County selector. This will create the `civicrm_city` table in the database, and it can be executed only one time. Any change on this configuration, needs to be performed by the SysAdmin'),
    'help_text' => NULL,
    'options' => [
      'county' => E::ts('County'),
      'state_province' => E::ts('State/Province'),
    ],
    'settings_pages' => [
      'cityselector' => ['weight' => 10]
    ],
    'on_change' => [
      'CRM_Cityselector_BAO_Location::onChangeSettingParent',
    ],
    'validate_callback' => 'CRM_Cityselector_BAO_Location::onValidateSettingParent',
  ],
];
