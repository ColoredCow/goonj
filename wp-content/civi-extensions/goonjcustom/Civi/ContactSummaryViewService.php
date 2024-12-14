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
    if ($tabsetName !== 'civicrm/contact/view') {
      return;
    }

    // Add check for accounts team.
    $newTabs = $this->removeTabsById($tabs, ['participant', 'activity', 'group', 'log', 'rel']);

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
