<?php

namespace Civi\Traits;

/**
 * Trait to handle subtype name retrieval by entity ID.
 */
trait SubtypeSource {

  /**
   * Get the subtype name by entity ID.
   *
   * @param int $entityID
   *   The ID of the entity.
   *
   * @return string|null
   *   The subtype name or NULL if not found.
   */
  public static function getEntitySubtypeName($entityID) {
    $getSubtypeName = civicrm_api4('Eck_Collection_Camp', 'get', [
      'select' => [
        'subtype:name',
      ],
      'where' => [
        ['id', '=', $entityID],
      ],
    ]);

    $entityData = $getSubtypeName[0] ?? [];

    return $entityData['subtype:name'] ?? NULL;
  }

}
