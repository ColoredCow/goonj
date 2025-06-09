<?php

require_once 'civiglific.civix.php';
require_once __DIR__ . '/vendor/autoload.php';

use CRM_Civiglific_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function civiglific_civicrm_config(&$config): void {
  _civiglific_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function civiglific_civicrm_install(): void {
  _civiglific_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function civiglific_civicrm_enable(): void {
  _civiglific_civix_civicrm_enable();
}

function civiglific_civicrm_xmlMenu(&$files) {
  $files[] = __DIR__ . '/xml/Menu.xml';
}

/**
 * Implements hook_civicrm_pageRun().
 */
function civiglific_civicrm_pageRun(&$page) {
  CRM_Core_Resources::singleton()->addScriptFile('civiglific', 'js/main.js');
}
