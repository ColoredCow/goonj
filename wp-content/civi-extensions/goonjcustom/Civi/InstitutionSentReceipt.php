<?php

namespace Civi;

use Civi\Api4\Campaign;
use Civi\Api4\Contact;
use Civi\Api4\Relationship;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class InstitutionSentReceipt extends AutoSubscriber {

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
    if ($objectName !== 'AfformSubmission' || $objectRef->afform_name !== 'afformInstitutionAcknowledgementForm') {
        return;
    }

    $data = json_decode($objectRef->data, TRUE);
    if (!$data) {
        return;
    }

    $result = [];

    if (isset($data['Eck_Collection_Source_Vehicle_Dispatch1'][0]['fields'])) {
        $fields = $data['Eck_Collection_Source_Vehicle_Dispatch1'][0]['fields'];
        
        // Store extracted details into the result array
        $result['No_of_bags_received_at_PU_Office'] = $fields['Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office'] ?? null;
        $result['Verified_By'] = $fields['Acknowledgement_For_Logistics.Verified_By'] ?? null;
        $result['Remark'] = $fields['Acknowledgement_For_Logistics.Remark'] ?? null;
        $result['Filled_by'] = $fields['Acknowledgement_For_Logistics.Filled_by'] ?? null;
    }

    // Extract 'Institution_POC' from 'Eck_Collection_Camp1'
    if (isset($data['Eck_Collection_Camp1'][0]['fields'])) {
        $campFields = $data['Eck_Collection_Camp1'][0]['fields'];
        $result['Institution_POC'] = $campFields['Institution_Collection_Camp_Intent.Institution_POC'] ?? null;
    }

    // If there are any contacts, send acknowledgment emails
    if (!empty($result['Verified_By']) && !empty($result['Institution_POC']) && !empty($result['Filled_by'])) {
        self::sendInstitutionMaterialContributionEmails(
            $result['No_of_bags_received_at_PU_Office'],
            $result['Verified_By'],
            $result['Filled_by'],
            $result['Remark'],
            $result['Institution_POC']
        );
    }

    // Return the result array with extracted data
    return $result;
}

private static function sendInstitutionMaterialContributionEmails(
    $noOfBagsReceived, 
    $verifiedById, 
    $filledById, 
    $remark, 
    $institutionPocId
) {
    // Fetch the Institution_POC details
    $institutionDetails = Contact::get(FALSE)
        ->addSelect('display_name', 'email.email', 'phone.phone')
        ->addJoin('Email AS email', 'LEFT')
        ->addJoin('Phone AS phone', 'LEFT')
        ->addWhere('id', '=', $institutionPocId)
        ->execute()->single();
    
    // Extract the Institution_POC email, phone, and name
    $institutionEmail = $institutionDetails['email.email'];
    $institutionPhone = $institutionDetails['phone.phone'];
    $institutionName = $institutionDetails['display_name'];

    // Fetch the 'Verified_By' details
    $verifiedByDetails = Contact::get(FALSE)
        ->addSelect('display_name', 'email.email', 'phone.phone')
        ->addJoin('Email AS email', 'LEFT')
        ->addJoin('Phone AS phone', 'LEFT')
        ->addWhere('id', '=', $verifiedById)
        ->execute()->single();

    // Extract the 'Verified_By' details
    $verifiedByEmail = $verifiedByDetails['email'];
    $verifiedByPhone = $verifiedByDetails['phone'];
    $verifiedByName = $verifiedByDetails['display_name'];

    // Fetch the 'Filled_by' details
    $filledByDetails = Contact::get(FALSE)
        ->addSelect('display_name', 'email.email', 'phone.phone')
        ->addJoin('Email AS email', 'LEFT')
        ->addJoin('Phone AS phone', 'LEFT')
        ->addWhere('id', '=', $filledById)
        ->execute()->single();

    // Extract the 'Filled_by' details
    $filledByEmail = $filledByDetails['email'];
    $filledByPhone = $filledByDetails['phone'];
    $filledByName = $filledByDetails['display_name'];

    // Prepare the email subject and body
    $subject = 'Acknowledgement for your material contribution to Goonj';
    error_log("institutionName: " . print_r($institutionName, TRUE));
    error_log("institutionPhone: " . print_r($institutionPhone, TRUE));
    error_log("institutionEmail: " . print_r($institutionEmail, TRUE));
    // Generate the email body
    $body = self::generateEmailBody(
        $institutionName, 
        $institutionPhone, 
        $institutionEmail
    );

   // Generate the HTML for the contribution receipt
    $html = self::generateContributionReceiptHtml(
        $noOfBagsReceived,
        $verifiedByName,
        $filledByName,
    );

    $attachments = [\CRM_Utils_Mail::appendPDF('institution_material_contribution.pdf', $html)];

    // Prepare the email parameters and send the email
    $params = self::prepareEmailParams($subject, $body, $attachments, $institutionEmail);
    \CRM_Utils_Mail::send($params);
}


/**
 * Generate the email body for the acknowledgment.
 */
private static function generateEmailBody(string $institutionName, string $institutionPhone, string $institutionEmail) {
    return "
      <html>
          <head>
              <title>Material Acknowledgment Receipt</title>
          </head>
          <body>
              <p>Dear {$institutionName},</p>
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
                  to reach out to us at <strong>{$institutionEmail}</strong> / <strong>{$institutionPhone}</strong>.
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
  private static function prepareEmailParams(string $emailSubject, string $emailBody, array $attachments, string $recipientEmail) {
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
  private static function generateContributionReceiptHtml($noOfBagsReceived, $verifiedByName, $filledByName) {

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
          <!-- <tr>
            <td class="table-header">Phone</td>
            <td style="text-align: center;">{$contactPhone}</td>
          </tr>
          <tr>
            <td class="table-header">Delivered by (Name & contact no.)</td>
            <td style="text-align: center;">
            {$deliveredBy}<br>
            {$deliveredByContact}
          </td>
        </tr> -->

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
