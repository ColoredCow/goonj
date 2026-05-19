<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
return [
  [
    'name' => 'Cron:Inlay.Createbundle',
    'entity' => 'Job',
    'params' => [
      'version' => 3,
      'name' => 'Update Inlays',
      'description' => 'Call Inlay.Createbundle API to update the static Javascript files',
      'run_frequency' => 'Always',
      'api_entity' => 'Inlay',
      'api_action' => 'Createbundle',
      'parameters' => '',
    ],
  ],
];
