<?php

namespace Civi;

use Civi\Api4\ActionSchedule;
use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Api4\Campaign;
use Civi\Api4\Relationship;
use Civi\Api4\Organization;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class InstitutionMaterialContributionService extends AutoSubscriber {
  const ACTIVITY_SOURCE_RECORD_TYPE_ID = 2;

  /**
   * Fetch the Material Contribution Receipt Reminder ID.
   */

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => 'processInstitutionMaterialContributions',
    ];
  }

  public static function processInstitutionMaterialContributions(string $op, string $objectName, int $objectId, &$objectRef)
  {
      if ($objectName !== 'AfformSubmission') {
          return;
      }
  
      $afformName = $objectRef->afform_name;
  
      if ($afformName !== 'afformAddInstitutionMaterialContribution') {
          return;
      }
  
      // Decode the data field from the AfformSubmission object.
      $data = json_decode($objectRef->data, TRUE);
      if (!$data) {
          error_log("Error decoding data: " . print_r($objectRef->data, TRUE));
          return;
      }
      error_log("data " . print_r($data, TRUE));
      // Extract required fields from the decoded data.
      $activityData = $data['Activity1'][0]['fields'];
      error_log("activityData " . print_r($activityData, TRUE));
      $brandingCampaignId = $activityData['Institution_Material_Contribution.Co_branding_Campaign'];
      $campaignId = $activityData['Institution_Material_Contribution.Campaign'];
      $goonjOffice = $activityData['Institution_Material_Contribution.Goonj_Office'];
      $description = $activityData['Institution_Material_Contribution.Description_of_Material_No_of_Bags_Material_'];
      $deliveredBy = $activityData['Institution_Material_Contribution.Delivered_By_Name'];
      $deliveredByContact = $activityData['Institution_Material_Contribution.Delivered_By_Contact'];
      $institutionId = $activityData['Additional_Details.Institution'];
      

      $organizationId = isset($data['Organization1'][0]['fields']['id']) ? $data['Organization1'][0]['fields']['id'] : null;

      // Fetch campaigns based on the brandingCampaignId or campaignId.
      $coBrandingCampaigns = [];
      $campaigns = [];
      if ($brandingCampaignId) {
          $coBrandingCampaigns = Campaign::get(TRUE)
              ->addSelect(
                  'id',
                  'Additional_Details.Type_of_Campaign',
                  'start_date',
                  'end_date',
                  'Additional_Details.Campaign_Goonj_PoC',
                  'Additional_Details.Campaign_Institution_PoC',
                  'Additional_Details.Branch_Wise_POCs'
              )
              ->addWhere('id', '=', $brandingCampaignId)
              ->execute();
      
          // error_log("campaigns " . print_r($campaigns, TRUE));
      } elseif ($campaignId) {
          $campaigns = Campaign::get(TRUE)
              ->addSelect('id', 'Additional_Details.Campaign_Institution_PoC')
              ->addWhere('id', '=', $campaignId)
              ->execute();
      }
      elseif ($organizationId) {
        $relationships = Relationship::get(FALSE)
        ->addWhere('contact_id_a', '=', $organizationId)
        ->addWhere('relationship_type_id:name', '=', 'Institution POC of')
        ->execute();

    }
      
      // Extract Branch_Wise_POCs emails from campaigns.
      $emails = [];
      $phone = [];
      $contactNames = [];
      if (!empty($coBrandingCampaigns)) {
          if (isset($coBrandingCampaigns[0]['Additional_Details.Branch_Wise_POCs'])) {
              $branchWisePOCs = $coBrandingCampaigns[0]['Additional_Details.Branch_Wise_POCs'] ?? [];
              foreach ($branchWisePOCs as $contactId) {
                  $contact = Contact::get(TRUE)
                      ->addSelect('email_primary.email', 'phone_primary.phone', 'display_name')
                      ->addWhere('id', '=', $contactId)
                      ->execute()
                      ->first();
      
                  if (!empty($contact['email_primary.email'])) {
                      $emails[] = $contact['email_primary.email'];
                      $Phone[$contact['email_primary.email']] = $contact['phone_primary.phone'];
                      $contactNames[$contact['email_primary.email']] = $contact['display_name'];
                  }
              }
          }
        }
          // If Branch_Wise_POCs are empty, use Campaign_Institution_PoC as fallback
          if (empty($coBrandingCampaigns) && !empty($campaigns[0]['Additional_Details.Campaign_Institution_PoC'])) {
            $campaignInstitutionPocId = $campaigns[0]['Additional_Details.Campaign_Institution_PoC'];
              $contact = Contact::get(TRUE)
                  ->addSelect('email_primary.email', 'phone_primary.phone', 'display_name')
                  ->addWhere('id', '=', $campaignInstitutionPocId)
                  ->execute()
                  ->first();
  
              if (!empty($contact['email_primary.email'])) {
                  $emails[] = $contact['email_primary.email'];
                  $Phone[$contact['email_primary.email']] = $contact['phone_primary.phone'];
                  $contactNames[$contact['email_primary.email']] = $contact['display_name'];
              }
          }

          if (empty($coBrandingCampaigns) && empty($campaigns)) {
            $institutionPocId = $relationships[0]['contact_id_b'];
            error_log("institutionPocId " . print_r($institutionPocId, TRUE));
              $contact = Contact::get(TRUE)
                  ->addSelect('email_primary.email', 'phone_primary.phone', 'display_name')
                  ->addWhere('id', '=', $institutionPocId)
                  ->execute()
                  ->first();
  
              if (!empty($contact['email_primary.email'])) {
                  $emails[] = $contact['email_primary.email'];
                  $Phone[$contact['email_primary.email']] = $contact['phone_primary.phone'];
                  $contactNames[$contact['email_primary.email']] = $contact['display_name'];
              }
          }
        
          error_log("phone " . print_r($phone, TRUE));
      // Remove duplicates from the email list.
      $emails = array_unique($emails);
      error_log("emails " . print_r($emails, TRUE));
      // Send the email with the contribution receipt attached.
      self::sendMaterialContributionEmails($emails, $contactNames, $phone, $description, $deliveredBy, $deliveredByContact);
  }
  
  /**
   * Send emails with the contribution receipt attached.
   */
  public static function sendMaterialContributionEmails(array $recipientEmails, array $contactNames, array $phone, $description, $deliveredBy, $deliveredByContact)
  {
      // Loop through each recipient to send personalized emails
      foreach ($recipientEmails as $email) {
          $contactName = $contactNames[$email] ?? 'Contributor';
          $contactPhone = $phone[$email] ?? 'Contributor';
          // Set up the email subject and body
          $emailSubject = 'Material Contribution Receipt';
          $emailBody = self::generateEmailBody($contactName);
  
          // Generate the HTML content for the receipt
          $html = self::generateContributionReceiptHtml($email, $contactPhone, $description, $contactName, $deliveredBy, $deliveredByContact);
  
          // Set the file name for the PDF
          $fileName = 'institution_material_contribution.pdf';
  
          // Log file name for debugging
          error_log("File Name: $fileName");
  
          // Add the attachment to the email parameters
          $attachments = [\CRM_Utils_Mail::appendPDF($fileName, $html)];
  
          // Prepare email parameters
          $params = self::prepareEmailParams($emailSubject, $emailBody, $attachments, $email);
  
          // Log the parameters for debugging
          error_log("Email Parameters: " . print_r($params, TRUE));
  
          // Send the email
          $sendStatus = \CRM_Utils_Mail::send($params);
  
          // Log send status for debugging
          error_log("Email Send Status: " . print_r($sendStatus, TRUE));
      }
  }
  
  private static function generateEmailBody(string $contactName)
  {
      return "
      <html>
          <head>
              <title>Thank You for Your Contribution</title>
          </head>
          <body>
              <p>Hello {$contactName},</p>
              <p>Thank you for contributing material to Goonj.</p>
              <p>This contribution is a step towards sustainability and serves as a reminder to 'Goonj it'.</p>
              <p>The contributions are not merely recycled; they actively participate in creating a tangible impact on the lives of many at the grassroots level and enable communities to solve their own issues on water access, education, roads, menstrual hygiene, disaster relief and rehabilitation.</p>
              <p>With Material, Money Matters for sorting, packing, transportation to implementation. To contribute, click on this link - <a href='https://goonj.org/donate'>https://goonj.org/donate</a>. All financial contributions are tax exempted u/s 80G of IT Act.</p>
              <p>For more details on our work, please visit <a href='https://www.goonj.org'>www.goonj.org</a>.</p>
              <p>Please find attached your material contribution receipt.</p>
              <p>Regards,<br>Team Goonj</p>
          </body>
      </html>
      ";
  }
  
  private static function prepareEmailParams(string $emailSubject, string $emailBody, array $attachments, string $recipientEmail)
  {
      $from = HelperService::getDefaultFromEmail();
  
      return [
          'subject' => $emailSubject,
          'from' => $from,
          'toEmail' => $recipientEmail,
          'html' => $emailBody,
          'cc' => 'crm@goonj.org',
          'attachments' => $attachments,
      ];
  }
  
   




  /**
   * Generate the HTML for the PDF from the activity data.
   *
   * @param array $activity
   *   The activity data.
   *
   * @return string
   *   The generated HTML.
   */
  private static function generateContributionReceiptHtml($emails , $contactPhone, $description, $contactName, $deliveredBy, $deliveredByContact) {
    // $activityDate = date("F j, Y", strtotime($activity['activity_date_time']));

    $baseDir = plugin_dir_path(__FILE__) . '../../../themes/goonj-crm/';
    // $deliveredBy = empty($activity['Material_Contribution.Delivered_By']) ? $activity['contact.display_name'] : $activity['Material_Contribution.Delivered_By'];

    // $deliveredByContact = empty($activity['Material_Contribution.Delivered_By_Contact']) ? $phone : $activity['Material_Contribution.Delivered_By_Contact'];

    $paths = [
      'logo' => $baseDir . 'images/goonj-logo.png',
      'qrCode' => $baseDir . 'images/qr-code.png',
      'callIcon' => $baseDir . 'Icon/call.png',
      'domainIcon' => $baseDir . 'Icon/domain.png',
      'emailIcon' => $baseDir . 'Icon/email.png',
      'facebookIcon' => $baseDir . 'Icon/facebook.webp',
      'instagramIcon' => $baseDir . 'Icon/instagram.png',
      'twitterIcon' => $baseDir . 'Icon/twitter.webp',
      'youtubeIcon' => $baseDir . 'Icon/youtube.webp',
    ];

    $imageData = array_map(fn ($path) => base64_encode(file_get_contents($path)), $paths);

    $html = <<<HTML
    <html>
      <body style="font-family: Arial, sans-serif;">
        <div style="text-align: center; margin-bottom: 16px;">
          <img src="data:image/png;base64,{$imageData['logo']}" alt="Goonj Logo" style="width: 95px; height: 80px;">
        </div>
        
        <div style="width: 100%; font-size: 14px;">
          <div style="float: left; text-align: left;">
            <!-- Material Acknowledgment# {$activity['id']} -->
          </div>
          <div style="float: right; text-align: right;">
            Goonj, C-544, Pocket C, Sarita Vihar, Delhi, India
          </div>
        </div>
        <br><br>
        <div style="font-weight: bold; font-style: italic; margin-top: 6px; margin-bottom: 6px;">
          "We appreciate your contribution of pre-used/new material. Goonj makes sure that the material reaches people with dignity and care."
        </div>
        <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
          <style>
            .table-header {
              text-align: left;
              font-weight: bold;
            }
          </style>
          <!-- Table rows for each item -->
          <tr>
            <td class="table-header">Description of Material *</td>
            <td style="text-align: center;">{$description}</td>
          </tr>
          <tr>
            <td class="table-header">Received On</td>
            <!-- <td style="text-align: center;">{$activityDate}</td> -->
          </tr>
          <tr>
            <td class="table-header">From</td>
            <td style="text-align: center;">{$contactName}</td>
          </tr>
          <tr>
            <td class="table-header">Email</td>
            <td style="text-align: center;">{$email}</td>
          </tr>
          <tr>
            <td class="table-header">Phone</td>
            <td style="text-align: center;">{$contactPhone}</td>
          </tr>
          <tr>
            <td class="table-header">Delivered by (Name & contact no.)</td>
            <td style="text-align: center;">
            {$deliveredBy}<br>
            {$deliveredByContact}
          </td>
        </tr>

        </table>
        <div style="text-align: right; font-size: 14px;">
          Team Goonj
        </div>
        <div style="width: 100%; margin-top: 16px;">
        <div style="float: left; width: 60%; font-size: 14px;">
        <p>Join us, by encouraging your friends, relatives, colleagues, and neighbours to join the journey as all of us have a lot to give.</p>
        <p style="margin-top: 8px;">
        <strong>With Material Money Matters</strong> Your monetary contribution is needed too for sorting, packing, transportation to implementation. (Financial contributions are tax-exempted u/s 80G of IT Act)
      </p>
      <p style="margin-top: 10px; font-size: 12px; float: left">* Received material has 'No Commercial Value' for Goonj.</p>
    </div>
    <div style="float: right; width: 40%; text-align: right; font-size: 12px; font-style: italic;">
    <p>To contribute, please scan the code.</p>
    <img src="data:image/png;base64,{$imageData['qrCode']}" alt="QR Code" style="width: 80px; height: 70px; margin-top: 2px"></div>
        </div>
        <div style="clear: both; margin-top: 20px;"></div>
        <div style="width: 100%; margin-top: 15px; background-color: #f2f2f2; padding: 16px; font-weight: 300; color: #000000">
          <div style="font-size: 14px; margin-bottom: 20px;">
            <div style="position: relative; height: 24px;">
              <div style="font-size: 14px; float: left; color:">
                Goonj, C-544, 1st Floor, C-Pocket, Sarita Vihar,<br>
                New Delhi-110076
              </div>
              <div style="font-size: 14px; float: right;">
                <img src="data:image/png;base64,{$imageData['callIcon']}" alt="Phone" style="width: 16px; height: 16px; margin-right: 5px;">
                011-26972351/41401216
              </div>
            </div>
          </div>
    
          <div style="text-align: center; width: 100%; font-size: 14px; margin-bottom: 20px;">
              <div style="font-size: 14px;">
                <img src="data:image/png;base64,{$imageData['emailIcon']}" alt="Email" style="width: 16px; height: 16px; display: inline;">
                <span style="display: inline; margin-left: 0;">mail@goonj.org</span>
                <img src="data:image/png;base64,{$imageData['domainIcon']}" alt="Website" style="width: 16px; height: 16px; margin-right: 5px;">
                <span style="display: inline; margin-left: 0;">www.goonj.org</span>
              </div>
          </div>
    
          <!-- Social Media Icons -->
          <div style="text-align: center; width: 100%; margin-top: 28px;">
            <a href="https://www.facebook.com/goonj.org" target="_blank"><img src="data:image/webp;base64,{$imageData['facebookIcon']}" alt="Facebook" style="width: 24px; height: 24px; margin-right: 10px;"></a>
            <a href="https://www.instagram.com/goonj/" target="_blank"><img src="data:image/webp;base64,{$imageData['instagramIcon']}" alt="Instagram" style="width: 24px; height: 24px; margin-right: 10px;"></a>
            <a href="https://x.com/goonj" target="_blank"><img src="data:image/webp;base64,{$imageData['twitterIcon']}" alt="Twitter" style="width: 24px; height: 24px; margin-right: 10px;"></a>
            <a href="https://www.youtube.com/channel/UCCq8iYlmjT7rrgPI1VHzIHg" target="_blank"><img src="data:image/webp;base64,{$imageData['youtubeIcon']}" alt="YouTube" style="width: 24px; height: 24px; margin-right: 10px;"></a>
          </div>
        </div>
        <p style="margin-bottom: 2px; text-align: center; font-size: 12px;">* This is a computer generated receipt, signature is not required.</p>
      </body>
    </html>
    HTML;

    return $html;
  }

}
