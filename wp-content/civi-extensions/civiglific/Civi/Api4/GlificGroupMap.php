<?php

namespace Civi\Api4;

use Civi\Api4\Generic\DAOEntity;

/**
 * @searchable
 * @entity GlificGroupMap
 */
class GlificGroupMap extends DAOEntity {

  /**
   *
   */
  protected static function getEntityTitle(bool $plural = FALSE): string {
    return $plural
      ? ts('Glific Group Maps')
      : ts('Glific Group Map');
  }

}
