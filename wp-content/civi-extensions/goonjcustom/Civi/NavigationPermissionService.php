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
                document.addEventListener('DOMContentLoaded', function() {
                    document.querySelectorAll('.crm-actions-ribbon').forEach(el => el.style.display = 'none');
                    
                    document.querySelectorAll('afsearch-induction-details-of-contact').forEach(el => el.style.display = 'none');
                    
                    document.querySelectorAll('.crm-collapsible').forEach(function(el) {
                        const title = el.querySelector('.collapsible-title');
                        if (title && title.textContent.trim() === 'Volunteer Details') {
                            el.style.display = 'none';  // Hides the entire collapsible section
                        }
                    });
                });
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
          'Inductions',
          'Volunteers',
          'Urban Visit',
          'Individuals',
          'Contributions',
          'Campaigns',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
      ],
      'mmt' => [
        'hide_menus' => [
          'Dropping Center',
          'Inductions',
          'Institution Collection Camp',
          'Collection Camps',
          'Goonj Activities',
          'Institutes',
          'Inductions',
          'Institution Dropping Center',
          'Institution Goonj Activities',
          'Inductions',
          'Volunteers',
          'Individuals',
          'Offices',
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
      'urban_ops_admin' => [
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
          'Contributions',
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
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
      ],
      'communications_team' => [
        'hide_menus' => [
          'Institutes',
          'Volunteers',
          'Events',
          'Offices',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'Inductions',
        ],
      ],
      'sanjha_team' => [
        'hide_menus' => [
          'Induction',
          'Inductions',
          'Volunteers',
          'Offices',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
      ],
      'data_team' => [
        'hide_menus' => [
          'Institute',
          'Volunteers',
          'Events',
          'Offices',
          'Inductions',
          'Volunteers',
          'Events',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
      ],
      'project_team_ho' => [
        'hide_menus' => [
          'Inductions',
          'Volunteers',
          'Individuals',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
      ],
      'project_team_chapter' => [
        'hide_menus' => [
          'Inductions',
          'Volunteers',
          'Individuals',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
      ],
      'njpc_ho_team' => [
        'hide_menus' => [
          'Inductions',
          'Volunteers',
          'Individuals',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
      ],
      's2s_ho_team' => [
        'hide_menus' => [
          'Inductions',
          'Individuals',
          'Volunteers',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
      ],
      'data_entry' => [
        'hide_menus' => [
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'Contributions',
          'Contacts',
          'Events',
          'Campaigns',
          'Volunteers',
          'Urban Visit',
          'Induction Tab',
          'Induction',
          'Inductions',
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
