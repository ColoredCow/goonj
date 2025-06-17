<?php
use CRM_Civirules_ExtensionUtil as E;

return [
  [
    'name' => 'SavedSearch_CiviRules_Rule_Trigger_History',
    'entity' => 'SavedSearch',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'CiviRules_Rule_Trigger_History',
        'label' => E::ts('CiviRules Rule Trigger History'),
        'api_entity' => 'CiviRulesRuleLog',
        'api_params' => [
          'version' => 4,
          'select' => [
            'id',
            'log_date',
            'contact_id.display_name',
          ],
          'orderBy' => [],
          'where' => [],
          'groupBy' => [],
          'join' => [],
          'having' => [],
        ],
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'SavedSearch_CiviRules_Rule_Trigger_History_SearchDisplay_CiviRules_Rule_Trigger_History',
    'entity' => 'SearchDisplay',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'CiviRules_Rule_Trigger_History',
        'label' => E::ts('CiviRules Rule Trigger History'),
        'saved_search_id.name' => 'CiviRules_Rule_Trigger_History',
        'type' => 'table',
        'settings' => [
          'description' => E::ts(NULL),
          'sort' => [
            ['log_date', 'DESC'],
          ],
          'limit' => 10,
          'pager' => [
            'hide_single' => TRUE,
            'show_count' => TRUE,
          ],
          'placeholder' => 5,
          'columns' => [
            [
              'type' => 'field',
              'key' => 'log_date',
              'dataType' => 'Timestamp',
              'label' => E::ts('Date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'contact_id.display_name',
              'dataType' => 'String',
              'label' => E::ts('Triggered For'),
              'sortable' => TRUE,
              'link' => [
                'path' => '',
                'entity' => 'Contact',
                'action' => 'view',
                'join' => 'contact_id',
                'target' => '_blank',
                'task' => '',
              ],
              'title' => E::ts('View Contact'),
            ],
          ],
          'actions' => FALSE,
          'classes' => ['table', 'table-striped'],
        ],
      ],
      'match' => [
        'saved_search_id',
        'name',
      ],
    ],
  ],
];
