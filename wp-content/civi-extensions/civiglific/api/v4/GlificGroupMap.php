<?php

namespace Civi\Api4;

use Civi\Api4\Generic\DAOEntity;

class GlificGroupMap extends DAOEntity {

  /**
   * @return string
   */
  public static function getEntityName(): string {
   \Civi::log()->debug('tarun2');
    return 'GlificGroupMap';
  }

  /**
   * @return array
   */
  public static function getFields(): array {
   \Civi::log()->debug('tarun3');

    return [
      [
        'name' => 'id',
        'title' => 'ID',
        'description' => 'Unique GlificGroupMap ID',
        'type' => 'integer',
        'required' => TRUE,
        'data_type' => 'Integer',
        'readonly' => TRUE,
      ],
      [
        'name' => 'group_id',
        'title' => 'Group ID',
        'description' => 'CiviCRM Group ID',
        'type' => 'integer',
        'required' => TRUE,
        'data_type' => 'Integer',
        'fk_entity' => 'Group',
      ],
      [
        'name' => 'collection_id',
        'title' => 'Collection ID',
        'description' => 'Glific Collection ID',
        'type' => 'string',
        'data_type' => 'String',
        'size' => 255,
      ],
      [
        'name' => 'last_sync_date',
        'title' => 'Last Sync Date',
        'description' => 'Date of last synchronization',
        'type' => 'timestamp',
        'data_type' => 'Timestamp',
      ],
    ];
  }

  /**
   * @return array
   */
  public static function permissions(): array {
    return [
      'create' => ['access CiviCRM'],
      'update' => ['access CiviCRM'],
      'delete' => ['access CiviCRM'],
      'get' => ['access CiviCRM'],
    ];
  }
}