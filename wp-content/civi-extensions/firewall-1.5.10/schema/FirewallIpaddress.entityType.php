<?php
use CRM_Firewall_ExtensionUtil as E;

return [
  'name' => 'FirewallIpaddress',
  'table' => 'civicrm_firewall_ipaddress',
  'class' => 'CRM_Firewall_DAO_FirewallIpaddress',
  'getInfo' => fn() => [
    'title' => E::ts('Firewall Ipaddress'),
    'title_plural' => E::ts('Firewall Ipaddresses'),
    'description' => E::ts('IP addresses logged by firewall'),
    'log' => FALSE,
  ],
  'getIndices' => fn() => [
    'index_ip_address' => [
      'fields' => [
        'ip_address' => TRUE,
      ],
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique FirewallIpaddress ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'ip_address' => [
      'title' => E::ts('IP Address'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('IP address used'),
    ],
    'access_date' => [
      'title' => E::ts('Access Date'),
      'sql_type' => 'timestamp',
      'input_type' => NULL,
      'required' => TRUE,
      'description' => E::ts('When the IP address accessed'),
      'default' => 'CURRENT_TIMESTAMP',
    ],
    'event_type' => [
      'title' => E::ts('Event Type'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('The type of event that triggered this log'),
    ],
    'source' => [
      'title' => E::ts('Source'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'description' => E::ts('Origin of this access request'),
    ],
  ],
];
