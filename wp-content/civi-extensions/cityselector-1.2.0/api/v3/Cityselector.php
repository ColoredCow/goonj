<?php

/**
 * Cityselector.get API
 *
 * Returns cities from specific $parent_id
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 */
function civicrm_api3_cityselector_get($params) {
  $cities = CRM_Cityselector_BAO_Location::getChainCityValues($params['parent_id'], $params['flatten']);
  return civicrm_api3_create_success($cities, $params, 'Cityselector');
}

/**
 * Cityselector.get API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $params description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_cityselector_get_spec(&$params) {
  $params['parent_id'] = [
    'name'         => 'parent_id',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Parent ID',
    'description'  => 'City\'s parent entity ID (County or State/Province)',
  ];
  $params['flatten'] = [
    'name'         => 'flatten',
    'api.default'  => FALSE,
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'title'        => 'Flatten',
    'description'  => 'Flatten the output format for cities array',
  ];
}
