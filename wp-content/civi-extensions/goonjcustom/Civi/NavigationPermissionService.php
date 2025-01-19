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
    ];
  }

  /**
   *
   */
  public function hideNavForRoles(&$params) {
    error_log("params: " . print_r($params, TRUE));
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
        ],
      ],
      'goonj_chapter_admin' => [
        'hide_menus' => [
          'MMT - Individuals',
          'MMT - Institutes',
        ],
      ],
      'urbanops' => [
        'hide_menus' => [
          'MMT - Individuals',
          'MMT - Institutes',
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
        ],
      ],
    ];

    foreach ($roleMenuMapping as $role => $menuConfig) {
      error_log("role: " . print_r($role, TRUE));
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
