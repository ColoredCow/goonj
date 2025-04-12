<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;
use Civi\HelperService;
use Civi\InstitutionDroppingCenterFeedbackService;

/**
 * Goonjcustom.InstitutionDroppingCenterFeedbackCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_institution_dropping_center_feedback_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.DroppingCenterCron API.
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
function civicrm_api3_goonjcustom_institution_dropping_center_feedback_cron($params) {
  $returnValues = [];

  // Retrieve the Status option value.
  $optionValues = OptionValue::get(FALSE)
    ->addWhere('option_group_id:name', '=', 'eck_sub_types')
    ->addWhere('name', '=', 'Status')
    ->addWhere('grouping', '=', 'Dropping_Center_Meta')
    ->execute()->single();

  $statusName = $optionValues['value'];

  $droppingCenterMeta = EckEntity::get('Dropping_Center_Meta', TRUE)
    ->addSelect('Dropping_Center_Meta.Institution_Dropping_Center', 'Status.Feedback_Email_Delivered', 'Dropping_Center_Meta.Institution_Dropping_Center.Collection_Camp_Core_Details.Status')
    ->addWhere('subtype', '=', $statusName)
    ->addWhere('Status.Status:name', '=', 'Permanently_Closed')
    ->execute();

  $from = HelperService::getDefaultFromEmail();

  try {
    foreach ($droppingCenterMeta as $meta) {
      $droppingCenterId = $meta['Dropping_Center_Meta.Institution_Dropping_Center'];
      $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
        ->addSelect('Institution_Dropping_Center_Intent.Institution_POC')
        ->addWhere('id', '=', $droppingCenterId)
        ->execute()->single();

      $initiatorId = $collectionCamps['Institution_Dropping_Center_Intent.Institution_POC'];
      $status = $meta['Status.Feedback_Email_Delivered'];
      $authorizedStatus = $meta['Dropping_Center_Meta.Institution_Dropping_Center.Collection_Camp_Core_Details.Status'];

      // Trigger only if the status is 'authorized'.
      if ($authorizedStatus === 'authorized') {
        InstitutionDroppingCenterFeedbackService::processInstitutionDroppingCenterStatus($droppingCenterId, $initiatorId, $status, $from);
      }
    }
  }
  catch (Exception $e) {
    // Do Nothing
  }
  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'institution_dropping_center_feedback_cron');
}
