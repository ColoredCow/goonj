<?php

namespace Civi\Traits;

use Civi\Api4\EckEntity;
use Civi\Api4\CustomField;
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
      // High for good quality.
        'scale'      => 12,
      ]);

      $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
        ->addSelect('Collection_Camp_Intent_Details.Location_Area_of_camp')
        ->addWhere('id', '=', $entityId)
        ->execute()->first();

      $collectionCampAddress = $collectionCamp['Collection_Camp_Intent_Details.Location_Area_of_camp'];

      // Generate QR.
      $qrcode = (new QRCode($options))->render($data);
      $qrcode = str_replace('data:image/png;base64,', '', $qrcode);
      $qrcode = base64_decode($qrcode);
      $qrImage = imagecreatefromstring($qrcode);
      $qrWidth = imagesx($qrImage);
      $qrHeight = imagesy($qrImage);

      // Resize QR (smaller inside fixed canvas)
      $qrScale = 0.7;
      $newQrWidth  = (int) ($qrWidth * $qrScale);
      $newQrHeight = (int) ($qrHeight * $qrScale);

      $resizedQr = imagecreatetruecolor($newQrWidth, $newQrHeight);
      imagealphablending($resizedQr, FALSE);
      imagesavealpha($resizedQr, TRUE);

      imagecopyresampled(
            $resizedQr, $qrImage,
            0, 0, 0, 0,
            $newQrWidth, $newQrHeight,
            $qrWidth, $qrHeight
        );

      // Load logo.
      $upload_dir = wp_upload_dir();
      $logoUrl = $upload_dir['baseurl'] . '/2024/09/Goonj-logo-10June20-300x193-1.png';
      $logoData = file_get_contents($logoUrl);
      $logoImage = imagecreatefromstring($logoData);
      $logoWidth = imagesx($logoImage);
      $logoHeight = imagesy($logoImage);

      // Resize logo.
      $scaleFactor = 0.5;
      $newLogoWidth = (int) ($newQrWidth * $scaleFactor);
      $newLogoHeight = (int) ($logoHeight * ($newLogoWidth / $logoWidth));
      $resizedLogo = imagecreatetruecolor($newLogoWidth, $newLogoHeight);
      imagealphablending($resizedLogo, FALSE);
      imagesavealpha($resizedLogo, TRUE);
      imagecopyresized(
            $resizedLogo, $logoImage,
            0, 0, 0, 0,
            $newLogoWidth, $newLogoHeight,
            $logoWidth, $logoHeight
        );

      // Texts.
      $topText = "Scan to Record Your\nContribution";
      $venueLabel = "Venue - ";
      $venueValue = $collectionCampAddress;

      // Canvas: logo + top text + QR + bottom text.
      $canvasWidth = $qrWidth + 100;
      $canvasHeight = $newLogoHeight + $qrHeight + 220;
      $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);

      $white = imagecolorallocate($canvas, 255, 255, 255);
      $black = imagecolorallocate($canvas, 0, 0, 0);
      imagefill($canvas, 0, 0, $white);

      // Fonts.
      $fontPath = dirname(__DIR__, 2) . '/fonts/AbhayaLibre-Bold.ttf';
      $fontPathRegular = dirname(__DIR__, 2) . '/fonts/AbhayaLibre-Regular.ttf';

      // --- Step 1: Logo
      $logoX = (int) (($canvasWidth - $newLogoWidth) / 2);
      $logoY = 10;
      imagecopy($canvas, $resizedLogo, $logoX, $logoY, 0, 0, $newLogoWidth, $newLogoHeight);

      // --- Step 2: Heading text
      $fontSize = 47;
      $topY = $logoY + $newLogoHeight + 60;
      $lines = explode("\n", $topText);
      $lineHeight = $fontSize + 10;

      foreach ($lines as $i => $line) {
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
        $textWidth = abs($bbox[2] - $bbox[0]);
        $x = (int) (($canvasWidth - $textWidth) / 2);
        $y = $topY + ($i * $lineHeight);
        imagettftext($canvas, $fontSize, 0, $x, $y, $black, $fontPath, $line);
      }

      // --- Step 3: QR code
      $qrY = $topY + 70;
      $qrX = (int) (($canvasWidth - $newQrWidth) / 2);
      imagecopy($canvas, $resizedQr, $qrX, $qrY, 0, 0, $newQrWidth, $newQrHeight);

      // --- Step 4: Venue text with wrapping
      $bottomFontSize = 25;
      $maxWidth = $canvasWidth - 80;
      $bottomY = $qrY + $newQrHeight + 40;

      // Start with bold "Venue:".
      $bboxLabel = imagettfbbox($bottomFontSize, 0, $fontPath, $venueLabel);
      $labelWidth = abs($bboxLabel[2] - $bboxLabel[0]);

      $lines = [];
      $currentLine = $venueLabel;
      $currentWidth = $labelWidth;

      $words = explode(" ", $venueValue);
      foreach ($words as $word) {
        $testLine = ($currentLine === $venueLabel ? $currentLine : $currentLine . " ") . $word;
        $bboxTest = imagettfbbox($bottomFontSize, 0, $fontPathRegular, $testLine);
        $testWidth = abs($bboxTest[2] - $bboxTest[0]);

        if ($testWidth > $maxWidth) {
          $lines[] = $currentLine;
          $currentLine = $word;
        }
        else {
          $currentLine = $testLine;
        }
      }
      $lines[] = $currentLine;

      $lineHeight = $bottomFontSize + 8;
      foreach ($lines as $i => $line) {
        $bbox = imagettfbbox($bottomFontSize, 0, $fontPathRegular, $line);
        $lineWidth = abs($bbox[2] - $bbox[0]);
        $x = (int) (($canvasWidth - $lineWidth) / 2);
        $y = $bottomY + ($i * $lineHeight);

        if ($i === 0) {
          imagettftext($canvas, $bottomFontSize, 0, $x, $y, $black, $fontPath, $venueLabel);
          $valuePart = trim(str_replace($venueLabel, "", $line));
          if ($valuePart !== "") {
            $bboxLabel = imagettfbbox($bottomFontSize, 0, $fontPath, $venueLabel);
            $offset = abs($bboxLabel[2] - $bboxLabel[0]);
            imagettftext($canvas, $bottomFontSize, 0, $x + $offset, $y, $black, $fontPathRegular, $valuePart);
          }
        }
        else {
          imagettftext($canvas, $bottomFontSize, 0, $x, $y, $black, $fontPathRegular, $line);
        }
      }

      // Save final.
      ob_start();
      imagepng($canvas);
      $finalImage = ob_get_clean();

      imagedestroy($qrImage);
      imagedestroy($resizedQr);
      imagedestroy($logoImage);
      imagedestroy($resizedLogo);
      imagedestroy($canvas);

      $baseFileName = "qr_code_{$entityId}.png";
      $saveOptions['baseFileName'] = $baseFileName;
      $saveOptions['entityId'] = $entityId;
      self::saveQrCode($finalImage, $saveOptions);
    }
    catch (\Exception $e) {
      \CRM_Core_Error::debug_log_message('Error generating QR code: ' . $e->getMessage());
      return FALSE;
    }

    return TRUE;
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
