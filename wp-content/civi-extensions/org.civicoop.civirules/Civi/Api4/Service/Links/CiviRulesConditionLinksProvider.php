<?php

/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

namespace Civi\Api4\Service\Links;

use Civi\API\Event\RespondEvent;
use Civi\Api4\CiviRulesCondition;

/**
 * @service
 * @internal
 */
class CiviRulesConditionLinksProvider extends \Civi\Core\Service\AutoSubscriber {
  use LinksProviderTrait;

  public static function getSubscribedEvents(): array {
    return [
      'civi.api.respond' => 'alterCiviRulesConditionLinksResult',
    ];
  }

  public static function alterCiviRulesConditionLinksResult(RespondEvent $e): void {
    $request = $e->getApiRequest();
    if ($request['version'] == 4 && $request->getEntityName() === 'CiviRulesRuleCondition' && is_a($request, '\Civi\Api4\Action\GetLinks')) {
      $links = (array) $e->getResponse();
      // $addLinkIndex = self::getActionIndex($links, 'add');
      $editLinkIndex = self::getActionIndex($links, 'update');

      // Expand the "update" link to multiple CiviRule Conditions if it exists (otherwise the WHERE clause excluded it and we should too)
      $where = $request->getWhere();
      $isEditLink = FALSE;
      foreach ($where as $whereValue) {
        if ($whereValue[0] === 'ui_action' && $whereValue[1] === '=' && $whereValue[2] === 'update') {
          $isEditLink = TRUE;
        }
      }
      if ($isEditLink && isset($editLinkIndex)) {
        $ruleID = $request->getValue('rule_id');
        $ruleConditionID = $request->getValue('id');
        $conditionID = $request->getValue('condition_id');
        if ($ruleID) {
          // Ensure links contain exactly the return values requested in the SELECT clause
          $editLink = self::getCiviRulesConditionEditLink($ruleConditionID, $conditionID, $request->getCheckPermissions());
          $links[$editLinkIndex] = array_merge($links[$editLinkIndex], $editLink);
        }
      }
      $e->getResponse()->exchangeArray(array_values($links));
    }
  }

  private static function getCiviRulesConditionEditLink($ruleConditionID, $conditionID, $checkPermissions): array {
    $condition = CiviRulesCondition::get($checkPermissions)
      ->addWhere('id', '=', $conditionID)
      ->execute()
      ->first();

    if (!empty($condition['class_name'])) {
      $conditionClass = new $condition['class_name']();
      $editLink['path'] = $conditionClass->getExtraDataInputUrl($ruleConditionID);
      $editLink['icon'] = $condition['icon'] ?? 'fa-plus-square-o';
      $editLink['text'] = $condition['label'];
      return $editLink;
    }
    return [];
  }

}
