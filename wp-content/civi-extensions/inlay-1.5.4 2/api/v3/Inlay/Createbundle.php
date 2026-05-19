<?php
use CRM_Inlay_ExtensionUtil as E;

/**
 * Inlay.Createbundle API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_inlay_Createbundle_spec(&$spec) {
  // @todo copy spec from api4
}

/**
 * Inlay.Createbundle API
 *
 * This is just a wrapper around api4 because I'm not sure if you can have api4 scheduled jobs.
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws CRM_Core_Exception
 */
function civicrm_api3_inlay_Createbundle($params) {
  $returnValues = \Civi\Api4\Inlay::createBundle(FALSE)
    ->setCheckPermissions(FALSE)
    ->execute()
    ->getArrayCopy();
  return civicrm_api3_create_success($returnValues, $params, 'Inlay', 'createbundle');
}
