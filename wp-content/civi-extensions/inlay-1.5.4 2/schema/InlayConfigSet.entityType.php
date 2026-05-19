<?php
use CRM_Inlay_ExtensionUtil as E;

return [
  'name' => 'InlayConfigSet',
  'table' => 'civicrm_inlay_config_set',
  'class' => 'CRM_Inlay_DAO_InlayConfigSet',
  'getInfo' => fn() => [
    'title' => E::ts('Inlay Config Set'),
    'title_plural' => E::ts('Inlay Config Sets'),
    'description' => E::ts('Holds sets of config defined against arbitrary schemas provided by inlay type extensions.'),
    'log' => FALSE,
    'label_field' => 'label',
  ],
  'getIndices' => fn() => [
    'index_schema_setname' => [
      'fields' => [
        'schema_name' => TRUE,
        'set_name' => TRUE,
      ],
      'unique' => TRUE,
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique InlayConfigSet ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'schema_name' => [
      'title' => E::ts('Schema Name'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('Machine name of schema that owns this, typically prefixed with the inlay extension shortname, e.g. inlaypay_stylesets'),
    ],
    'set_name' => [
      'title' => E::ts('Set Name'),
      'sql_type' => 'varchar(128)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('Machine name of this config item, where needed, must be unique within schema.'),
    ],
    'label' => [
      'title' => E::ts('Label'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'description' => E::ts('Human friendly admin name for the set'),
    ],
    'config' => [
      'title' => E::ts('Config'),
      'sql_type' => 'longtext',
      'input_type' => 'TextArea',
      'required' => TRUE,
      'description' => E::ts('JSON blob of config.'),
    ],
  ],
];
