<?php

namespace Civi;

use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class ContactSummaryViewService extends AutoSubscriber {

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_tabset' => ['hideTabsForAccountsTeam', -1000],
    ];
  }

  /**
   *
   */
  public function hideTabsForAccountsTeam($tabsetName, &$tabs, $context) {
    $isAdmin = \CRM_Core_Permission::check('admin');

    if ($tabsetName !== 'civicrm/contact/view' || $isAdmin) {
      return;
    }

    $isAccount = \CRM_Core_Permission::check('account_team');

    if ($isAccount) {
      $newTabs = $this->removeTabsById($tabs, ['participant', 'activity', 'group', 'log', 'rel']);
    }
    else {
      $newTabs = $tabs;
    }

    $tabs = $newTabs;
  }

  /**
   *
   */
  private function removeTabsById($tabs, $idsToRemove) {
    if (!is_array($idsToRemove)) {
      $idsToRemove = [$idsToRemove];
    }

    return array_filter($tabs, fn($item) => !in_array($item['id'], $idsToRemove));
  }

}
