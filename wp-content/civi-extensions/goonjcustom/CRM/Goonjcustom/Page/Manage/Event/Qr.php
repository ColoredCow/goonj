<?php

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 *
 */
class CRM_Goonjcustom_Page_Manage_Event_Qr extends CRM_Core_Page {

  /**
   *
   */
  public function run() {
    $eventID = CRM_Utils_Request::retrieve('id', 'Integer', $this, TRUE);

    $qrDir = CRM_Utils_File::baseFilePath() . 'goonjcustom/qrcodes/';

    if (!is_dir($qrDir)) {
      mkdir($qrDir, 0755, TRUE);
    }

    $qrFilePath = $qrDir . "event_qr_$eventID.png";

    if (!file_exists($qrFilePath)) {
      $this->generateQrCode($eventID, $qrFilePath);
    }

    // @todo
    // `content_url` is a WordPress specific function.
    // We should replace it with CiviCRM specific function.
    // Otherwise this will limit this extension to WorPress-only.
    $qrImageUrl = content_url("uploads/civicrm/goonjcustom/qrcodes/event_qr_$eventID.png");

    $this->assign('qrImageUrl', $qrImageUrl);

    Civi::resources()->addStyleFile('goonjcustom', 'css/custom_styles.css');

    parent::run();
  }

  /**
   * @return string
   */
  public function getTemplateFileName() {
    $templateFile = 'CRM/Goonjcustom/Page/Manage_Event_Qr.tpl';
    $template = CRM_Core_Page::getTemplate();

    if ($template->template_exists($templateFile)) {
      return $templateFile;
    }

    return parent::getTemplateFileName();
  }

  /**
   * Generates a QR code for the event.
   *
   * @param int $eventID
   * @param string $qrFilePath
   */
  private function generateQrCode($eventID, $qrFilePath) {
    $baseUrl = CRM_Core_Config::singleton()->userFrameworkBaseURL;

    $url = "{$baseUrl}actions/collection-camp/{$eventID}";

    $options = new QROptions(
    [
      'version'    => 5,
      'outputType' => QRCode::OUTPUT_IMAGE_PNG,
      'eccLevel'   => QRCode::ECC_L,
    // Adjust the scale as needed.
      'scale'      => 10,
    ]
    );

    $qrcode = (new QRCode($options))->render($url);

    // Remove the base64 header and decode the image data.
    $qrcode = str_replace('data:image/png;base64,', '', $qrcode);
    $qrcode = base64_decode($qrcode);

    file_put_contents($qrFilePath, $qrcode);

    // @todo
    // We should also save the URL or the filesystem path of the
    // QR code image to the database to remember where it is saved.
  }

}
