<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\DroppingCenterFeedbackService;
use Civi\HelperService;

/**
 *
 */
function _civicrm_api3_goonjcustom_biannual_dropping_center_feedback_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 *
 */
function civicrm_api3_goonjcustom_biannual_dropping_center_feedback_cron($params) {
  $returnValues = [];

  // Set threshold date to 6 months ago.
  $thresholdDate = (new \DateTime())->modify('-6 months')->format('Y-m-d');

  // And the last feedback email was either never sent or sent more than 6 months ago.
  $droppingCenters = EckEntity::get('Collection_Camp', TRUE)
    ->addSelect('Collection_Camp_Core_Details.Contact_Id', 'Dropping_Centre.last_feedback_sent_date')
    ->addWhere('Dropping_Centre.When_do_you_wish_to_open_center_Date_', '<=', $thresholdDate)
    ->addWhere('Collection_Camp_Core_Details.Status:name', '=', 'Authorized')
    ->addClause('OR', ['Dropping_Centre.last_feedback_sent_date', 'IS NULL'], ['Dropping_Centre.last_feedback_sent_date', '<=', $thresholdDate])
    ->execute();

  $from = HelperService::getDefaultFromEmail();

  try {
    foreach ($droppingCenters as $center) {
      $droppingCenterId = $center['id'];
      $initiatorId = $center['Collection_Camp_Core_Details.Contact_Id'];

      $droppingCenterMeta = EckEntity::get('Dropping_Center_Meta', TRUE)
        ->addSelect('Status.Status:name', 'Status.Feedback_Email_Delivered')
        ->addWhere('Dropping_Center_Meta.Dropping_Center', '=', $droppingCenterId)
        ->addWhere('Status.Status:name', '=', 'Permanently_Closed')
        ->execute();

      $status = !empty($droppingCenterMeta) ? $droppingCenterMeta[0]['Status.Feedback_Email_Delivered'] : null;
      // Send email only if not delivered and not permanently closed
      // Send the feedback email.
      DroppingCenterFeedbackService::processDroppingCenterStatus($droppingCenterId, $initiatorId, $status, $from);

      // Update the last_feedback_sent_date to the current date after the email is sent.
      DroppingCenterFeedbackService::updateFeedbackLastSentDate($droppingCenterId);
    }
  }
  catch (Exception $e) {
    \CRM_Core_Error::debug_log_message('Error processing Dropping Center feedback: ' . $e->getMessage());
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'biannual_dropping_center_feedback_cron');
}
