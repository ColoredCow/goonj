<?php

namespace Civi;

use Civi\Api4\Contribution;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class MonetaryReceiptService extends AutoSubscriber {

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => [
        'triggerMonetaryEmail',
      ],
    ];
  }

  /**
   *
   */
  public static function triggerMonetaryEmail($op, $entity, $id, $objectRef = NULL) {
    if ($entity !== 'Contribution' || !$objectRef->id) {
      return;
    }
    try {
      $contributionId = $objectRef->id;

      // Load contribution BAO.
      if (!class_exists('\CRM_Contribute_BAO_Contribution')) {
        require_once 'CRM/Contribute/BAO/Contribution.php';
      }

      $contribution = new \CRM_Contribute_BAO_Contribution();

      $contributionData = Contribution::get(FALSE)
        ->addSelect('total_amount', 'is_test', 'fee_amount', 'net_amount', 'trxn_id', 'receive_date', 'contribution_status_id', 'contact_id', 'Contribution_Details.Send_Receipt_via_WhatsApp:name')
        ->addWhere('id', '=', $contributionId)
        ->execute()->first();

      error_log('contributionData: ' . print_r($contributionData, TRUE));

      $isSendReceiptViaWhatsApp = $contributionData['Contribution_Details.Send_Receipt_via_WhatsApp:name'];
      error_log('issendreceipt: ' . print_r($isSendReceiptViaWhatsApp, TRUE));

      if ($isSendReceiptViaWhatsApp == NULL) {
        return;
      }

      // Prepare input and IDs.
      $input = [
        'amount' => $contributionData['total_amount'] ?? NULL,
        'is_test' => $contributionData['is_test'] ?? 0,
        'fee_amount' => $contributionData['fee_amount'] ?? 0,
        'net_amount' => $contributionData['net_amount'] ?? 0,
        'trxn_id' => $contributionData['trxn_id'] ?? '',
        'trxn_date' => $contributionData['receive_date'] ?? NULL,
        'receipt_update' => 1,
        'contribution_status_id' => $contributionData['contribution_status_id'] ?? NULL,
        'payment_processor_id' => !empty($contribution->trxn_id)
          ? \CRM_Core_DAO::singleValueQuery(
                    "SELECT payment_processor_id FROM civicrm_financial_trxn WHERE trxn_id = %1 LIMIT 1",
                    [1 => [$contribution->trxn_id, 'String']]
        )
          : NULL,
      ];
      $ids = [
        'contact' => $contributionData['contact_id'] ?? NULL,
        'contribution' => $contributionId,
        'contributionRecur' => NULL,
        'contributionPage' => NULL,
        'membership' => NULL,
        'participant' => NULL,
        'event' => NULL,
      ];

      // Generate PDF.
      if (!class_exists('\CRM_Utils_PDF_Utils')) {
        require_once 'CRM/Utils/PDF/Utils.php';
      }

      $pdfFormatId = NULL;
      if (class_exists('\CRM_Core_BAO_PdfFormat')) {
        $def = \CRM_Core_BAO_PdfFormat::getDefaultValues();
        $pdfFormatId = $def['id'] ?? NULL;
      }

      $messages = [];
      $mail = \CRM_Contribute_BAO_Contribution::sendMail($input, $ids, $contributionId, TRUE);

      if (!empty($mail['html'])) {
        $messages[] = $mail['html'];
      }
      elseif (!empty($mail['body'])) {
        $messages[] = nl2br(htmlspecialchars($mail['body']));
      }
      else {
        $messages[] = "<html><body><h3>Contribution Receipt</h3><p>Contribution ID: {$contributionId}</p></body></html>";
      }

      $fileNameForPdf = "receipt_{$contributionId}.pdf";
      $pdfBinary = \CRM_Utils_PDF_Utils::html2pdf($messages, $fileNameForPdf, TRUE, $pdfFormatId);
      if (empty($pdfBinary)) {
        throw new \Exception("PDF generation failed for contribution {$contributionId}");
      }

      // Save PDF to persistent location.
      $uploadBase = defined('WP_CONTENT_DIR') ? rtrim(WP_CONTENT_DIR, '/') : rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/');
      $saveDir = $uploadBase . '/uploads/civicrm/persist/contribute/contribution/';
      if (!file_exists($saveDir)) {
        mkdir($saveDir, 0755, TRUE);
      }

      $savePath = $saveDir . $fileNameForPdf;
      if (@file_put_contents($savePath, $pdfBinary) === FALSE) {
        throw new \Exception("Failed to save PDF to {$savePath}");
      }

      // Build public URL.
      $baseUrl = rtrim(\CRM_Core_Config::singleton()->userFrameworkBaseURL, '/');
      $pdfUrl  = $baseUrl . '/wp-content/uploads/civicrm/persist/contribute/contribution/' . $fileNameForPdf;

      return $savePath;

    }
    catch (\Throwable $e) {
      error_log("[MonetaryReceiptService] EXCEPTION: {$e->getMessage()}\n{$e->getTraceAsString()}");
      return NULL;
    }
  }

}
