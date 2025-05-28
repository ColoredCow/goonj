<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\QrCodeable;

/**
 *
 */
class InstitutionReceiptGenerationService extends AutoSubscriber {

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => [
        ['generateMaterialAcknowledgmentReceipt'],
        ['sendAcknowledgedDataToInstitutionPOC'],
      ],
    ];
  }

  /**
   * Generates a material acknowledgment receipt PDF when an Afform submission is created.
   */
  public static function generateMaterialAcknowledgmentReceipt(string $op, string $objectName, int $objectId, &$objectRef) {
    if (
      $op !== 'create' ||
      $objectName !== 'AfformSubmission' ||
      empty($objectRef->afform_name) ||
      !in_array($objectRef->afform_name, [
        'afformInstitutionDroppingCenterMaterialReceipt',
        'afformInstitutionCollectionCampMaterialReceipt',
      ], TRUE)
    ) {
      return;
    }

    $data = json_decode($objectRef->data, TRUE);
    $fields = $data['Eck_Collection_Source_Vehicle_Dispatch1'][0]['fields'] ?? [];
    $recordId = $fields['id'] ?? NULL;

    if (empty($recordId)) {
      return;
    }

    $entityIdFieldMap = [
      'Camp_Vehicle_Dispatch.Institution_Collection_Camp',
      'Camp_Vehicle_Dispatch.Institution_Dropping_Center',
    ];

    $entityId = NULL;
    foreach ($entityIdFieldMap as $fieldKey) {
      if (!empty($fields[$fieldKey])) {
        $entityId = $fields[$fieldKey];
        break;
      }
    }

    if (empty($entityId)) {
      return;
    }

    $record = self::getVehicleDispatchRecord($entityId, $recordId);
    if (empty($record)) {
      return;
    }

    $institutionName          = $record['Camp_Institution_Data.Name_of_the_institution'] ?? '';
    $institutionAddress       = $record['Camp_Institution_Data.Address'] ?? '';
    $institutionContactNumber = $record['Camp_Institution_Data.Contact_Number'] ?? '';
    $institutionEmail         = $record['Camp_Institution_Data.Email'] ?? '';
    $noOfBagsReceived         = $record['Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office'] ?? '';
    $contributionDate         = $record['Acknowledgement_For_Logistics.Contribution_Date'] ?? '';

    // Generate PDF.
    self::generatePdfForInstitution(
      $institutionName,
      $institutionAddress,
      $institutionContactNumber,
      $institutionEmail,
      $noOfBagsReceived,
      $contributionDate,
      $recordId
    );
  }

  /**
   * Fetches vehicle dispatch record for a given entity ID.
   */
  private static function getVehicleDispatchRecord($entityId, $recordId) {
    return EckEntity::get('Collection_Source_Vehicle_Dispatch', FALSE)
      ->addSelect(
        'Camp_Institution_Data.Name_of_the_institution',
        'Camp_Institution_Data.Address',
        'Camp_Institution_Data.Contact_Number',
        'Camp_Institution_Data.Email',
        'Camp_Vehicle_Dispatch.Institution_Collection_Camp',
        'Camp_Vehicle_Dispatch.Institution_Dropping_Center',
        'Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office',
        'Acknowledgement_For_Logistics.Contribution_Date'
      )
      ->addClause('OR', ['Camp_Vehicle_Dispatch.Institution_Collection_Camp', '=', $entityId], ['Camp_Vehicle_Dispatch.Institution_Dropping_Center', '=', $entityId])
      ->addWhere('id', '=', $recordId)
      ->execute()
      ->first();
  }

  /**
   * Generates a PDF receipt for an institution and attaches it to a custom field.
   */
  public static function generatePdfForInstitution(
    $institutionName,
    $institutionAddress,
    $institutionContactNumber,
    $institutionEmail,
    $noOfBagsReceived,
    $contributionDate,
    $recordId,
  ) {
    try {
      $html = self::generateAcknowledgedReceiptHtml(
        $institutionName,
        $institutionAddress,
        $institutionContactNumber,
        $institutionEmail,
        $noOfBagsReceived,
        $contributionDate
      );
      $pdfContent = \CRM_Utils_PDF_Utils::html2pdf($html, NULL, ['paper_size' => 'a4', 'orientation' => 'portrait']);
      $fileName = "acknowledgement_receipt.pdf";
      $tempFilePath = \CRM_Utils_File::tempnam($fileName);
      file_put_contents($tempFilePath, $pdfContent);

      return QrCodeable::savePdfAttachmentToCustomField(
        $recordId,
        $fileName,
        $tempFilePath,
        'Acknowledgement_For_Logistics',
        'Acknowledgement_Receipt'
      );
    }
    catch (\Exception $e) {
      \CRM_Core_Error::debug_log_message('PDF error: ' . $e->getMessage());
      return FALSE;
    }
  }

  /**
   * Sends acknowledgment data to the institution's point of contact via email.
   */
  public static function sendAcknowledgedDataToInstitutionPOC(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($objectName !== 'AfformSubmission' || !in_array($objectRef->afform_name, [
      'afformInstitutionAcknowledgementForm',
      'afformInstitutionCampAcknowledgementFormForLogistics',
      'afformInstitutionDroppingCenterAcknowledgementForm',
      'afformInstitutionDroppingCenterAcknowledgementFormForLogistics',
    ])) {
      return;
    }

    $data = json_decode($objectRef->data, TRUE);
    if (!$data) {
      return;
    }

    $result = self::extractAcknowledgedData($data);

    self::notifyInstitutionPOC(
      $result['Name_of_the_institution'] ?? NULL,
      $result['address'] ?? NULL,
      $result['Contact_Number'] ?? NULL,
      $result['Institution_POC'] ?? NULL,
      $result['collectionCampId'] ?? NULL,
      $result['droppingCenterId'] ?? NULL,
      $result['description_of_material'] ?? NULL,
      $result['contribution_date'] ?? NULL
    );

    return $result;
  }

  /**
   * Extracts acknowledgment data from the submission.
   */
  private static function extractAcknowledgedData(array $data): array {
    $result = [];
    if (isset($data['Eck_Collection_Source_Vehicle_Dispatch1'][0]['fields'])) {
      $fields = $data['Eck_Collection_Source_Vehicle_Dispatch1'][0]['fields'];
      $result = array_merge($result, [
        'Name_of_the_institution' => $fields['Camp_Institution_Data.Name_of_the_institution'],
        'address' => $fields['Camp_Institution_Data.Address'],
        'Contact_Number' => $fields['Camp_Institution_Data.Contact_Number'],
        'Institution_POC' => $fields['Camp_Institution_Data.Email'],
        'collectionCampId' => $fields['Camp_Vehicle_Dispatch.Institution_Collection_Camp'],
        'droppingCenterId' => $fields['Camp_Vehicle_Dispatch.Institution_Dropping_Center'],
        'description_of_material' => $fields['Acknowledgement_For_Logistics.No_of_bags_received_at_PU_Office'],
        'contribution_date' => $fields['Acknowledgement_For_Logistics.Contribution_Date'],
      ]);
    }
    return $result;
  }

  /**
   *
   */
  private static function notifyInstitutionPOC(
    $institutionName,
    $address,
    $contactNumber,
    $institutionEmail,
    $collectionCampId,
    $droppingCenterId,
    $descriptionOfMaterial,
    $contributionDate,
  ) {
    $coordinatingPOCId = NULL;

    if ($collectionCampId) {
      $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
        ->addSelect('Institution_collection_camp_Review.Coordinating_POC')
        ->addWhere('id', '=', $collectionCampId)
        ->execute()
        ->first();
      $coordinatingPOCId = $collectionCamp['Institution_collection_camp_Review.Coordinating_POC'] ?? NULL;
    }
    elseif ($droppingCenterId) {
      $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
        ->addSelect('Institution_Dropping_Center_Review.Coordinating_POC')
        ->addWhere('id', '=', $droppingCenterId)
        ->execute()
        ->first();
      $coordinatingPOCId = $collectionCamp['Institution_Dropping_Center_Review.Coordinating_POC'] ?? NULL;
    }

    if (!$coordinatingPOCId) {
      return;
    }

    $contact = Contact::get(FALSE)
      ->addSelect('email.email', 'phone.phone')
      ->addJoin('Email AS email', 'LEFT')
      ->addJoin('Phone AS phone', 'LEFT')
      ->addWhere('id', '=', $coordinatingPOCId)
      ->execute()->first();

    if (empty($contact)) {
      return;
    }

    $goonjCoordinatorEmail = $contact['email.email'];
    $goonjCoordinatorPhone = $contact['phone.phone'];

    $body = self::generateEmailBody($institutionName, $goonjCoordinatorEmail, $goonjCoordinatorPhone);
    $html = self::generateAcknowledgedReceiptHtml(
      $institutionName,
      $address,
      $contactNumber,
      $institutionEmail,
      $descriptionOfMaterial,
      $contributionDate
    );

    $attachments = [\CRM_Utils_Mail::appendPDF('receipt.pdf', $html)];
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
   * Generates the email body for the acknowledgment.
   */
  private static function generateEmailBody(?string $institutionPOCName, ?string $goonjCoordinatorEmail, ?string $goonjCoordinatorPhone): string {
    $institutionPOCName = $institutionPOCName ?? '';
    $goonjCoordinatorEmail = $goonjCoordinatorEmail ?? '';
    $goonjCoordinatorPhone = $goonjCoordinatorPhone ?? '';

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
   * Prepares email parameters for sending.
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
   * Generates the HTML content for the acknowledgment receipt PDF.
   */
  private static function generateAcknowledgedReceiptHtml($institutionName, $address, $contactNumber, $institutionEmail, $descriptionOfMaterial, $contributionDate) {
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
    $currentDate = date('F j, Y');
    $receivedOnDate = !empty($contributionDate) ? date("F j, Y", strtotime($contributionDate)) : $currentDate;

    $html = <<<HTML
    <html>
      <head>
        <title>Material Acknowledgment Receipt</title>
        <style>
          @page {
            margin: 15mm;
          }
          body, table, div, p, span {
            font-family: Arial, sans-serif;
          }
          .table-header {
            text-align: left;
            font-weight: bold;
          }
          .table-cell {
            font-size: 14px;
            text-align: center;
          }
        </style>
      </head>
      <body>
        <div style="text-align: center; margin-bottom: 16px;">
          <img alt="Goonj Logo" src="https://goonj-crm.staging.coloredcow.com/wp-content/uploads/2024/07/Goonj-logo-10June20-300x193.png" style="width: 150px; height: auto;" />
        </div>
        <div style="width: 100%; font-size: 11px;">
          <div style="float: left; text-align: left;">
            Material Acknowledgment#
          </div>
        </div>
        <br><br>
        <div style="font-weight: bold; font-style: italic; margin-top: 6px; margin-bottom: 6px; font-size: 14px;">
          "We appreciate your contribution of pre-used/new material. Goonj makes sure that the material reaches people with dignity and care."
        </div>
        <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
          <tr>
            <td class="table-header" style="font-weight: bold;">Description of Material</td>
            <td class="table-cell">{$descriptionOfMaterial}</td>
          </tr>
          <tr>
            <td class="table-header" style="font-weight: bold;">Received On</td>
            <td class="table-cell">{$receivedOnDate}</td>
          </tr>
          <tr>
            <td class="table-header" style="font-weight: bold;">Institution Name</td>
            <td class="table-cell">{$institutionName}</td>
          </tr>
          <tr>
            <td class="table-header" style="font-weight: bold;">Address</td>
            <td class="table-cell">{$address}</td>
          </tr>
          <tr>
            <td class="table-header" style="font-weight: bold;">Email</td>
            <td class="table-cell">{$institutionEmail}</td>
          </tr>
          <tr>
            <td class="table-header" style="font-weight: bold;">Phone</td>
            <td class="table-cell">{$contactNumber}</td>
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
            <img src="data:image/png;base64,{$imageData['qrCode']}" alt="QR Code" style="width: 80px; height: 70px; margin-top: 2px">
          </div>
        </div>
        <div style="clear: both; margin-top: 20px;"></div>
        <div style="width: 100%; margin-top: 15px; background-color: #f2f2f2; padding: 16px; font-weight: 300; color: #000000">
          <div style="font-size: 10px; margin-bottom: 20px;">
            <div style="position: relative; height: 24px;">
              <div style="font-size: 12px; float: left;">
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
          <div style="text-align: center; width: 100%; margin-top: 26px;">
            <a href="https://www.facebook.com/goonj.org" target="_blank"><img src="data:image/webp;base64,{$imageData['facebookIcon']}" alt="Facebook" style="width: 24px; height: 24px; margin-right: 10px;"></a>
            <a href="https://www.instagram.com/goonj/" target="_blank"><img src="data:image/webp;base64,{$imageData['instagramIcon']}" alt="Instagram" style="width: 24px; height: 24px; margin-right: 10px;"></a>
            <a href="https://x.com/goonj" target="_blank"><img src="data:image/webp;base64,{$imageData['twitterIcon']}" alt="Twitter" style="width: 24px; height: 24px; margin-right: 10px;"></a>
            <a href="https://www.youtube.com/channel/UCCq8iYlmjT7rrgPI1VHzIHg" target="_blank"><img src="data:image/webp;base64,{$imageData['youtubeIcon']}" alt="YouTube" style="width: 24px; height: 24px; margin-right: 10px;"></a>
          </div>
        </div>
        <p style="margin-bottom: 2px; text-align: center; font-size: 12px;">* This is a computer-generated acknowledgement, signature is not required.</p>
      </body>
    </html>
    HTML;

    return $html;
  }

}
