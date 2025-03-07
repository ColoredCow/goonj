<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\HelperService;
use Civi\InstitutionDroppingCenterFeedbackService;

/**
 *
 */
function _civicrm_api3_goonjcustom_biannual_institution_dropping_center_feedback_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 *
 */
function civicrm_api3_goonjcustom_biannual_institution_dropping_center_feedback_cron($params) {
  $returnValues = [];

  // Set threshold date to 6 months ago.
  $thresholdDate = (new \DateTime())->modify('-6 months')->format('Y-m-d');

  // And the last feedback email was either never sent or sent more than 6 months ago.
  $droppingCenters = EckEntity::get('Collection_Camp', TRUE)
    ->addSelect('Dropping_Centre.last_feedback_sent_date', 'Institution_Dropping_Center_Intent.Institution_POC')
    ->addWhere('Institution_Dropping_Center_Intent.When_do_you_wish_to_open_center_Date_', '<=', $thresholdDate)
    ->addWhere('Collection_Camp_Core_Details.Status:name', '=', 'Authorized')
    ->addClause('OR', ['Dropping_Centre.last_feedback_sent_date', 'IS NULL'], ['Dropping_Centre.last_feedback_sent_date', '<=', $thresholdDate])
    ->execute();

  $from = HelperService::getDefaultFromEmail();

  try {
    foreach ($droppingCenters as $center) {
      $droppingCenterId = $center['id'];
      $initiatorId = $center['Institution_Dropping_Center_Intent.Institution_POC'];

      $droppingCenterMeta = EckEntity::get('Dropping_Center_Meta', TRUE)
        ->addSelect('Status.Status:name', 'Status.Feedback_Email_Delivered')
        ->addWhere('Dropping_Center_Meta.Institution_Dropping_Center', '=', $droppingCenterId)
        ->addWhere('Status.Status:name', '=', 'Permanently_Closed')
        ->execute();

      $status = !empty($droppingCenterMeta) ? $droppingCenterMeta[0]['Status.Feedback_Email_Delivered'] : NULL;

      // Send email only if not delivered and not permanently closed
      // Send the feedback email.
      if (empty($droppingCenterMeta[0])) {
        // Proceed with sending the feedback email.
        InstitutionDroppingCenterFeedbackService::processInstitutionDroppingCenterStatus($droppingCenterId, $initiatorId, $status, $from);

        // Update the last_feedback_sent_date to the current date after the email is sent.
        InstitutionDroppingCenterFeedbackService::updateFeedbackLastSentDate($droppingCenterId);
      }
      else {
        \CRM_Core_Error::debug_log_message("Feedback cron: Dropping Center ID {$droppingCenterId} is marked as 'Permanently Closed'. As a result, no email will be sent by the biannual feedback cron.");
      }
    }
  }
  catch (Exception $e) {
    \CRM_Core_Error::debug_log_message('Error processing Dropping Center feedback: ' . $e->getMessage());
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'biannual_institution_dropping_center_feedback_cron');
}
