<?php

require_once 'civirazorpay.civix.php';

use CRM_Civirazorpay_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function civirazorpay_civicrm_config(&$config): void {
  $templateDir = __DIR__ . '/templates';
  CRM_Core_Smarty::singleton()->addTemplateDir($templateDir);
  _civirazorpay_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function civirazorpay_civicrm_install(): void {
  _civirazorpay_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function civirazorpay_civicrm_enable(): void {
  _civirazorpay_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_buildForm().
 *
 * Set a default value for the installments field in the contribution form.
 *
 * @param string $formName
 * @param CRM_Core_Form $form
 */
function civirazorpay_civicrm_buildForm($formName, &$form) {
  if ($formName === 'CRM_Contribute_Form_Contribution_Main') {
    if ($form->elementExists('installments')) {
      $form->setDefaults(['installments' => 36]);

      $form->addFormRule(function($fields) {
        if (!empty($fields['is_recur']) && empty($fields['installments'])) {
          return ['installments' => ts('The number of installments is required when recurring contribution is selected.')];
        }
        return TRUE;
      });
    }
  }
}
