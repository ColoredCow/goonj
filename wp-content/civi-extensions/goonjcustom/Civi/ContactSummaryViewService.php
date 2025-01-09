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

    $permissionsToHideTabs = [
      'account_team' => ['participant', 'activity', 'group', 'log', 'rel', 'tag'],
      'mmt' => ['participant', 'activity', 'group', 'log', 'rel', 'contribute'],
    ];

    $newTabs = $tabs;
    foreach ($permissionsToHideTabs as $permission => $tabsToHide) {
      // If the user has the specified permission, remove the corresponding tabs.
      if (\CRM_Core_Permission::check($permission)) {
        $newTabs = $this->removeTabsById($newTabs, $tabsToHide);
      }
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
