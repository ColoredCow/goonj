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
    $subtypeIdd = self::$subtypeId;
    \Civi::log()->info('subtypeId', ['subtypeIdss'=>$subtypeIdd]);
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
  public static function getOrgSubtypeName($entityID) {
    $organization = Organization::get(FALSE)
      ->addSelect('contact_sub_type')
      ->addWhere('id', '=', $entityID)
      ->execute()
      ->first();

    if (!$organization) {
      return FALSE;
    }

    $subType = $organization['contact_sub_type'];

    return $subType;
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
