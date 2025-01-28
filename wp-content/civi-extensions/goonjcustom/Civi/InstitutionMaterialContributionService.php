<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Api4\Campaign;
use Civi\Api4\Contact;
use Civi\Api4\Relationship;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class InstitutionMaterialContributionService extends AutoSubscriber {

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => 'processInstitutionMaterialContributions',
    ];
  }

  /**
   *
   */
  public static function processInstitutionMaterialContributions(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($objectName !== 'AfformSubmission' || $objectRef->afform_name !== 'afformAddInstitutionMaterialContribution') {
      return;
    }
    $data = json_decode($objectRef->data, TRUE);
    // error_log("data: " . print_r($data, TRUE));.
    if (!$data) {
      return;
    }
    $activityData = $data['Activity1'][0]['fields'] ?? [];
    $activityDate = $activityData['Institution_Material_Contribution.Contribution_Date'];
    $campaignId = $activityData['campaign_id'] ?? NULL;
    $description = $activityData['Institution_Material_Contribution.Description_of_Material_No_of_Bags_Material_'] ?? '';
    $deliveredBy = $activityData['Institution_Material_Contribution.Delivered_By_Name'] ?? '';
    $deliveredByContact = $activityData['Institution_Material_Contribution.Delivered_By_Contact'] ?? '';
    $organizationId = $data['Organization1'][0]['fields']['id'] ?? NULL;
    $contacts = self::fetchContributionContacts($campaignId, $organizationId);

    $activities = Activity::get(FALSE)
      ->addSelect('*')
      ->addJoin('Organization AS organization', 'LEFT')
      ->addWhere('activity_type_id:name', '=', 'Institution Material Contribution')
      ->addWhere('organization.id', '=', 17383)
      ->addOrderBy('created_date', 'DESC')
      ->setLimit(1)
      ->execute();

    $contribution = $activities->first();

    if (!empty($contacts)) {
      self::sendInstitutionMaterialContributionEmails($contribution, $contacts, $description, $deliveredBy, $deliveredByContact, $activityDate);
    }
  }

  /**
   *
   */
  private static function fetchContributionContacts($campaignId, $organizationId) {
    $contacts = [];

    // Case 1: Campaign Institution POC.
    if ($campaignId) {
      $contacts = self::getCampaignInstitutionPOC($campaignId);
    }
    // Case 2: Institution POC.
    elseif ($organizationId) {
      $contacts = self::getInstitutionRelationships($organizationId);
    }

    return $contacts;
  }

  /**
   *
   */
  private static function getCampaignInstitutionPOC($campaignId) {
    $contacts = [];
    $campaign = Campaign::get(TRUE)
      ->addSelect('Additional_Details.Campaign_Institution_PoC')
      ->addWhere('id', '=', $campaignId)
      ->execute()
      ->first();

    if (!empty($campaign['Additional_Details.Campaign_Institution_PoC'])) {
      $contact = self::getContactDetails($campaign['Additional_Details.Campaign_Institution_PoC']);
      if ($contact) {
        $contacts[$contact['email']] = $contact;
      }
    }
    return $contacts;
  }

  /**
   *
   */
  private static function getInstitutionRelationships($organizationId) {
    $contacts = [];
    $relationship = Relationship::get(FALSE)
      ->addWhere('contact_id_a', '=', $organizationId)
      ->addWhere('relationship_type_id:name', '=', 'Institution POC of')
      ->execute()
      ->first();

    if (!empty($relationship['contact_id_b'])) {
      $contact = self::getContactDetails($relationship['contact_id_b']);
      if ($contact) {
        $contacts[$contact['email']] = $contact;
      }
    }
    return $contacts;
  }

  /**
   *
   */
  private static function getContactDetails($contactId) {
    $contact = Contact::get(TRUE)
      ->addSelect('email_primary.email', 'phone_primary.phone', 'display_name')
      ->addWhere('id', '=', $contactId)
      ->execute()
      ->first();

    return (!empty($contact['email_primary.email'])) ? [
      'email' => $contact['email_primary.email'],
      'phone' => $contact['phone_primary.phone'] ?? '',
      'name' => $contact['display_name'] ?? '',
    ] : NULL;
  }

  /**
   *
   */
  private static function sendInstitutionMaterialContributionEmails(array $contribution, array $contacts, string $description, string $deliveredBy, string $deliveredByContact, string $activityDate) {
    foreach ($contacts as $contact) {
      $email = $contact['email'];
      $name = $contact['name'];
      $phone = $contact['phone'];

      $subject = 'Acknowledgement for your material contribution to Goonj';
      $body = self::generateEmailBody($name);
      $html = self::generateContributionReceiptHtml($contribution, $email, $phone, $description, $name, $deliveredBy, $deliveredByContact, $activityDate);
      $attachments = [\CRM_Utils_Mail::appendPDF('institution_material_contribution.pdf', $html)];
      $params = self::prepareEmailParams($subject, $body, $attachments, $email);

      \CRM_Utils_Mail::send($params);
    }
  }

  /**
   *
   */
  private static function generateEmailBody(string $contactName) {
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

  /**
   *
   */
  private static function prepareEmailParams(string $emailSubject, string $emailBody, array $attachments, string $recipientEmail) {
    $from = HelperService::getDefaultFromEmail();

    return [
      'subject' => $emailSubject,
      'from' => $from,
      'toEmail' => $recipientEmail,
      'html' => $emailBody,
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
  private static function generateContributionReceiptHtml($contribution, $email, $contactPhone, $description, $contactName, $deliveredBy, $deliveredByContact, $activityDate) {

    $baseDir = plugin_dir_path(__FILE__) . '../../../themes/goonj-crm/';

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
        
        <div style="width: 100%; font-size: 12px;">
          <div style="float: left; text-align: left;">
            Material Acknowledgment# {$contribution['id']}
          </div>
        </div>
        <br><br>
        <div style="font-weight: bold; font-style: italic; margin-top: 6px; margin-bottom: 6px; font-size: 14px;">
          "We appreciate your contribution of pre-used/new material. Goonj makes sure that the material reaches people with dignity and care."
        </div>
        <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
          <style>
            .table-header {
              text-align: left;
              font-weight: bold;
              font-size: 14px;
            }
            .table-cell {
              font-size: 14px;
              text-align: center;
            }
          </style>
          <!-- Table rows for each item -->
            <tr>
              <td class="table-header">Description of Material</td>
              <td class="table-cell">{$description}</td>
            </tr>
            <tr>
              <td class="table-header">Received On</td>
              <td class="table-cell">{$activityDate}</td>
            </tr>
            <tr>
              <td class="table-header">From</td>
              <td class="table-cell">{$contactName}</td>
            </tr>
            <tr>
              <td class="table-header">Email</td>
              <td class="table-cell">{$email}</td>
            </tr>
            <tr>
              <td class="table-header">Phone</td>
              <td class="table-cell">{$contactPhone}</td>
            </tr>
            <tr>
              <td class="table-header">Delivered by (Name & contact no.)</td>
              <td class="table-cell">
                {$deliveredBy}<br>
                {$deliveredByContact}
              </td>
            </tr>
          </table>
        <div style="width: 100%; margin-top: 16px;">
        <div style="float: left; width: 60%; font-size: 12px;">
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
          <div style="font-size: 12px; margin-bottom: 20px;">
            <div style="position: relative; height: 24px;">
              <div style="font-size: 12px; float: left; color:">
                Goonj, C-544, 1st Floor, C-Pocket, Sarita Vihar,<br>
                New Delhi-110076
              </div>
              <div style="font-size: 12px; float: right;">
                <img src="data:image/png;base64,{$imageData['callIcon']}" alt="Phone" style="width: 16px; height: 16px; margin-right: 5px;">
                011-26972351/41401216
              </div>
            </div>
          </div>
    
          <div style="text-align: center; width: 100%; font-size: 14px; margin-bottom: 20px;">
              <div style="font-size: 12px;">
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
