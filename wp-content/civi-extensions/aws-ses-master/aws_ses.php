<?php

require_once 'aws_ses.civix.php';
// phpcs:disable
use CRM_AwsSes_ExtensionUtil as E;
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function aws_ses_civicrm_config(&$config) {
  _aws_ses_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function aws_ses_civicrm_install() {
  _aws_ses_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function aws_ses_civicrm_enable() {
  _aws_ses_civix_civicrm_enable();
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 */
//function aws_ses_civicrm_preProcess($formName, &$form) {
//
//}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
function aws_ses_civicrm_navigationMenu(&$menu) {
  _aws_ses_civix_insert_navigation_menu($menu, 'Administer/CiviMail', [
    'label' => E::ts('AWS SES Settings'),
    'name' => 'mailing_aws_ses_settings',
    'url' => 'civicrm/admin/settings/aws-ses',
    'permission' => 'access CiviMail',
    'operator' => 'OR',
    'separator' => 0,
  ]);
  _aws_ses_civix_navigationMenu($menu);
}
