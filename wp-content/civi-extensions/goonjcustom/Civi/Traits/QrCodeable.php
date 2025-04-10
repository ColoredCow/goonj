<?php

namespace Civi\Traits;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Civi\Api4\CustomField;
use Dompdf\Dompdf;

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

      $qrcode = (new QRCode($options))->render($data);

      // Remove the base64 header and decode the image data.
      $qrcode = str_replace('data:image/png;base64,', '', $qrcode);
      $qrcode = base64_decode($qrcode);

      $baseFileName = "qr_code_{$entityId}.png";

      $saveOptions['baseFileName'] = $baseFileName;
      $saveOptions['entityId'] = $entityId;

      self::saveQrCode($qrcode, $saveOptions);
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
  public static function generatePdfForCollectionCamp($entityId) {
    try {
      $dompdf = new Dompdf(['isRemoteEnabled' => TRUE]);

      $html = '
        <h1>Collection Camp Acknowledgement</h1>
        <p>This is to acknowledge the material contribution.</p>
        <p>Activity ID: ' . $entityId . '</p>
      ';

      $dompdf->loadHtml($html);
      $dompdf->setPaper('A4', 'portrait');
      $dompdf->render();

      $pdfOutput = $dompdf->output();
      $fileName = "acknowledgement_{$entityId}.pdf";
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
    error_log("entityId: " . print_r($entityId, TRUE));
    error_log("fileName: " . print_r($fileName, TRUE));
    error_log("filePath: " . print_r($filePath, TRUE));
    error_log("customGroupName: " . print_r($customGroupName, TRUE));
    error_log("customFieldName: " . print_r($customFieldName, TRUE));

    $customFields = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', $customGroupName)
      ->addWhere('name', '=', $customFieldName)
      ->setLimit(1)
      ->execute();

    $field = $customFields->first();
    error_log("field: " . print_r($field, TRUE));

    if (!$field) {
      \CRM_Core_Error::debug_log_message("Custom field not found: {$customGroupName} / {$customFieldName}");
      return FALSE;
    }

    $fieldId = 'custom_' . $field['id'];
    error_log("fieldId: " . print_r($fieldId, TRUE));

    $params = [
      'entity_id' => $entityId,
      'name' => $fileName,
      'mime_type' => 'application/pdf',
      'field_name' => $fieldId,
      'options' => [
        'move-file' => $filePath,
      ],
    ];
    error_log("params: " . print_r($params, TRUE));

    $result = civicrm_api3('Attachment', 'create', $params);
    error_log("result: " . print_r($result, TRUE));

    if (!empty($result['is_error'])) {
      \CRM_Core_Error::debug_log_message("Failed to attach PDF for entity ID $entityId");
      return FALSE;
    }

    return $result['values'][$result['id']];
  }

}
