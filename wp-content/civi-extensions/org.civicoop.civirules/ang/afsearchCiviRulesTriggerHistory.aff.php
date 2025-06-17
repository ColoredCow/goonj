<?php
use CRM_Civirules_ExtensionUtil as E;

return [
  'type' => 'search',
  'title' => E::ts('CiviRules Trigger History'),
  'icon' => 'fa-clock-rotate-left',
  'server_route' => 'civicrm/civirules/search/logs',
  'permission' => [
    'administer CiviRules',
  ],
];
