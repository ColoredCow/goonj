<?php
use CRM_Civirules_ExtensionUtil as E;

return [
  'type' => 'search',
  'title' => E::ts('Rule Actions'),
  'icon' => 'fa-list-alt',
  'permission' => [
    'administer CiviRules',
  ],
];
