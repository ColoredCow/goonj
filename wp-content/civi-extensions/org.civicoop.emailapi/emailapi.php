<?php
require_once 'emailapi.civix.php';
use CRM_Emailapi_ExtensionUtil as E;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Implements hook_civicrm_container().
 *
 * @param ContainerBuilder $container
 */
function emailapi_civicrm_container(ContainerBuilder $container) {
  if (class_exists('Civi\Emailapi\CompilerPass')) {
    $container->addCompilerPass(new Civi\Emailapi\CompilerPass());
  }
}

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function emailapi_civicrm_config(&$config) {
  _emailapi_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function emailapi_civicrm_install() {
  _emailapi_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function emailapi_civicrm_enable() {
  _emailapi_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function emailapi_civicrm_managed(&$entities)
{
    // Load the triggers when civirules is installed.
    if (!empty(\Civi\Api4\Extension::get(FALSE)
        ->addWhere('file', '=', 'civirules')
        ->addWhere('status:name', '=', 'installed')
        ->execute()
        ->first())) {
        CRM_Civirules_Utils_Upgrader::insertActionsFromJson(E::path('civirules/actions.json'));
    }
}
