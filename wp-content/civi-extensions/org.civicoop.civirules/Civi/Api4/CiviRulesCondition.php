<?php

namespace Civi\Api4;

/**
 * CiviRulesCondition entity.
 *
 * Provided by the civirules extension.
 *
 * @searchable secondary
 * @package Civi\Api4
 */
class CiviRulesCondition extends Generic\DAOEntity {
    /**
     * @dependency CiviRulesConditionParameter:condition_id
     */
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
