<?php

namespace Civi\Traits;

use Civi\Api4\OptionValue;

/**
 *
 */
trait CollectionSource {

  /**
   *
   */
  public static function getSubtypeId() {
    if (!self::$subtypeId) {
      $subtype = OptionValue::get(FALSE)
        ->addWhere('grouping', '=', self::ENTITY_NAME)
        ->addWhere('name', '=', self::ENTITY_SUBTYPE_NAME)
        ->execute()->single();

      self::$subtypeId = (int) $subtype['value'];
    }

    return self::$subtypeId;
  }

}
