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
          'Offices',
          'Dropping Center',
          'Institution Collection Camp',
          'Institute',
          'Institutes',
          'Collection Camps',
          'Goonj Activities',
          'Institution Dropping Center',
          'Institution Goonj Activities',
          'Inductions',
          'Volunteers',
          'Individuals',
          'Campaigns',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
        'hide_child_menus' => [
          'Material Contributions',
          'hide_child_menus' => [
            'Dashboard',
            'Contribution Reports',
            'Import Contributions',
            'Batch Data Entry',
            'Accounting Batches',
            'Manage Contribution Pages',
            'Personal Campaign Pages',
            'Premiums',
            'Manage Price Sets',
          ],
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
          'Account - Individuals',
          'Account - Institutions',
          'eck_entities',
          'My Office',
          'Contributions',
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
          'Institutes',
          'Inductions',
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
          'Volunteers',
          'Events',
          'Offices',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'Inductions',
        ],
        'hide_child_menus' => [
          'Material Contributions',
          'Institution Collection Camps',
          'Dropping Center',
          'Institution Goonj Activities',
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
        'hide_child_menus' => [
          'Institution Collection Camps',
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
          'Campaigns',
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
          'Campaigns',
          'Individuals',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
        'hide_child_menus' => [
          'Institution Collection Camps',
          'Material Contributions',
          'Dropping Center',
          'Institution Goonj Activities',
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
      'mmt_and_accounts_chapter_team' => [
        'hide_menus' => [
          'Campaigns',
          'Offices',
          'Volunteers',
          'Individuals',
          'Induction Tab',
          'Induction',
          'Inductions',
          'Institutes',
        ],
        'hide_child_menus' => [
          'Dashboard',
          'Contribution Reports',
          'Import Contributions',
          'Batch Data Entry',
          'Accounting Batches',
          'Manage Contribution Pages',
          'Personal Campaign Pages',
          'Premiums',
          'Manage Price Sets',
        ],
      ],
      'urban_ops_and_accounts_chapter_team' => [
        'hide_menus' => [
          'Campaigns',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
        ],
        'hide_child_menus' => [
          'Contribution Reports',
          'Import Contributions',
          'Batch Data Entry',
          'Accounting Batches',
          'Manage Contribution Pages',
          'Personal Campaign Pages',
          'Premiums',
          'Manage Price Sets',
        ],
      ],
    ];

    foreach ($roleMenuMapping as $role => $menuConfig) {
      if (\CRM_Core_Permission::check($role)) {
        $menusToHide = $menuConfig['hide_menus'] ?? [];
        $childMenusToHide = $menuConfig['hide_child_menus'] ?? [];

        foreach ($params as $key => &$menu) {
          // Hide top-level menu.
          if (isset($menu['attributes']['name']) && in_array($menu['attributes']['name'], $menusToHide)) {
            $menu['attributes']['active'] = 0;
          }

          // Hide child menus.
          if (isset($menu['child']) && is_array($menu['child'])) {
            foreach ($menu['child'] as $childKey => &$child) {
              if (isset($child['attributes']['name']) && in_array($child['attributes']['name'], $childMenusToHide)) {
                $child['attributes']['active'] = 0;
              }
            }
          }
        }
      }
    }
  }
}
