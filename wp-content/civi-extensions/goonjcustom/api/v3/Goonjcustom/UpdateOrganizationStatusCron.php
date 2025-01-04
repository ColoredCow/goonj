<?php

/**
 * @file
 */

use Civi\Api4\Contact;
use Civi\InstitutionService;

/**
 * Goonjcustom.UpdateOrganizationStatusCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_update_organization_status_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.UpdateOrganizationStatus API.
 *
 * @param array $params
 *
 * @return array API result descriptor
 *
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_goonjcustom_update_organization_status_cron($params) {
  $returnValues = [];

  $contacts = Contact::get(TRUE)
    ->addWhere('contact_sub_type', '=', 'Institute')
    ->execute();

  foreach ($contacts as $contact) {
    try {
      InstitutionService::updateOrganizationStatus($contact);
    }
    catch (\Exception $e) {
      \Civi::log()->info('Error processing visit', [
        'error' => $e->getMessage(),
      ]);
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'update_organization_status_cron');
}
