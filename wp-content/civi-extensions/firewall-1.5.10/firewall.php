<?php

require_once 'firewall.civix.php';
use CRM_Firewall_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function firewall_civicrm_config(&$config) {
  _firewall_civix_civicrm_config($config);

  // Symfony hook priorities - see https://docs.civicrm.org/dev/en/latest/hooks/usage/symfony/#priorities
  // Run early
  // civi.invoke.auth available from 5.36 - https://github.com/civicrm/civicrm-core/pull/19590/commits/e09616fd15aa438d4c904d3fb9da23b4893d1878
  Civi::dispatcher()->addListener('civi.invoke.auth', 'firewall_civicrm_boot', 1000);
}

function firewall_civicrm_boot() {
  $firewall = new \Civi\Firewall\Firewall();
  $firewall->run();
}

/**
 * Implements hook_civicrm_container().
 */
function firewall_civicrm_container(\Symfony\Component\DependencyInjection\ContainerBuilder $container) {
  $container->addResource(new \Symfony\Component\Config\Resource\FileResource(__FILE__));
  \Civi\Firewall\Services::registerServices($container);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function firewall_civicrm_install() {
  _firewall_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function firewall_civicrm_enable() {
  _firewall_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_alterLogTables().
 *
 * Exclude firewall tables from logging tables since they hold mostly temp data.
 */
function firewall_civicrm_alterLogTables(&$logTableSpec) {
  $tablePrefix = 'civicrm_firewall_';
  $len = strlen($tablePrefix);

  foreach ($logTableSpec as $key => $val) {
    if (substr($key, 0, $len) === $tablePrefix) {
      unset($logTableSpec[$key]);
    }
  }
}

/**
 * Implements hook_civicrm_navigationMenu().
 */
function firewall_civicrm_navigationMenu(&$menu) {
  _firewall_civix_insert_navigation_menu($menu, 'Administer/System Settings', [
    'label' => E::ts('Firewall Settings'),
    'name' => 'firewall_settings',
    'url' => 'civicrm/admin/setting/firewall',
    'permission' => 'administer CiviCRM',
    'operator' => 'OR',
    'separator' => 0,
  ]);
  _firewall_civix_navigationMenu($menu);
}
