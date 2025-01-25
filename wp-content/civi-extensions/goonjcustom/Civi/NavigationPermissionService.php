<?php

namespace Civi;

use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class NavigationPermissionService extends AutoSubscriber {

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_navigationMenu' => ['hideNavForRoles'],
      '&hook_civicrm_pageRun' => 'hideButtonsForMMT',
    ];
  }

  /**
   *
   */
  public function hideButtonsForMMT(&$page) {
    if ($page->getVar('_name') === 'CRM_Contact_Page_View_Summary') {
      if (\CRM_Core_Permission::check('mmt') && !\CRM_Core_Permission::check('admin')) {
        \CRM_Core_Resources::singleton()->addScript("
              document.querySelectorAll('.crm-actions-ribbon').forEach(el => el.style.display = 'none');
          ");
      }
    }
  }

  /**
   *
   */
  public function hideNavForRoles(&$params) {
    $isAdmin = \CRM_Core_Permission::check('admin');
    if ($isAdmin) {
      return;
    }

    $roleMenuMapping = [
      'account_team' => [
        'hide_menus' => [
          'Contacts',
          'Events',
          'Offices',
          'Dropping Center',
          'Institution Collection Camp',
          'Institute',
          'Collection Camps',
          'Goonj Activities',
          'Institution Dropping Center',
          'Institution Goonj Activities',
          'Induction Tab',
          'Volunteers',
          'Urban Visit',
          'Individuals',
          'Contributions',
          'Campaigns',
        ],
      ],
      'mmt' => [
        'hide_menus' => [
          'Contacts',
          'Events',
          'Dropping Center',
          'Induction Tab',
          'Institution Collection Camp',
          'Collection Camps',
          'Goonj Activities',
          'Institute',
          'Institution Dropping Center',
          'Institution Goonj Activities',
          'Induction Tab',
          'Volunteers',
          'Individuals',
          'Offices',
          'Urban Visit'
        ],
      ],
      'goonj_chapter_admin' => [
        'hide_menus' => [
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
      ],
      'urbanops' => [
        'hide_menus' => [
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
      ],
      'ho_account' => [
        'hide_menus' => [
          'Urban Visit',
          'Account: Goonj Offices',
          'Volunteers',
          'Institute',
          'Individuals',
          'Campaigns',
        ],
      ],
      'communications_team' => [
        'hide_menus' => [
          'Institute',
          'Volunteers',
          'Events',
          'Offices',
        ],
      ],
    ];

    foreach ($roleMenuMapping as $role => $menuConfig) {
      if (\CRM_Core_Permission::check($role)) {
        $menusToHide = $menuConfig['hide_menus'];

        foreach ($params as $key => &$menu) {
          if (isset($menu['attributes']['name']) && in_array($menu['attributes']['name'], $menusToHide)) {
            $menu['attributes']['active'] = 0;
          }
        }
      }
    }
  }

}
