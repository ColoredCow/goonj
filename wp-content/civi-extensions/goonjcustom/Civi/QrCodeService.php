<?php

namespace Civi;

use Civi\Api4\CustomField;
use Civi\Core\Service\AutoSubscriber;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 *
 */
class QrCodeService extends AutoSubscriber {

  const DROPPING_CENTER_URL_PATTERN = "%sactions/dropping-center/%s";
  const COLLECTION_CAMP_URL_PATTERN = "%sactions/collection-camp/%s";

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [];
  }

  /**
   *
   */
  public static function generateQrCode($collectionCampId, $collectionCampSubtype) {
    try {
      $baseUrl = \CRM_Core_Config::singleton()->userFrameworkBaseURL;

      if ($collectionCampSubtype === 'Dropping_Center') {
        $url = sprintf(self::DROPPING_CENTER_URL_PATTERN, $baseUrl, $collectionCampId);
      }
      else {
        $url = sprintf(self::COLLECTION_CAMP_URL_PATTERN, $baseUrl, $collectionCampId);
      }

      $options = new QROptions([
        'version'    => 5,
        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
        'eccLevel'   => QRCode::ECC_L,
        'scale'      => 10,
      ]);

      $qrcode = (new QRCode($options))->render($url);

      // Remove the base64 header and decode the image data.
      $qrcode = str_replace('data:image/png;base64,', '', $qrcode);
      $qrcode = base64_decode($qrcode);

      $baseFileName = "qr_code_{$collectionCampId}.png";
      $fileName = \CRM_Utils_File::makeFileName($baseFileName);
      $tempFilePath = \CRM_Utils_File::tempnam($baseFileName);

      $numBytes = file_put_contents($tempFilePath, $qrcode);

      if (!$numBytes) {
        \CRM_Core_Error::debug_log_message('Failed to write QR code to temporary file for collection camp ID ' . $collectionCampId);
        return FALSE;
      }

      $customFields = CustomField::get(FALSE)
        ->addSelect('id')
        ->addWhere('custom_group_id:name', '=', 'Collection_Camp_QR_Code')
        ->addWhere('name', '=', 'QR_Code')
        ->setLimit(1)
        ->execute();

      $qrField = $customFields->first();

      if (!$qrField) {
        \CRM_Core_Error::debug_log_message('No field to save QR Code for collection camp ID ' . $collectionCampId);
        return FALSE;
      }

      $qrFieldId = 'custom_' . $qrField['id'];

      // Save the QR code as an attachment linked to the collection camp.
      $params = [
        'entity_id' => $collectionCampId,
        'name' => $fileName,
        'mime_type' => 'image/png',
        'field_name' => $qrFieldId,
        'options' => [
          'move-file' => $tempFilePath,
        ],
      ];

      $result = civicrm_api3('Attachment', 'create', $params);

      if (!empty($result['is_error'])) {
        \CRM_Core_Error::debug_log_message('Failed to create attachment for collection camp ID ' . $collectionCampId);
        return FALSE;
      }

      $attachment = $result['values'][$result['id']];
      $attachmentUrl = $attachment['url'];

    }
    catch (\Exception $e) {
      \CRM_Core_Error::debug_log_message('Error generating QR code: ' . $e->getMessage());
      return FALSE;
    }

    return TRUE;
  }

}
