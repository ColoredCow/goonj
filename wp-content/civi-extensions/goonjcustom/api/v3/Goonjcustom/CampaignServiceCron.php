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
  // There are no parameters for the Goonjcustom cron.
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

  $campaigns = Campaign::get(TRUE)
    ->addSelect('id', 'Additional_Details.Type_of_Campaign', 'start_date', 'end_date', 'Additional_Details.Campaign_Goonj_PoC', 'Additional_Details.Campaign_Institution_PoC', 'Additional_Details.Branch_POC')
    ->addWhere('start_date', '>=', $today . ' 00:00:00')
    ->addWhere('start_date', '<=', $today . ' 23:59:59')
    ->addWhere('Additional_Details.Campaign_Goonj_PoC', 'IS NOT NULL')
    ->execute();

  error_log("campaigns: " . print_r($campaigns, TRUE));

  foreach ($campaigns as $campaign) {
    // Fetch and send email to Campaign Goonj PoC.
    $goonjPocId = $campaign['Additional_Details.Campaign_Goonj_PoC'];
    sendEmailToPoC($campaign['id'], $goonjPocId);

    // Fetch and send email to Campaign Institution PoC if present.
    if (!empty($campaign['Additional_Details.Campaign_Institution_PoC'])) {
      $institutionPocId = $campaign['Additional_Details.Campaign_Institution_PoC'];
      sendEmailToPoC($campaign['id'], $institutionPocId);
    }

    // Fetch and send emails to Branch PoCs.
    if (!empty($campaign['Additional_Details.Branch_POC'])) {
      $branchPoCIds = $campaign['Additional_Details.Branch_POC'];

      foreach ($branchPoCIds as $branchPoCId) {
        sendEmailToPoC($campaign['id'], $branchPoCId);
      }
    }
  }
  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'campaign_service_cron');
}

/**
 * Generate and send email to PoC.
 */
function sendEmailToPoC($campaignId, $pocId) {

  $contacts = Contact::get(FALSE)
    ->addSelect('email.email')
    ->addJoin('Email AS email', 'LEFT')
    ->addWhere('id', '=', $pocId)
    ->execute();

  if (!empty($contacts)) {
    $pocEmail = $contacts[0]['email.email'];

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

      error_log("Email sent to {$pocEmail} for campaign ID {$campaignId}");
    }
  }
}

/**
 * Generate email content for PoC.
 */
function generateEmailContent($campaignId, $pocEmail) {
  $homeUrl = \CRM_Utils_System::baseCMSURL();
  $campaignUrl = $homeUrl . 'campaign-details/#?campaignId=' . $campaignId;

  $html = "
    <p>Dear PoC,</p>
    <p>This is a reminder that your campaign with ID: <strong>{$campaignId}</strong> starts today.</p>
    <p>For more details, visit the campaign details page: <a href=\"$campaignUrl\">Campaign Details</a></p>
    <p>Best regards,<br>Goonj Campaign Team</p>";

  return $html;
}
