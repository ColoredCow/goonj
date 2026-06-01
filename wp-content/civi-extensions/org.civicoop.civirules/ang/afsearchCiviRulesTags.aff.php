<?php
use CRM_Civirules_ExtensionUtil as E;

return [
  'type' => 'search',
  'title' => E::ts('Manage Tags'),
  'icon' => 'fa-tags',
  'server_route' => 'civicrm/admin/civirules/tags',
  'permission' => [
    'administer CiviCRM',
    'administer CiviRules',
  ],
  'permission_operator' => 'OR',
];
