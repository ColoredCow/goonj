<?php

namespace Civi;

use Civi\Api4\Contact;
use CRM\Civiglific\GlificClient;
use Civi\Api4\Phone;
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
    static $processed = [];
    if ($entity !== 'Contribution' || !$objectRef->id) {
      return;
    }

    // Prevent infinite loop.
    if (in_array($objectRef->id, $processed)) {
      return;
    }
    $processed[] = $objectRef->id;

    try {
      $contributionId = $objectRef->id;

      // ---- 1. Invoice generation (if not already there) ----
      try {
        \Civi\CollectionCampService::generateInvoiceNumber($op, $entity, $id, $objectRef);
      } catch (\Throwable $e) {
        \Civi::log()->error("[triggerMonetaryEmail] Failed to call generateInvoiceNumber: " . $e->getMessage());
      }

      // Load contribution BAO.
      if (!class_exists('\CRM_Contribute_BAO_Contribution')) {
        require_once 'CRM/Contribute/BAO/Contribution.php';
      }

      $contribution = new \CRM_Contribute_BAO_Contribution();

      $contributionData = Contribution::get(FALSE)
        ->addSelect('total_amount', 'is_test', 'fee_amount', 'net_amount', 'trxn_id', 'receive_date', 'contribution_status_id', 'contact_id', 'Contribution_Details.Send_Receipt_via_WhatsApp:name', 'invoice_number', 'contribution_page_id:name', 'Contribution_Details.Is_WhatsApp_Message_Send')
        ->addWhere('id', '=', $contributionId)
        ->execute()->first();

      $isSendReceiptViaWhatsApp = $contributionData['Contribution_Details.Send_Receipt_via_WhatsApp:name'];
      $isWhatsaApppMessageSent = $contributionData['Contribution_Details.Is_WhatsApp_Message_Send'];

      if ($isSendReceiptViaWhatsApp == NULL) {
        return;
      }

      if ($isWhatsaApppMessageSent == TRUE) {
        return;
      }

      $invoiceNumber = $contributionData['invoice_number'] ?? '';
      if (empty($invoiceNumber)) {
        return;
      }

      $contributionPageName = $contributionData['contribution_page_id:name'] ?? '';
      if ($contributionPageName === 'Team_5000') {
        $templateId = CIVICRM_GLIFIC_TEMPLATE_ID_TEAM5000;
      }
      else {
        $templateId = CIVICRM_GLIFIC_TEMPLATE_ID_DEFAULT;
      }

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
      $saveDir = $uploadBase . CIVICRM_PERSIST_PDF_PATH;

      if (!file_exists($saveDir)) {
        mkdir($saveDir, 0755, TRUE);
      }

      $savePath = $saveDir . $fileNameForPdf;
      if (@file_put_contents($savePath, $pdfBinary) === FALSE) {
        throw new \Exception("Failed to save PDF to {$savePath}");
      }

      $baseUrl = rtrim(\CRM_Core_Config::singleton()->userFrameworkBaseURL, '/');
      $pdfUrl  = $baseUrl . CIVICRM_SAVED_PDF_PATH . $fileNameForPdf;

      $contributionContactId = $contributionData['contact_id'];

      $phones = Phone::get(FALSE)
        ->addSelect('phone')
        ->addWhere('contact_id', '=', $contributionContactId)
        ->execute()->first();

      $contacts = Contact::get(FALSE)
        ->addSelect('display_name')
        ->addWhere('id', '=', $contributionContactId)
        ->execute()->first();

      $contactName = $contacts['display_name'] ?? 'Valued Supporter';

      $phoneNumber = $phones['phone'] ?? NULL;

      // --- Call GlificClient function ---
      $glificContactId = NULL;
      if ($phoneNumber) {
        try {
          $glificClient = new GlificClient();
          $glificContactId = $glificClient->getContactIdByPhone($phoneNumber);

          if (!$glificContactId) {
            $newContactId = $glificClient->createContact($contactName, $phoneNumber);
            if ($newContactId) {
              // Opt-in immediately after creation.
              $optinId = $glificClient->optinContact($phoneNumber, $contactName);
              if ($optinId) {
                $glificContactId = $optinId;
              }
              else {
                // Fallback if opt-in fails.
                $glificContactId = $newContactId;
              }
            }
          }
        }
        catch (\Throwable $e) {
          \Civi::log()->info("MonetaryReceiptService] Glific sync failed:", $e->getMessage());
        }
      }

      if ($glificContactId && $pdfUrl) {
        try {
          $glificClient = new GlificClient();

          // --- 1. Upload PDF to Glific ---
          $media = $glificClient->createMessageMedia($pdfUrl);

          if ($media) {
            $mediaId = $media['id'];

            \Civi::log()->info("[MonetaryReceiptService] Created media in Glific", [
              'media_id' => $mediaId,
              'media_url' => $media['url'],
            ]);

            // Dynamic template params.
            $params = [
              $contactName ?: "-",
              $invoiceNumber ?: "-",
            ];

            $message = $glificClient->sendMessage($glificContactId, $mediaId, $templateId, $params);
            if ($message) {
              \Civi::log()->info("[MonetaryReceiptService] Message sent via Glific", [
                'message_id' => $message['id'],
                'template_id' => $message['templateId'],
              ]);
            }
          }
        }
        catch (\Throwable $e) {
          \Civi::log()->error("[MonetaryReceiptService] Glific API failed: " . $e->getMessage());
        }
      }

      $results = Contribution::update(FALSE)
        ->addValue('Contribution_Details.Is_WhatsApp_Message_Send', TRUE)
        ->addWhere('id', '=', $contributionId)
        ->execute();

      return [
        'receipt_path' => $savePath,
        'glific_contact_id' => $glificContactId,
      ];

    }
    catch (\Throwable $e) {
      \Civi::log()->error("[MonetaryReceiptService] Exception: " . $e->getMessage(), ['exception' => $e]);

      return NULL;
    }
  }

}
