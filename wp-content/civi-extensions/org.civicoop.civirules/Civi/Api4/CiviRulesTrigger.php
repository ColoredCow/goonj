<?php

namespace Civi\Api4;

/**
 * CiviRulesTrigger entity.
 *
 * Provided by the civirules extension.
 *
 * @searchable secondary
 * @package Civi\Api4
 */
class CiviRulesTrigger extends Generic\DAOEntity {
  use Generic\Traits\ManagedEntity;

  /**
   * @inheritDoc
   */
  public static function permissions(): array {
    return [
      'default' => ['administer CiviRules'],
    ];
  }

}
