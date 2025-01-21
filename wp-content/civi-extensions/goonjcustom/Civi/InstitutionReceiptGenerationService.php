<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class InstitutionReceiptGenerationService extends AutoSubscriber {

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => 'sendAcknowledgedDataToInstitutionPOC',
    ];
  }

  /**
   *
   */
  public static function sendAcknowledgedDataToInstitutionPOC(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($objectName !== 'AfformSubmission' || $objectRef->afform_name !== 'afformInstitutionAcknowledgementForm') {
      return;
    }

    $data = json_decode($objectRef->data, TRUE);
    if (!$data) {
      return;
    }

    $result = self::extractAcknowledgedData($data);

    self::notifyInstitutionPOC(
        $result['No_of_bags_received_at_PU_Office'] ?? NULL,
        $result['Verified_By'] ?? NULL,
        $result['Filled_by'] ?? NULL,
        $result['Remark'] ?? NULL,
        $result['Name_of_the_institution'] ?? NULL,
        $result['Institution_POC'] ?? NULL,
        $result['id'] ?? NULL
    );

    return $result;
  }

  /**
   *
   */
  private static function extractAcknowledgedData(array $data): array {
    $result = [];
    if (isset($data['Eck_Collection_Source_Vehicle_Dispatch1'][0]['fields'])) {
      $fields = $data['Eck_Collection_Source_Vehicle_Dispatch1'][0]['fields'];
      $result = array_merge($result, [
        'No_of_bags_received_at_PU_Office' => $fields['Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office'] ?? NULL,
        'Verified_By' => $fields['Acknowledgement_For_Logistics.Verified_By'] ?? NULL,
        'Remark' => $fields['Acknowledgement_For_Logistics.Remark'] ?? NULL,
        'Filled_by' => $fields['Acknowledgement_For_Logistics.Filled_by'] ?? NULL,
        'Name_of_the_institution' => $fields['Camp_Institution_Data.Name_of_the_institution'] ?? NULL,
        'Institution_POC' => $fields['Camp_Institution_Data.Email'] ?? NULL,
        'id' => $fields['Camp_Vehicle_Dispatch.Institution_Collection_Camp'] ?? NULL,
      ]);
    }

    return $result;
  }

  /**
   *
   */
  private static function fetchContactDetails($contactId): array {
    if (!$contactId) {
      return [];
    }

    return Contact::get(FALSE)
      ->addSelect('display_name', 'email.email', 'phone.phone')
      ->addJoin('Email AS email', 'LEFT')
      ->addJoin('Phone AS phone', 'LEFT')
      ->addWhere('id', '=', $contactId)
      ->execute()
      ->single();
  }

  /**
   *
   */
  private static function notifyInstitutionPOC(
    $noOfBagsReceived,
    $verifiedById,
    $filledById,
    $remark,
    $institutionName,
    $institutionEmail,
    $id,
  ) {
    // Fetch details for Institution POC, Verified By, and Filled By.
    $verifiedByDetails = self::fetchContactDetails($verifiedById);
    $filledByDetails = self::fetchContactDetails($filledById);

    $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Institution_collection_camp_Review.Coordinating_POC')
      ->addWhere('id', '=', $id)
      ->execute()->single();

    $coordinatingPOCId = $collectionCamp['Institution_collection_camp_Review.Coordinating_POC'];

    $goonjCoordinatorEmail = Contact::get(FALSE)
      ->addSelect('email.email', 'phone.phone')
      ->addJoin('Email AS email', 'LEFT')
      ->addJoin('Phone AS phone', 'LEFT')
      ->addWhere('id', '=', $coordinatingPOCId)
      ->execute()->single();

    $goonjCoordinatorEmail = $goonjCoordinatorEmail['email.email'];
    $goonjCoordinatorPhone = $goonjCoordinatorEmail['phone.phone'];

    // Generate the email body and receipt.
    $body = self::generateEmailBody(
          $institutionName,
          $goonjCoordinatorEmail,
          $goonjCoordinatorPhone,
      );

    $html = self::generateAcknowledgedReceiptHtml(
        $noOfBagsReceived,
        $verifiedByDetails['display_name'] ?? '',
        $filledByDetails['display_name'] ?? '',
        $remark,
        $institutionName
    );

    $attachments = [\CRM_Utils_Mail::appendPDF('receipt.pdf', $html)];
    // Prepare the email parameters and send the email.
    $params = self::prepareEmailParams(
        'Acknowledgement for your material contribution to Goonj',
        $body,
        $attachments,
        $institutionEmail ?? '',
        $goonjCoordinatorEmail
    );
    \CRM_Utils_Mail::send($params);
  }

  /**
   * Generate the email body for the acknowledgment.
   */
  private static function generateEmailBody(string $institutionPOCName, string $goonjCoordinatorEmail, string $goonjCoordinatorPhone) {
    return "
      <html>
          <head>
              <title>Material Acknowledgment Receipt</title>
          </head>
          <body>
              <p>Dear {$institutionPOCName},</p>
              <p>Greetings from Goonj!</p>
              <p>
                  We are pleased to acknowledge the receipt of materials dispatched from your collection camp drive. 
                  Your efforts and contribution are invaluable in supporting our mission to create meaningful change 
                  in underserved communities.
              </p>
              <p>
                  Attached, please find the Material Acknowledgment Receipt for your reference and records.
              </p>
              <p>
                  Your support strengthens our ability to reach those in need and implement impactful initiatives. 
                  If you have any questions regarding the acknowledgment or need further assistance, please feel free 
                  to reach out to us at <strong>$goonjCoordinatorEmail</strong> / <strong>$goonjCoordinatorPhone</strong>.
              </p>
              <p>
                  Thank you once again for partnering with us and making a difference!
              </p>
              <p>Warm Regards,<br>Team Goonj</p>
          </body>
      </html>
    ";
  }

  /**
   *
   */
  private static function prepareEmailParams(string $emailSubject, string $emailBody, array $attachments, string $recipientEmail, string $goonjCoordinatorEmail) {
    $from = HelperService::getDefaultFromEmail();

    return [
      'subject' => $emailSubject,
      'from' => $from,
      'toEmail' => $recipientEmail,
      'html' => $emailBody,
      'cc' => $goonjCoordinatorEmail,
      'attachments' => $attachments,
    ];
  }

  /**
   *
   */
  private static function generateAcknowledgedReceiptHtml($noOfBagsReceived, $verifiedByName, $filledByName, $remark, $institutionName) {

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
            <td class="table-header">No of Bags Received *</td>
            <td style="text-align: center;">{$noOfBagsReceived}</td>
          </tr>
          <tr>
            <td class="table-header">Verified By</td>
            <td style="text-align: center;">{$verifiedByName}</td>
          </tr>
          <tr>
            <td class="table-header">Filled By</td>
            <td style="text-align: center;">{$filledByName}</td>
          </tr>
          <tr>
            <td class="table-header">institutionName</td>
            <td style="text-align: center;">{$institutionName}</td>
          </tr>
          <!-- <tr>
            <td class="table-header">Delivered by (Name & contact no.)</td>
            <td style="text-align: center;">
            {$deliveredBy}<br>
            {$deliveredByContact}
          </td> -->
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
