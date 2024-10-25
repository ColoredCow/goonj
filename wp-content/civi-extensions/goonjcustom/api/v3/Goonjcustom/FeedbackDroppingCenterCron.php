<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;
use Civi\DroppingCenterFeedbackService;
use Civi\HelperService;

/**
 * Goonjcustom.FeedbackDroppingCenterCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_feedback_dropping_center_cron_spec(&$spec) {
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
function civicrm_api3_goonjcustom_feedback_dropping_center_cron($params) {
  $returnValues = [];

  // Retrieve the Status option value.
  $optionValues = OptionValue::get(FALSE)
    ->addWhere('option_group_id:name', '=', 'eck_sub_types')
    ->addWhere('name', '=', 'Status')
    ->addWhere('grouping', '=', 'Dropping_Center_Meta')
    ->execute()->single();

  $statusName = $optionValues['value'];

  $droppingCenterMeta = EckEntity::get('Dropping_Center_Meta', TRUE)
    ->addSelect('Dropping_Center_Meta.Dropping_Center', 'Dropping_Center_Meta.Dropping_Center.Collection_Camp_Core_Details.Contact_Id', 'Status.Feedback_Email_Delivered:name')
    ->addWhere('subtype', '=', $statusName)
    ->addWhere('Status.Status:name', '=', 'Permanently_Closed')
    ->execute();

  $from = HelperService::getDefaultFromEmail();

  try {
    foreach ($droppingCenterMeta as $meta) {
      $droppingCenterId = $meta['Dropping_Center_Meta.Dropping_Center'];
      $initiatorId = $meta['Dropping_Center_Meta.Dropping_Center.Collection_Camp_Core_Details.Contact_Id'];
      $status = $meta['Status.Feedback_Email_Delivered:name'];
      
      DroppingCenterFeedbackService::processDroppingCenterStatus($droppingCenterId, $initiatorId, $status, $from);
    }
  }
  catch (Exception $e) {
    \CRM_Core_Error::debug_log_message('Error processing Dropping Center ID: ' . $meta['Dropping_Center_Meta.Dropping_Center']);
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'feedback_dropping_center_cron');
}
