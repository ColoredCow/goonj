<?php

namespace Civi\Api4;

/**
 * CiviRulesRuleAction entity.
 *
 * Provided by the civirules extension.
 *
 * @searchable secondary
 * @orderBy weight
 * @package Civi\Api4
 */
class CiviRulesRuleAction extends Generic\DAOEntity {
  use Generic\Traits\SortableEntity;
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
