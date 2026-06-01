<?php

namespace Civi\Api4;

/**
 * CiviRulesRuleLog entity.
 *
 * Provided by the civirules extension.
 *
 * @searchable secondary
 * @package Civi\Api4
 */
class CiviRulesRuleLog extends Generic\DAOEntity {

  /**
   * @inheritDoc
   */
  public static function permissions(): array {
    return [
      'default' => ['administer CiviRules'],
    ];
  }

}
