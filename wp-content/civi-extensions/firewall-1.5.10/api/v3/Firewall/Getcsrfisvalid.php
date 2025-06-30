<?php
use CRM_Firewall_ExtensionUtil as E;

/**
 * Firewall.Getcsrfisvalid API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_firewall_Getcsrfisvalid($params) {
  $isValid = \Civi\Firewall\Firewall::isCSRFTokenValid($params['token']);
  return civicrm_api3_create_success(['valid' => $isValid], $params, 'Firewall', 'Getcsrfisvalid');
}

/**
 * @param array $params
 *
 */
function _civicrm_api3_firewall_Getcsrfisvalid_spec(&$params) {
  $params['token']['type'] = CRM_Utils_Type::T_STRING;
  $params['token']['title'] = 'CSRF token to check for validity';
}
