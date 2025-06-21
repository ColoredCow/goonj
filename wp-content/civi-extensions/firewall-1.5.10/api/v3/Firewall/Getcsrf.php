<?php
use CRM_Firewall_ExtensionUtil as E;

/**
 * Firewall.GetCsrf API
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
function civicrm_api3_firewall_Getcsrf($params) {
  $publicToken = \Civi\Firewall\Firewall::getCSRFToken();
  return civicrm_api3_create_success(['token' => $publicToken], $params, 'Firewall', 'Getcsrf');
}
