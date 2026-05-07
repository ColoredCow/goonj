<?php

namespace Civi\Api4;

/**
 * CiviRulesAction entity.
 *
 * Provided by the civirules extension.
 *
 * @searchable secondary
 * @package Civi\Api4
 */
class CiviRulesAction extends Generic\DAOEntity {
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
