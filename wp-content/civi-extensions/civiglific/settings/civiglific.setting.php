<?php

/**
 * @file
 */


return [

  'civiglific_phone' => [
    'name' => 'civiglific_phone',
    'type' => 'String',
    'html_type' => 'text',
    'title' => ts('Glific Phone Number'),
    'description' => ts('Registered Glific WhatsApp phone number'),
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['civiglific' => ['weight' => 10]],
  ],

  'civiglific_password' => [
    'name' => 'civiglific_password',
    'type' => 'String',
    'html_type' => 'password',
    'title' => ts('Glific Password'),
    'description' => ts('Glific API password'),
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['civiglific' => ['weight' => 20]],
  ],

  'civiglific_api_base_url' => [
    'name' => 'civiglific_api_base_url',
    'type' => 'String',
    'html_type' => 'text',
    'title' => ts('Glific API Base URL'),
    'description' => ts('Base URL for Glific API'),
    'default' => 'https://api.goonj.glific.com',
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['civiglific' => ['weight' => 30]],
  ],

  'civiglific_template_id_default' => [
    'name' => 'civiglific_template_id_default',
    'type' => 'Integer',
    'html_type' => 'text',
    'title' => ts('Default Template ID'),
    'is_domain' => 1,
    'settings_pages' => ['civiglific' => ['weight' => 40]],
  ],

  'civiglific_template_id_team5000' => [
    'name' => 'civiglific_template_id_team5000',
    'type' => 'Integer',
    'html_type' => 'text',
    'title' => ts('Team 5000 Template ID'),
    'is_domain' => 1,
    'settings_pages' => ['civiglific' => ['weight' => 50]],
  ],

  'civiglific_persist_pdf_path' => [
    'name' => 'civiglific_persist_pdf_path',
    'type' => 'String',
    'html_type' => 'text',
    'title' => ts('Persistent PDF Path (Private)'),
    'description' => ts('Server path where contribution PDFs are stored persistently (private path)'),
    'default' => '/uploads/civicrm/persist/contribute/contribution/',
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['civiglific' => ['weight' => 60]],
  ],

  'civiglific_saved_pdf_path' => [
    'name' => 'civiglific_saved_pdf_path',
    'type' => 'String',
    'html_type' => 'text',
    'title' => ts('Saved PDF Path (Public)'),
    'description' => ts('Public path where contribution PDFs are accessible'),
    'default' => '/wp-content/uploads/civicrm/persist/contribute/contribution/',
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['civiglific' => ['weight' => 70]],
  ],

];
