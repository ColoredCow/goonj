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
      '&hook_civicrm_pageRun' => [
        ['hideButtonsForMMT'],
        ['hideAPIKeyTab'],
        ['hideContributionFields'],
        ['hideSearchIcon'],
        ['hideDownloadSpreadsheet'],
        ['hideDashlets'],
      ],
    ];
  }

  /**
   *
   */
  public function hideContributionFields(&$page) {
    if ($page->getVar('_name') === 'CRM_Eck_Page_Entity_View') {
      \CRM_Core_Resources::singleton()->addScript("
    (function($) {
      $(document).ready(function() {
      const searchParams = new URLSearchParams(window.location.search);
      const hasGoonjActivities = searchParams.has('goonj_activites');

      if (hasGoonjActivities) {
        const labelsToHide = [
        'Total Number of unique contributors',
        'Total Number of unique material contributors'
        ];

          $('table.crm-info-panel tr').each(function() {
            const label = $(this).find('td.label').text().trim();
            if (labelsToHide.includes(label)) {
              $(this).hide();
            }
          });
        }
      });
    })(CRM.$);
  ");
    }
  }

  /**
   *
   */
  public function hideAPIKeyTab(&$page) {
    if ($page->getVar('_name') === 'CRM_Contact_Page_View_Summary') {
      if (!\CRM_Core_Permission::check('admin')) {
        \CRM_Core_Resources::singleton()->addScript("
          document.addEventListener('DOMContentLoaded', function() {
            const apiTab = document.querySelector('#tab_apiKey');
            if (apiTab) {
              apiTab.style.display = 'none';
            }
          });
        ");
      }
    }
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
  public function hideSearchIcon() {
    $isAdmin = \CRM_Core_Permission::check('admin');
    if ($isAdmin) {
      return;
    }
    $rolesWithHiddenSearch = ['communications_team', 'mmt', 'mmt_and_accounts_chapter_team', 'njpc_ho_team', 's2s_ho_team', 'project_team_ho', 'sanjha_team'];
    foreach ($rolesWithHiddenSearch as $role) {
      if (\CRM_Core_Permission::check($role)) {
        \CRM_Core_Resources::singleton()->addStyle("
          #crm-qsearch { display: none !important; }
        ");
        break;
      }
    }
  }

  /**
   * Hides the "Download Spreadsheet" and "Export Activities" actions from SearchKit displays
   * for all roles except admin, urban_ops_admin, and ho_account.
   */
  public function hideDownloadSpreadsheet() {
    $allowedRoles = ['admin', 'urban_ops_admin', 'ho_account'];
    foreach ($allowedRoles as $role) {
      if (\CRM_Core_Permission::check($role)) {
        return;
      }
    }

    \CRM_Core_Resources::singleton()->addScript("
      (function() {
        var labelsToHide = ['Download Spreadsheet', 'Export Activities'];
        function removeRestrictedActions() {
          document.querySelectorAll('.dropdown-menu li, .crm-search-tasks li').forEach(function(li) {
            var el = li.querySelector('a, button');
            if (el && labelsToHide.indexOf(el.textContent.trim()) !== -1) {
              li.style.display = 'none';
            }
          });
        }
        document.addEventListener('DOMContentLoaded', function() {
          removeRestrictedActions();
          new MutationObserver(removeRestrictedActions).observe(document.body, { childList: true, subtree: true });
        });
      })();
    ");
  }

  /**
   * Hides the dashlet controls and all opened dashlets on the CiviCRM dashboard
   * for roles that should not manage or view dashlets (e.g. mmt).
   */
  public function hideDashlets() {
    if (\CRM_Core_Permission::check('admin')) {
      return;
    }
    $rolesWithHiddenDashlets = [
      'mmt',
      'ho_account',
      'communications_team',
      'sanjha_team',
      'project_team_ho',
      'project_team_chapter',
      's2s_ho_team',
      'njpc_ho_team',
      'data_entry',
      'mmt_and_accounts_chapter_team',
      'project_ho_and_accounts',
    ];
    foreach ($rolesWithHiddenDashlets as $role) {
      if (\CRM_Core_Permission::check($role)) {
        \CRM_Core_Resources::singleton()->addStyle("
          .crm-inactive-dashlet-fieldset,
          .crm-dashlet,
          #refreshall { display: none !important; }
        ");
        break;
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
          'My Office',
          'Project HO Institutes',
          'Sanjha Institute List',
        ],
        'hide_child_menus' => [
          'Material Contributions',
          'Dashboard',
          'Contribution Reports',
          'Import Contributions',
          'Batch Data Entry',
          'Accounting Batches',
          'Manage Contribution Pages',
          'Personal Campaign Pages',
          'Premiums',
          'Manage Price Sets',
          'Find Contributions',
          'NJPC Institution Goonj Activities',
          'S2S Institution Collection Camp',
          'S2S Institution Dropping Center',
          'NJPC Individual Goonj Activities',
          'S2S Institution Goonj Activities',
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
          'Search',
          'Contacts',
          'Events',
          'Urban Visits',
          'Reports',
          'Project HO Institutes',
          'Sanjha Institute List',
        ],
        'hide_child_menus' => [
          'Manage Groups',
          'NJPC Institution Goonj Activities',
          'S2S Institution Collection Camp',
          'S2S Institution Dropping Center',
          'NJPC Individual Goonj Activities',
          'S2S Institution Goonj Activities',
        ],
      ],
      'goonj_chapter_admin' => [
        'hide_menus' => [
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'Project HO Institutes',
          'Sanjha Institute List',
        ],
        'hide_child_menus' => [
          'NJPC Institution Goonj Activities',
          'S2S Institution Collection Camp',
          'S2S Institution Dropping Center',
          'NJPC Individual Goonj Activities',
          'S2S Institution Goonj Activities',
        ],
      ],
      'urban_ops_admin' => [
        'hide_menus' => [
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
          'Mailings',
          'Project HO Institutes',
          'Sanjha Institute List',
        ],
        'hide_child_menus' => [
          'Manage Groups',
          'Manage Duplicates',
          'NJPC Institution Goonj Activities',
          'S2S Institution Collection Camp',
          'S2S Institution Dropping Center',
          'NJPC Individual Goonj Activities',
          'S2S Institution Goonj Activities',
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
          'My Office',
          'Search',
          'Reports',
          'Project HO Institutes',
          'Sanjha Institute List',
        ],
        'hide_child_menus' => [
          'Manage Groups',
          'NJPC Institution Goonj Activities',
          'S2S Institution Collection Camp',
          'S2S Institution Dropping Center',
          'NJPC Individual Goonj Activities',
          'S2S Institution Goonj Activities',
        ],
      ],
      'communications_team' => [
        'hide_menus' => [
          'Volunteers',
          'Events',
          'Offices',
          'Individuals',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'Inductions',
          'Search',
          'Campaigns',
          'Administer',
          'Glific Integration',
          'Support',
          'Institutes',
          'Urban Visits',
          'Contacts',
          'Reports',
          'Project HO Institutes',
          'Sanjha Institute List',
        ],
        'hide_child_menus' => [
          'Material Contributions',
          'Institution Collection Camps',
          'Dropping Center',
          'Institution Goonj Activities',
          'Manage Groups',
          'Manage Duplicates',
          'NJPC Institution Goonj Activities',
          'S2S Institution Collection Camp',
          'S2S Institution Dropping Center',
          'NJPC Individual Goonj Activities',
          'S2S Institution Goonj Activities',
        ],
      ],
      'sanjha_team' => [
        'hide_menus' => [
          'Induction',
          'Inductions',
          'Volunteers',
          'Individuals',
          'Offices',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'Search',
          'Contacts',
          'Reports',
          'Urban Visits',
          'Project HO Institutes',
          'Institutes',
        ],
        'hide_child_menus' => [
          'Institution Collection Camps',
          'Manage Groups',
          'NJPC Institution Goonj Activities',
          'S2S Institution Collection Camp',
          'S2S Institution Dropping Center',
          'Material Contributions',
          'Institution Goonj Activities',
          'NJPC Individual Goonj Activities',
          'S2S Institution Goonj Activities',
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
          'Project HO Institutes',
          'Sanjha Institute List',
        ],
        'hide_child_menus' => [
          'NJPC Institution Goonj Activities',
          'S2S Institution Collection Camp',
          'S2S Institution Dropping Center',
          'NJPC Individual Goonj Activities',
          'S2S Institution Goonj Activities',
        ],
      ],
      'project_team_ho' => [
        'hide_menus' => [
          'Inductions',
          'Volunteers',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'Search',
          'Contacts',
          'Events',
          'Campaigns',
          'Reports',
          'Support',
          'Individuals',
          'Institutes',
          'Sanjha Institute List',
        ],
        'hide_child_menus' => [
          'Manage Groups',
          'NJPC Institution Goonj Activities',
          'S2S Institution Collection Camp',
          'S2S Institution Dropping Center',
          'NJPC Individual Goonj Activities',
          'S2S Institution Goonj Activities',
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
          'Sanjha Institute List',
          'Search',
          'Contacts',
          'Events',
          'Campaigns',
          'Reports',
          'Support',
          'Institutes',
        ],
        'hide_child_menus' => [
          'Manage Groups',
          'NJPC Institution Goonj Activities',
          'S2S Institution Collection Camp',
          'S2S Institution Dropping Center',
          'NJPC Individual Goonj Activities',
          'S2S Institution Goonj Activities',
        ],
      ],
      'njpc_ho_team' => [
        'hide_menus' => [
          'Inductions',
          'Volunteers',
          'Campaigns',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'Search',
          'Contacts',
          'Reports',
          'Offices',
          'Urban Visits',
          'Events',
          'Project HO Institutes',
          'Sanjha Institute List',
        ],
        'hide_child_menus' => [
          'Material Contributions',
          'Manage Groups',
          'S2S Institution Collection Camp',
          'S2S Institution Dropping Center',
          'S2S Institution Goonj Activities',
        ],
        'hide_child_menus_under' => [
          'Individuals' => ['Collection Camps', 'Dropping Centers', 'Goonj Activities'],
          'Institutes' => ['Institution Collection Camps', 'Dropping Center', 'Institution Goonj Activities'],
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
          'Search',
          'Contacts',
          'Reports',
          'Project HO Institutes',
          'Sanjha Institute List',
        ],
        'hide_child_menus' => [
          'Manage Groups',
          'NJPC Institution Goonj Activities',
          'NJPC Individual Goonj Activities',
        ],
        'hide_child_menus_under' => [
          'Institutes' => ['Institution Collection Camps', 'Dropping Center', 'Institution Goonj Activities'],
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
          'Search',
          'Project HO Institutes',
          'Sanjha Institute List',
        ],
        'hide_child_menus' => [
          'NJPC Institution Goonj Activities',
          'S2S Institution Collection Camp',
          'S2S Institution Dropping Center',
          'NJPC Individual Goonj Activities',
          'S2S Institution Goonj Activities',
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
          'My Office',
          'Search',
          'Contacts',
          'Events',
          'Reports',
          'Urban Visits',
          'Project HO Institutes',
          'Sanjha Institute List',
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
          'Find Contributions',
          'Manage Groups',
          'NJPC Institution Goonj Activities',
          'S2S Institution Collection Camp',
          'S2S Institution Dropping Center',
          'NJPC Individual Goonj Activities',
          'S2S Institution Goonj Activities',
        ],
      ],
      'urban_ops_and_accounts_chapter_team' => [
        'hide_menus' => [
          'Campaigns',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'My Office',
          'Account - Individuals',
          'Account - Institutions',
          'Search',
          'Project HO Institutes',
          'Sanjha Institute List',
        ],
        'hide_child_menus' => [
          'Contribution Reports',
          'Dashboard',
          'Import Contributions',
          'Batch Data Entry',
          'Accounting Batches',
          'Manage Contribution Pages',
          'Personal Campaign Pages',
          'Premiums',
          'Manage Price Sets',
          'Find Contributions',
          'Manage Groups',
          'NJPC Institution Goonj Activities',
          'S2S Institution Collection Camp',
          'S2S Institution Dropping Center',
          'NJPC Individual Goonj Activities',
          'S2S Institution Goonj Activities',
        ],
      ],
      'project_ho_and_accounts' => [
        'hide_menus' => [
          'Induction Tab',
          'Induction',
          'Inductions',
          'Volunteers',
          'Individuals',
          'MMT - Individuals',
          'MMT - Institutes',
          'MMT - Offices',
          'MMT - Urban Visits',
          'Account - Individuals',
          'Account - Institutions',
          'Search',
          'Events',
          'Campaigns',
          'Reports',
          'Offices',
          'Project HO Institutes',
          'Sanjha Institute List',
        ],
        'hide_child_menus' => [
          'New Contribution',
          'Import Contributions',
          'Batch Data Entry',
          'Accounting Batches',
          'Manage Contribution Pages',
          'Personal Campaign Pages',
          'Premiums',
          'Manage Price Sets',
          'Find Contributions',
          'Manage Groups',
          'NJPC Institution Goonj Activities',
          'S2S Institution Collection Camp',
          'S2S Institution Dropping Center',
          'NJPC Individual Goonj Activities',
          'S2S Institution Goonj Activities',
        ],
      ]
    ];

    foreach ($roleMenuMapping as $role => $menuConfig) {
      if (\CRM_Core_Permission::check($role)) {
        $menusToHide = $menuConfig['hide_menus'] ?? [];
        $childMenusToHide = $menuConfig['hide_child_menus'] ?? [];
        $childMenusUnder = $menuConfig['hide_child_menus_under'] ?? [];

        foreach ($params as $key => &$menu) {
          // Hide top-level menu.
          if (isset($menu['attributes']['name']) && in_array($menu['attributes']['name'], $menusToHide)) {
            $menu['attributes']['active'] = 0;
          }

          // Hide child menus.
          if (isset($menu['child']) && is_array($menu['child'])) {
            $parentName = $menu['attributes']['name'] ?? '';
            foreach ($menu['child'] as $childKey => &$child) {
              $childName = $child['attributes']['name'] ?? '';
              if (in_array($childName, $childMenusToHide)) {
                $child['attributes']['active'] = 0;
              }
              // Hide child menus scoped to a specific parent.
              if (isset($childMenusUnder[$parentName]) && in_array($childName, $childMenusUnder[$parentName])) {
                $child['attributes']['active'] = 0;
              }
            }
          }
        }
      }
    }
  }

}
