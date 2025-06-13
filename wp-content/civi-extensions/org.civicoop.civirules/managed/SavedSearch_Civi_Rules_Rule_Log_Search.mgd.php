<?php
use CRM_Civirules_ExtensionUtil as E;

return [
  [
    'name' => 'SavedSearch_Civi_Rules_Rule_Log_Search',
    'entity' => 'SavedSearch',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Civi_Rules_Rule_Log_Search',
        'label' => E::ts('Civi Rules Rule Log Search'),
        'api_entity' => 'CiviRulesRuleLog',
        'api_params' => [
          'version' => 4,
          'select' => [
            'id',
            'CiviRulesRuleLog_CiviRulesRule_rule_id_01.label',
            'log_date',
            'entity_id',
            'entity_table',
            'CiviRulesRuleLog_CiviRulesRule_rule_id_01.id',
            'contact_id.display_name',
          ],
          'orderBy' => [],
          'where' => [],
          'groupBy' => [],
          'join' => [
            [
              'CiviRulesRule AS CiviRulesRuleLog_CiviRulesRule_rule_id_01',
              'INNER',
              [
                'rule_id',
                '=',
                'CiviRulesRuleLog_CiviRulesRule_rule_id_01.id',
              ],
            ],
          ],
          'having' => [],
        ],
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'SavedSearch_Civi_Rules_Rule_Log_Search_SearchDisplay_Civi_Rules_Rule_Log_Search',
    'entity' => 'SearchDisplay',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Civi_Rules_Rule_Log_Search',
        'label' => E::ts('Civi Rules Rule Log Search'),
        'saved_search_id.name' => 'Civi_Rules_Rule_Log_Search',
        'type' => 'table',
        'settings' => [
          'description' => NULL,
          'sort' => [
            ['log_date', 'DESC'],
          ],
          'limit' => 50,
          'pager' => [
            'show_count' => TRUE,
            'hide_single' => TRUE,
          ],
          'placeholder' => 5,
          'columns' => [
            [
              'type' => 'field',
              'key' => 'CiviRulesRuleLog_CiviRulesRule_rule_id_01.label',
              'dataType' => 'String',
              'label' => E::ts('Rule'),
              'sortable' => TRUE,
              'link' => [
                'path' => 'civicrm/civirule/form/rule?reset=1&action=update&id=[CiviRulesRuleLog_CiviRulesRule_rule_id_01.id]',
                'entity' => '',
                'action' => '',
                'join' => '',
                'target' => '_blank',
                'task' => '',
              ],
              'rewrite' => '[CiviRulesRuleLog_CiviRulesRule_rule_id_01.id]: [CiviRulesRuleLog_CiviRulesRule_rule_id_01.label]',
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
            [
              'type' => 'field',
              'key' => 'log_date',
              'dataType' => 'Timestamp',
              'label' => E::ts('Log Date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'entity_id',
              'dataType' => 'Integer',
              'label' => E::ts('Triggered Entity ID'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'entity_table',
              'dataType' => 'String',
              'label' => E::ts('Triggered Entity Table'),
              'sortable' => TRUE,
            ],
          ],
          'actions' => [
            'download',
            'triggerCivirule',
          ],
          'classes' => ['table', 'table-striped'],
          'actions_display_mode' => 'buttons',
          'headerCount' => TRUE,
        ],
      ],
      'match' => [
        'saved_search_id',
        'name',
      ],
    ],
  ],
];
