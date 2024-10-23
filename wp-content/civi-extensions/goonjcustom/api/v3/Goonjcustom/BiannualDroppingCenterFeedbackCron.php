<?php

/**
 * @file
 */

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\DroppingCenterFeedbackCron;

/**
 *
 */
function _civicrm_api3_goonjcustom_biannual_dropping_center_feedback_cron_spec(&$spec) {
}

/**
 *
 */
function civicrm_api3_goonjcustom_biannual_dropping_center_feedback_cron($params) {
  $returnValues = [];

  $thresholdDate = (new \DateTime())->modify('-6 months')->format('Y-m-d');

  $droppingCenters = EckEntity::get('Collection_Camp', TRUE)
    ->addSelect('Dropping_Centre.When_do_you_wish_to_open_center_Date_', 'id', 'Collection_Camp_Core_Details.Contact_Id')
    ->addWhere('Dropping_Centre.When_do_you_wish_to_open_center_Date_', '<=', $thresholdDate)
    ->execute();

  [$defaultFromName, $defaultFromEmail] = CRM_Core_BAO_Domain::getNameAndEmail();
  $from = "\"$defaultFromName\" <$defaultFromEmail>";

  try {
    foreach ($droppingCenters as $center) {
      $droppingCenterId = $center['id'];
      $initiatorId = $center['Collection_Camp_Core_Details.Contact_Id'];

      $campAttendedBy = Contact::get(TRUE)
        ->addSelect('email.email', 'display_name')
        ->addJoin('Email AS email', 'LEFT')
        ->addWhere('id', '=', $initiatorId)
        ->execute()->single();

      if ($campAttendedBy) {
        $contactEmailId = $campAttendedBy['email.email'];
        $organizingContactName = $campAttendedBy['display_name'];

        DroppingCenterFeedbackCron::sendFeedbackEmail($organizingContactName, $droppingCenterId, $contactEmailId, $from);
      }
    }
  }
  catch (Exception $e) {
    \CRM_Core_Error::debug_log_message('Error processing Dropping Center feedback: ' . $e->getMessage());
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'biannual_dropping_center_feedback_cron');
}
