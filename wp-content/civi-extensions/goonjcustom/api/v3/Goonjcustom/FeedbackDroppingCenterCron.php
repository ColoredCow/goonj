<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;
use Civi\DroppingCenterFeedbackCron;

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
    ->setLimit(1)
    ->execute()->single();

  $statusName = $optionValues['value'];

  $droppingCenterMeta = EckEntity::get('Dropping_Center_Meta', TRUE)
    ->addSelect('Status.Status:name', 'Status.Closing_Date', 'Dropping_Center_Meta.Dropping_Center', 'Dropping_Center_Meta.Dropping_Center.Collection_Camp_Core_Details.Contact_Id', 'Status.Feedback_Email_Delivered:name')
    ->addWhere('subtype', '=', $statusName)
    ->addWhere('Status.Status:name', '=', 'Parmanently_Closed')
    ->execute();

  [$defaultFromName, $defaultFromEmail] = CRM_Core_BAO_Domain::getNameAndEmail();
  $from = "\"$defaultFromName\" <$defaultFromEmail>";

  try {
    foreach ($droppingCenterMeta as $meta) {
      DroppingCenterFeedbackCron::processDroppingCenterStatus($meta, $from);
    }
  }
  catch (Exception $e) {
    error_log("Error processing: " . $e->getMessage());
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'feedback_dropping_center_cron');
}
