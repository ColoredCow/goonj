<?php

namespace Civi\Api4;

/**
 * CiviRulesRule entity.
 *
 * Provided by the civirules extension.
 *
 * @searchable secondary
 * @package Civi\Api4
 */
class CiviRulesRule extends Generic\DAOEntity {
    /**
     * @dependency CiviRulesRuleCondition:rule_id
     * @dependency CiviRulesRuleAction:rule_id
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
