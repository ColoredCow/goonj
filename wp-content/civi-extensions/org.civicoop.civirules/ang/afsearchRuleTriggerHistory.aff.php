<?php
use CRM_Civirules_ExtensionUtil as E;

return [
  'type' => 'search',
  'title' => E::ts('Rule Trigger History'),
  'icon' => 'fa-list-alt',
  'permission' => [
    'administer CiviRules',
  ],
];
