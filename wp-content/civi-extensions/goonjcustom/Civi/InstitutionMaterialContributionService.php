<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\Organization;
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
    if (!$data) {
      return;
    }

    $activityData = $data['Activity1'][0]['fields'] ?? [];
    $activityDate = $activityData['Institution_Material_Contribution.Contribution_Date'];
    $campaignId = $activityData['campaign_id'] ?? NULL;
    $description = $activityData['Institution_Material_Contribution.Description_of_Material_No_of_Bags_Material_'] ?? '';
    $deliveredBy = $activityData['Institution_Material_Contribution.Delivered_By_Name'] ?? '';
    $deliveredByContact = $activityData['Institution_Material_Contribution.Delivered_By_Contact'] ?? '';
    $organizationId = $activityData['source_contact_id'];
    $institutionPOCId = $activityData['Institution_Material_Contribution.Institution_POC'];

    $organizations = Organization::get(FALSE)
      ->addSelect('address_primary.street_address', 'display_name')
      ->addWhere('id', '=', $organizationId)
      ->execute()->first();

    $organizationName = $organizations['display_name'] ?? '';
    $organizationAddress = $organizations['address_primary.street_address'] ?? '';

    $activities = Activity::get(FALSE)
      ->addSelect('*')
      ->addJoin('Organization AS organization', 'LEFT')
      ->addWhere('activity_type_id:name', '=', 'Institution Material Contribution')
      ->addWhere('organization.id', '=', $organizationId)
      ->addOrderBy('created_date', 'DESC')
      ->setLimit(1)
      ->execute();

    $contribution = $activities->first() ?? [];

    // Determine target contact with fallback logic.
    if (!empty($institutionPOCId)) {
      $targetContactId = $institutionPOCId;
    }
    else {
      $initiatorId = NULL;

      // First, check for Primary Institution POC relationship.
      $primaryRelationships = Relationship::get(FALSE)
        ->addWhere('contact_id_a', '=', $organizationId)
        ->addWhere('relationship_type_id:name', '=', 'Primary Institution POC of')
        ->addWhere('is_active', '=', TRUE)
        ->execute();

      if ($primaryRelationships) {
        $firstPrimaryRelationship = $primaryRelationships->first();
        $initiatorId = $firstPrimaryRelationship['contact_id_b'] ?? NULL;
      }

      // If Primary not found, check for Institution POC of relationship.
      if (!$initiatorId) {
        $pocRelationships = Relationship::get(FALSE)
          ->addWhere('contact_id_a', '=', $organizationId)
          ->addWhere('relationship_type_id:name', '=', 'Institution POC of')
          ->addWhere('is_active', '=', TRUE)
          ->execute();

        if ($pocRelationships) {
          $firstPocRelationship = $pocRelationships->first();
          $initiatorId = $firstPocRelationship['contact_id_b'] ?? NULL;
        }
      }

      $targetContactId = $initiatorId ?? $organizationId;
    }

    if (!empty($targetContactId)) {
      self::sendInstitutionMaterialContributionEmails(
            $targetContactId,
            $organizationName,
            $organizationAddress,
            $contribution,
            $description,
            $deliveredBy,
            $deliveredByContact,
            $activityDate
        );
    }
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

    return (!empty($contact)) ? [
      'email' => $contact['email_primary.email'] ?? '',
      'phone' => $contact['phone_primary.phone'] ?? '',
      'name' => $contact['display_name'] ?? '',
    ] : NULL;
  }

  /**
   *
   */
  private static function sendInstitutionMaterialContributionEmails(string $institutionPOCId, string $organizationName, string $organizationAddress, array $contribution, string $description, string $deliveredBy, string $deliveredByContact, string $activityDate) {
    $contact = self::getContactDetails($institutionPOCId);

    if (!$contact || empty($contact['email'])) {
      return;
    }
    $email = $contact['email'];
    $name = $contact['name'];
    $phone = $contact['phone'];

    $subject = 'Acknowledgement for your material contribution to Goonj';
    $body = self::generateEmailBody($name);
    $html = self::generateContributionReceiptHtml($organizationName, $organizationAddress, $contribution, $email, $phone, $description, $name, $deliveredBy, $deliveredByContact, $activityDate);
    $attachments = [\CRM_Utils_Mail::appendPDF('institution_material_contribution.pdf', $html)];
    $params = self::prepareEmailParams($subject, $body, $attachments, $email);

    \CRM_Utils_Mail::send($params);
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
  private static function generateContributionReceiptHtml($organizationName, $organizationAddress, $contribution, $email, $contactPhone, $description, $contactName, $deliveredBy, $deliveredByContact, $activityDate) {

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

    $date = new \DateTime($activityDate);

    $formattedDate = $date->format('F j, Y');

    $html = <<<HTML
    <html>
      <body style="font-family: Arial, sans-serif;">
        <div style="text-align: center; margin-bottom: 16px;">
        <img alt="Goonj Logo" src="https://goonj-crm.staging.coloredcow.com/wp-content/uploads/2024/07/Goonj-logo-10June20-300x193.png" style="width: 150px; height: auto;" />
        </div>      
        <div style="width: 100%; font-size: 11px;">
          <div style="float: left; text-align: left; margin-bottom: 2px;">
            Material Acknowledgment# {$contribution['id']}
          </div>
        </div>
        <br>
        <div style="font-weight: bold; font-style: italic; margin-bottom: 6px; font-size: 12px;">
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
              <td class="table-cell">{$formattedDate}</td>
            </tr>
            <tr>
              <td class="table-header">Institution Name</td>
              <td class="table-cell">{$organizationName}</td>
            </tr>
            <tr>
              <td class="table-header">Address</td>
              <td class="table-cell">{$organizationAddress}</td>
            </tr>
            <!-- <tr>
              <td class="table-header">From</td>
              <td class="table-cell">{$contactName}</td>
            </tr> -->
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
          <div style="font-size: 10px; margin-bottom: 20px;">
            <div style="position: relative; height: 24px;">
              <div style="font-size: 12px; float: left; color:">
                Goonj, C-544, 1st Floor, C-Pocket, Sarita Vihar,<br>
                New Delhi-110076
              </div>
              <div style="font-size: 12px; float: right;">
                <img src="data:image/png;base64,{$imageData['callIcon']}" alt="Phone" style="width: 16px; height: 16px; margin-right: 5px;">
                011-41401216
              </div>
            </div>
          </div>
    
          <div style="text-align: center; width: 100%; font-size: 10px; margin-bottom: 18px;">
              <div style="font-size: 10px;">
                <img src="data:image/png;base64,{$imageData['emailIcon']}" alt="Email" style="width: 16px; height: 16px; display: inline;">
                <span style="display: inline; margin-left: 0;">mail@goonj.org</span>
                <img src="data:image/png;base64,{$imageData['domainIcon']}" alt="Website" style="width: 16px; height: 16px; margin-right: 5px;">
                <span style="display: inline; margin-left: 0;">www.goonj.org</span>
              </div>
          </div>
    
          <!-- Social Media Icons -->
          <div style="text-align: center; width: 100%; margin-top: 26px;">
            <a href="https://www.facebook.com/goonj.org" target="_blank"><img src="data:image/webp;base64,{$imageData['facebookIcon']}" alt="Facebook" style="width: 24px; height: 24px; margin-right: 10px;"></a>
            <a href="https://www.instagram.com/goonj/" target="_blank"><img src="data:image/webp;base64,{$imageData['instagramIcon']}" alt="Instagram" style="width: 24px; height: 24px; margin-right: 10px;"></a>
            <a href="https://x.com/goonj" target="_blank"><img src="data:image/webp;base64,{$imageData['twitterIcon']}" alt="Twitter" style="width: 24px; height: 24px; margin-right: 10px;"></a>
            <a href="https://www.youtube.com/channel/UCCq8iYlmjT7rrgPI1VHzIHg" target="_blank"><img src="data:image/webp;base64,{$imageData['youtubeIcon']}" alt="YouTube" style="width: 24px; height: 24px; margin-right: 10px;"></a>
          </div>
        </div>
        <p style="margin-bottom: 2px; text-align: center; font-size: 10px;">* This is a computer generated receipt, signature is not required.</p>
      </body>
    </html>
    HTML;

    return $html;
  }

}
