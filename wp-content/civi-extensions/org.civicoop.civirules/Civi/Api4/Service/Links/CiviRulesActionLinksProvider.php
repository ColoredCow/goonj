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
use Civi\Api4\CiviRulesAction;
use Civi\Api4\OptionValue;

/**
 * @service
 * @internal
 */
class CiviRulesActionLinksProvider extends \Civi\Core\Service\AutoSubscriber {
  use LinksProviderTrait;

  public static function getSubscribedEvents(): array {
    return [
      'civi.api.respond' => 'alterCiviRulesActionLinksResult',
    ];
  }

  public static function alterCiviRulesActionLinksResult(RespondEvent $e): void {
    $request = $e->getApiRequest();
    if ($request['version'] == 4 && $request->getEntityName() === 'CiviRulesRuleAction' && is_a($request, '\Civi\Api4\Action\GetLinks')) {
      $links = (array) $e->getResponse();
      // $addLinkIndex = self::getActionIndex($links, 'add');
      $editLinkIndex = self::getActionIndex($links, 'update');

      // Expand the "update" link to multiple CiviRule Actions if it exists (otherwise the WHERE clause excluded it and we should too)
      $where = $request->getWhere();
      $isEditLink = FALSE;
      foreach ($where as $whereValue) {
        if ($whereValue[0] === 'ui_action' && $whereValue[1] === '=' && $whereValue[2] === 'update') {
          $isEditLink = TRUE;
        }
      }
      if ($isEditLink && isset($editLinkIndex)) {
        // Expanding the "add" link requires a value for target_contact.
        // This might come back from SearchKit in a couple different ways,
        // either an implicit join on 'target_contact_id' or as an explicit join.
        $ruleID = $request->getValue('rule_id');
        $ruleActionID = $request->getValue('id');
        $actionID = $request->getValue('action_id');
        if ($ruleID) {
          // Ensure links contain exactly the return values requested in the SELECT clause
          $editLink = self::getCiviRulesActionEditLink($ruleActionID, $actionID, $request->getCheckPermissions());
          $links[$editLinkIndex] = array_merge($links[$editLinkIndex], $editLink);
        }
      }
      $e->getResponse()->exchangeArray(array_values($links));
    }
  }

  private static function getCiviRulesActionEditLink($ruleActionID, $actionID, $checkPermissions): array {
    $action = CiviRulesAction::get($checkPermissions)
      ->addWhere('id', '=', $actionID)
      ->execute()
      ->first();

    if (!empty($action['class_name'])) {
      $actionClass = new $action['class_name']();
      $editLink['path'] = $actionClass->getExtraDataInputUrl($ruleActionID);
      $editLink['icon'] = $action['icon'] ?? 'fa-plus-square-o';
      $editLink['text'] = $action['label'];
      return $editLink;
    }
    return [];
  }

}
