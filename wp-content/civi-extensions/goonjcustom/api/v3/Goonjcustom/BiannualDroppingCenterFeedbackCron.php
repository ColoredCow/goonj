<?php

/**
 * @file
 */

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\HelperService;
use Civi\DroppingCenterFeedbackService;

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

  // Get all dropping centers where the center's open date is older than 6 months
  // and the last feedback email was either never sent or sent more than 6 months ago.
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
      ->addSelect('Status.Status:name','Status.Feedback_Email_Delivered:name')
      ->addWhere('Dropping_Center_Meta.Dropping_Center', '=', $droppingCenterId)
      ->addWhere('Status.Status:name', '=', 'Permanently_Closed')
      ->execute();


          
        foreach ($droppingCenterMeta as $meta) {
          $status = $meta['Status.Feedback_Email_Delivered:name'];    

        }
          

          // Send email only if not delivered and not permanently closed
            // Send the feedback email.
            DroppingCenterFeedbackService::processDroppingCenterStatus($droppingCenterId, $initiatorId, $status, $from);

            // Update the last_feedback_sent_date to the current date after the email is sent.
            EckEntity::update('Collection_Camp', TRUE)
              ->addWhere('id', '=', $droppingCenterId)
              ->addValue('Dropping_Centre.last_feedback_sent_date', (new \DateTime())->format('Y-m-d'))
              ->execute();
          }
        }
  catch (Exception $e) {
    \CRM_Core_Error::debug_log_message('Error processing Dropping Center feedback: ' . $e->getMessage());
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'biannual_dropping_center_feedback_cron');
}
