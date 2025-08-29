<?php

namespace Civi\Traits;

use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;

use Civi\InstitutionMaterialContributionService;
use Civi\MaterialContributionService;
use Dompdf\Dompdf;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 *
 */
trait QrCodeable {

  /**
   *
   */
  public static function generateQrCode($data, $entityId, $saveOptions) {
    try {
      $options = new QROptions([
        'version'    => 5,
        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
        'eccLevel'   => QRCode::ECC_L,
        'scale'      => 10,
      ]);
  
      // Generate QR as PNG binary
      $qrcode = (new QRCode($options))->render($data);
      $qrcode = str_replace('data:image/png;base64,', '', $qrcode);
      $qrcode = base64_decode($qrcode);
  
      // --- Step 1: Create GD image from QR ---
      $qrImage = imagecreatefromstring($qrcode);
      $qrWidth = imagesx($qrImage);
      $qrHeight = imagesy($qrImage);
  
      // --- Step 2: Load logo image (from URL) ---
      $logoUrl = "https://goonj.org/wp-content/uploads/2020/06/Goonj-logo-10June20.png";
      $logoData = file_get_contents($logoUrl);
      $logoImage = imagecreatefromstring($logoData);
  
      $logoWidth = imagesx($logoImage);
      $logoHeight = imagesy($logoImage);
  
      // --- Step 2a: Resize logo with scale factor ---
      $scaleFactor = 0.4; // 👈 change this (0.5 = 50% width, 0.3 = 30% width, etc.)
      $newLogoWidth = (int)($qrWidth * $scaleFactor);
      $newLogoHeight = (int)($logoHeight * ($newLogoWidth / $logoWidth));
  
      $resizedLogo = imagecreatetruecolor($newLogoWidth, $newLogoHeight);
      imagealphablending($resizedLogo, false);
      imagesavealpha($resizedLogo, true);
  
      imagecopyresampled(
        $resizedLogo, $logoImage,
        0, 0, 0, 0,
        $newLogoWidth, $newLogoHeight,
        $logoWidth, $logoHeight
      );
  
      // --- Step 3: Create new canvas (logo + QR + text space) ---
      $text = "Hello here is the qr code";
      $fontHeight = 30; // reserve space for text
  
      $canvasHeight = $newLogoHeight + $qrHeight + $fontHeight + 20;
      $canvas = imagecreatetruecolor($qrWidth, $canvasHeight);
  
      // White background
      $white = imagecolorallocate($canvas, 255, 255, 255);
      imagefill($canvas, 0, 0, $white);
  
      // --- Step 4: Copy logo (centered) + QR ---
      $logoX = (int)(($qrWidth - $newLogoWidth) / 2);
      imagecopy($canvas, $resizedLogo, $logoX, 0, 0, 0, $newLogoWidth, $newLogoHeight);
  
      imagecopy($canvas, $qrImage, 0, $newLogoHeight, 0, 0, $qrWidth, $qrHeight);
  
      // --- Step 5: Add text below QR ---
      $black = imagecolorallocate($canvas, 0, 0, 0);
      $x = 10;
      $y = $newLogoHeight + $qrHeight + 20;
      imagestring($canvas, 5, $x, $y, $text, $black);
  
      // --- Step 6: Save combined image ---
      ob_start();
      imagepng($canvas);
      $finalImage = ob_get_clean();
  
      imagedestroy($qrImage);
      imagedestroy($logoImage);
      imagedestroy($resizedLogo);
      imagedestroy($canvas);
  
      $baseFileName = "qr_code_{$entityId}.png";
      $saveOptions['baseFileName'] = $baseFileName;
      $saveOptions['entityId'] = $entityId;
  
      self::saveQrCode($finalImage, $saveOptions);
  
    } catch (\Exception $e) {
      \CRM_Core_Error::debug_log_message('Error generating QR code: ' . $e->getMessage());
      return FALSE;
    }
  
    return TRUE;
  }
  

  /**
   *
   */
  public static function handleCampRedirect($id) {
    $camp = EckEntity::get('Collection_Camp', TRUE)
      ->addSelect('Collection_Camp_Intent_Details.End_Date')
      ->addWhere('id', '=', $id)
      ->execute()
      ->first();

    error_log("data: " . print_r($camp, TRUE));

    $endRaw = $camp['Collection_Camp_Intent_Details.End_Date'] ?? NULL;
    error_log("endRaw: " . print_r($endRaw, TRUE));

    if ($endRaw) {
      $endDate = new \DateTime($endRaw);
      $endDate->modify('+3 days');
      $today = new \DateTime();

      if ($today > $endDate) {
    error_log("working");

        // Redirect to goonj.org after end+3 days.
        \CRM_Utils_System::redirect('https://goonj.org/');
        error_log("is redirect??: ");

        return;

        error_log("Checking ??: ");

      }
    }

    // Otherwise normal camp page.
    $baseUrl = \CRM_Core_Config::singleton()->userFrameworkBaseURL;
    \CRM_Utils_System::redirect("{$baseUrl}actions/collection-camp/{$id}");
  }

  /**
   *
   */
  public static function saveQrCode($qrcode, $options) {
    $baseFileName = $options['baseFileName'];
    $entityId = $options['entityId'];
    $customGroupName = $options['customGroupName'];
    $customFieldName = $options['customFieldName'];

    $fileName = \CRM_Utils_File::makeFileName($baseFileName);
    $tempFilePath = \CRM_Utils_File::tempnam($baseFileName);

    $numBytes = file_put_contents($tempFilePath, $qrcode);

    if (!$numBytes) {
      \CRM_Core_Error::debug_log_message('Failed to write QR code to temporary file for entity ID ' . $entityId);
      return FALSE;
    }

    $customFields = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', $customGroupName)
      ->addWhere('name', '=', $customFieldName)
      ->setLimit(1)
      ->execute();

    $qrField = $customFields->first();

    if (!$qrField) {
      \CRM_Core_Error::debug_log_message('No field to save QR Code for entity ID ' . $entityId);
      return FALSE;
    }

    $qrFieldId = 'custom_' . $qrField['id'];

    // Save the QR code as an attachment linked to the entity.
    $params = [
      'entity_id' => $entityId,
      'name' => $fileName,
      'mime_type' => 'image/png',
      'field_name' => $qrFieldId,
      'options' => [
        'move-file' => $tempFilePath,
      ],
    ];

    $result = civicrm_api3('Attachment', 'create', $params);

    if (!empty($result['is_error'])) {
      \CRM_Core_Error::debug_log_message('Failed to create attachment for entity ID ' . $entityId);
      return FALSE;
    }

    $attachment = $result['values'][$result['id']];

    return $attachment;

  }

  /**
   *
   */
  public static function generatePdfForCollectionCamp($entityId, $activity, $email, $phone, $locationAreaOfCamp, $contributionDate, $subtype, $eventId, $goonjOfficeId) {
    try {
      $dompdf = new Dompdf(['isRemoteEnabled' => TRUE]);

      $html = MaterialContributionService::generateContributionReceiptHtml($activity, $email, $phone, $locationAreaOfCamp, $contributionDate, $subtype, $eventId, $goonjOfficeId);

      $dompdf->loadHtml($html);
      $dompdf->setPaper('A4', 'portrait');
      $dompdf->render();

      $pdfOutput = $dompdf->output();
      $fileName = "material_contribution_{$entityId}.pdf";
      $tempFilePath = \CRM_Utils_File::tempnam($fileName);

      file_put_contents($tempFilePath, $pdfOutput);

      // Save to custom field.
      return self::savePdfAttachmentToCustomField(
        $entityId,
        $fileName,
        $tempFilePath,
      // Your custom group name.
        'Material_Contribution',
      // Your custom field name.
        'Receipt'
      );

    }
    catch (\Exception $e) {
      \CRM_Core_Error::debug_log_message('PDF error: ' . $e->getMessage());
      return FALSE;
    }
  }

  /**
   *
   */
  public static function savePdfAttachmentToCustomField($entityId, $fileName, $filePath, $customGroupName, $customFieldName) {
    $customFields = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', $customGroupName)
      ->addWhere('name', '=', $customFieldName)
      ->setLimit(1)
      ->execute();

    $field = $customFields->first();

    if (!$field) {
      \CRM_Core_Error::debug_log_message("Custom field not found: {$customGroupName} / {$customFieldName}");
      return FALSE;
    }

    $fieldId = 'custom_' . $field['id'];

    $params = [
      'entity_id' => $entityId,
      'name' => $fileName,
      'mime_type' => 'application/pdf',
      'field_name' => $fieldId,
      'options' => [
        'move-file' => $filePath,
      ],
    ];

    $result = civicrm_api3('Attachment', 'create', $params);

    if (!empty($result['is_error'])) {
      \CRM_Core_Error::debug_log_message("Failed to attach PDF for entity ID $entityId");
      return FALSE;
    }

    return $result['values'][$result['id']];
  }

  /**
   *
   */
  public static function generatePdfForEvent($organizationName, $organizationAddress, $contribution, $email, $phone, $description, $name, $deliveredBy, $deliveredByContact, $activityDate, $entityId) {
    try {
      $dompdf = new Dompdf(['isRemoteEnabled' => TRUE]);

      $html = InstitutionMaterialContributionService::generateContributionReceiptHtml($organizationName, $organizationAddress, $contribution, $email, $phone, $description, $name, $deliveredBy, $deliveredByContact, $activityDate);

      $dompdf->loadHtml($html);
      $dompdf->setPaper('A4', 'portrait');
      $dompdf->render();

      $pdfOutput = $dompdf->output();
      $fileName = "institution_material_contribution_{$entityId}.pdf";
      $tempFilePath = \CRM_Utils_File::tempnam($fileName);

      file_put_contents($tempFilePath, $pdfOutput);

      // Save to custom field.
      return self::savePdfAttachmentToCustomField(
        $entityId,
        $fileName,
        $tempFilePath,
      // Your custom group name.
        'Institution_Material_Contribution',
      // Your custom field name.
        'Receipt'
      );

    }
    catch (\Exception $e) {
      \CRM_Core_Error::debug_log_message('PDF error: ' . $e->getMessage());
      return FALSE;
    }
  }

}
