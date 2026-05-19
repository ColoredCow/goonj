<?php

require_once 'inlay.civix.php';
// phpcs:disable
use CRM_Inlay_ExtensionUtil as E;
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function inlay_civicrm_config(&$config) {
  // Prevent double calls: https://docs.civicrm.org/dev/en/latest/hooks/usage/symfony/
  if (isset(Civi::$statics[__FUNCTION__])) {
    return;
  }
  Civi::$statics[__FUNCTION__] = 1;

  _inlay_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_permission.
 *
 * @param string $permissions
 */
function inlay_civicrm_permission(&$permissions) {
  $prefix = E::ts('Inlay') . ': ';
  $permissions += [
    'administer Inlays' => [
      'label' => $prefix . E::ts('Administer Inlays'),
      'description' => E::ts('Create, delete, edit any type of inlay'),
    ],
  ];
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
function inlay_civicrm_navigationMenu(&$menu) {
  // Could not get 'Customise Data and Screens'
  _inlay_civix_insert_navigation_menu($menu, 'Administer', array(
    'label' => E::ts('Inlays'),
    'name' => 'inlays',
    'url' => 'civicrm/a#/inlays',
    'permission' => 'administer Inlays',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _inlay_civix_navigationMenu($menu);
}

/**
 * This is called at the end of a request that has resulted in
 * at least one inlay having its bundle updated.
 *
 * The IDs of all updated bundles are held in
 * \Civi::$statics['InlayBundlesUpdated'] set in the WriteTrait
 *
 * We dispatch civi.inlay.bundleupdate event with updatedInlayIDs property
 * so that custom extensions could pick this up, for example,
 * for the sake of CMS integrations that might need a list of inlays.
 *
 */
function inlay_onShutdown() {
  $event = Civi\Core\Event\GenericHookEvent::create(
    ['updatedBundles' => \Civi::$statics['InlayBundlesUpdated'] ?? []]);
  Civi::dispatcher()->dispatch('civi.inlay.bundleupdate', $event);
}
