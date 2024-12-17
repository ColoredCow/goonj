<?php

/**
 * @file
 */

use Civi\Api4\Campaign;
use Civi\Api4\Contact;
use Civi\CollectionCampService;

/**
 * Goonjcustom.CampaignServiceCron API specification (optional)
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_campaign_service_cron_spec(&$spec) {
  // No parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.CampaignServiceCron API.
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
function civicrm_api3_goonjcustom_campaign_service_cron($params) {
  $returnValues = [];
  $today = date('Y-m-d');

  // Fetch campaigns starting today with relevant details.
  $campaigns = Campaign::get(TRUE)
    ->addSelect(
      'id',
      'Additional_Details.Type_of_Campaign',
      'start_date',
      'end_date',
      'Additional_Details.Campaign_Goonj_PoC',
      'Additional_Details.Campaign_Institution_PoC',
      'Additional_Details.Branch_POC'
    )
    ->addWhere('start_date', '>=', $today . ' 00:00:00')
    ->addWhere('start_date', '<=', $today . ' 23:59:59')
    ->addWhere('Additional_Details.Campaign_Goonj_PoC', 'IS NOT NULL')
    ->execute();

  // Process each campaign.
  foreach ($campaigns as $campaign) {
    processPoC($campaign['id'], $campaign);
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'campaign_service_cron');
}

/**
 * Process and send email to all PoCs (Goonj, Institution, and Branch).
 */
function processPoC($campaignId, $campaign) {
  // Send email to Goonj PoC.
  sendEmailToPoC($campaignId, $campaign['Additional_Details.Campaign_Goonj_PoC']);

  // // Send email to Institution PoC if available.
  // if (!empty($campaign['Additional_Details.Campaign_Institution_PoC'])) {
  //   sendEmailToPoC($campaignId, $campaign['Additional_Details.Campaign_Institution_PoC']);
  // }

  // Send email to each Branch PoC if available.
  if (!empty($campaign['Additional_Details.Branch_POC'])) {
    foreach ($campaign['Additional_Details.Branch_POC'] as $branchPoCId) {
      sendEmailToPoC($campaignId, $branchPoCId);
    }
  }
}

/**
 * Generate and send email to PoC.
 */
function sendEmailToPoC($campaignId, $pocId) {
  // Fetch contact details for the PoC.
  $contact = Contact::get(FALSE)
    ->addSelect('email.email')
    ->addJoin('Email AS email', 'LEFT')
    ->addWhere('id', '=', $pocId)
    ->execute();

  if (!empty($contact)) {
    $pocEmail = $contact[0]['email.email'];

    if (!empty($pocEmail)) {
      $subject = "Campaign Reminder: Campaign starting today";
      $body = generateEmailContent($campaignId, $pocEmail);

      $mailParams = [
        'subject' => $subject,
        'from' => CollectionCampService::getFromAddress(),
        'toEmail' => $pocEmail,
        'replyTo' => $from,
        'html' => $body,
      ];
      \CRM_Utils_Mail::send($mailParams);

      \Civi::log()->info("Email sent to {$pocEmail} for campaign ID {$campaignId}");
    }
  }
}

/**
 * Generate email content for PoC.
 */
function generateEmailContent($campaignId, $pocEmail) {
  $html = "
    <p>Dear {$pocEmail},</p>
    <p>This is a reminder that your campaign with ID: <strong>{$campaignId}</strong> starts today.</p>
    <p>Best regards,<br>Goonj Campaign Team</p>";

  return $html;
}
