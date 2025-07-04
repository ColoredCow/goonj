<?php

/**
 * The record will be automatically inserted, updated, or deleted from the
 * database as appropriate. For more details, see "hook_civicrm_managed" at:
 * https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed/
 */
return [
  0 => [
    'name' => 'FirewallCleanup',
    'entity' => 'Job',
    'params' =>
      [
        'version' => 3,
        'name' => 'Firewall: Cleanup',
        'description' => 'Cleanup firewall table data',
        'run_frequency' => 'Daily',
        'api_entity' => 'Job',
        'api_action' => 'firewall_cleanup',
        'parameters' => 'delete_old_ipaddress=-1 month',
      ],
  ],
];
