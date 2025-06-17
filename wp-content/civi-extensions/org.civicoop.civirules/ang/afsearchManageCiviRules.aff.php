<?php
use CRM_Civirules_ExtensionUtil as E;

return [
  'type' => 'search',
  'title' => E::ts('Manage CiviRules'),
  'icon' => 'fa-list-alt',
  'server_route' => 'civicrm/civirules/form/rulesview',
  'permission' => [
    'administer CiviRules',
    'administer CiviCRM',
  ],
  'permission_operator' => 'OR',
];
