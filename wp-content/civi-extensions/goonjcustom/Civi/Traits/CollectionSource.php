<?php

namespace Civi\Traits;

use Civi\Api4\OptionValue;
use Civi\Api4\Organization;

/**
 *
 */
trait CollectionSource {
  private static $subtypeId;

  /**
   *
   */
  public static function getSubtypeId() {
    if (!self::$subtypeId) {
      $subtype = OptionValue::get(FALSE)
        ->addWhere('grouping', '=', static::ENTITY_NAME)
        ->addWhere('name', '=', static::ENTITY_SUBTYPE_NAME)
        ->execute()->single();

      self::$subtypeId = (int) $subtype['value'];
    }

    return self::$subtypeId;
  }

  /**
   *
   */
  public static function getEntitySubtypeName($entityID) {
    $getSubtypeName = civicrm_api4('Eck_Collection_Camp', 'get', [
      'select' => [
        'subtype:name',
      ],
      'where' => [
              ['id', '=', $entityID],
      ],
      'checkPermissions' => FALSE,
    ]);

    $entityData = $getSubtypeName[0] ?? [];

    return $entityData['subtype:name'] ?? NULL;
  }

  /**
   *
   */
  public static function getContactSubtypeName($entityID) {
    \Civi::log()->debug(__METHOD__, ['entityId' => $entityId]);

    if (!$entityID) {
      return FALSE;
    }

    $getSubtypeName = Organization::get(FALSE)
      ->addSelect('contact_sub_type')
      ->addWhere('id', '=', $entityID)
      ->execute()->single();

    \Civi::log()->debug(__METHOD__, ['getSubtypeName' => $getSubtypeName]);

    $entityData = $getSubtypeName['contact_sub_type'] ?? [];
    return $entityData[0] ?? NULL;
  }

  /**
   *
   */
  private static function isCurrentSubtype($objectRef) {
    if (empty($objectRef['subtype'])) {
      return FALSE;
    }

    $subtypeId = self::getSubtypeId();
    return (int) $objectRef['subtype'] === $subtypeId;
  }

}
