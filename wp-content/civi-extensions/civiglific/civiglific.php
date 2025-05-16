<?php

require_once 'civiglific.civix.php';

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
 * Implementation of hook_civicrm_api4_entities
 */
function civiglific_civicrm_api4_entities(&$entities) {
  \Civi::log()->debug('civiglific: civicrm_api4_entities hook called');
  $entities['GlificGroupMap'] = [
    'class' => 'Civi\Api4\GlificGroupMap',
    'path' => __DIR__ . '/api/v4/GlificGroupMap.php',
  ];
  \Civi::log()->debug('civiglific: Registered GlificGroupMap entity', ['entities' => $entities]);
}
