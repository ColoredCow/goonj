<?php

namespace Civi;

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
      '&hook_civicrm_post' => [],
      '&hook_civicrm_alterMailParams' => [
        ['saveReceiptFromMail'],
      ],
    ];
  }

  /**
   *
   */
  public static function saveReceiptFromMail(&$params, $context) {
    if (!empty($params['workflow']) && $params['workflow'] === 'contribution_online_receipt') {
      $contributionID = !empty($params['tplParams']['contributionID']) ? $params['tplParams']['contributionID'] : NULL;

      error_log("attachments are there : " . print_r($params['attachments'], TRUE));

      // --- Add: Copy generated PDF to persistent folder ---
      if (!empty($params['attachments']) && $contributionID) {
        error_log(" working inside the functon : ");

        foreach ($params['attachments'] as $attach) {
          $sourceFile = $attach['fullPath'];
          error_log("sourceFile : " . print_r($sourceFile, TRUE));

          // Build persistent path.
          $uploadDir = WP_CONTENT_DIR . '/uploads/civicrm/persist/contribute/contribution';
          if (!file_exists($uploadDir)) {
            // Recursive create.
            mkdir($uploadDir, 0777, TRUE);
          }
          $destFile = $uploadDir . "/receipt_{$contributionID}.pdf";
          error_log(" destFile : " . print_r($destFile, TRUE));

          if (file_exists($sourceFile)) {
            copy($sourceFile, $destFile);
            $baseUrl = \CRM_Core_Config::singleton()->userFrameworkBaseURL;
            error_log("Saved receipt for contribution : " . print_r($destFile, TRUE));
            error_log(" contribution : " . print_r($contributionID, TRUE));
          }
          else {
            error_log("source receipt file not found for contribution : " . print_r($sourceFile, TRUE));
            error_log(" contribution else: " . print_r($contributionID, TRUE));
          }
        }
      }
      // --- End copy PDF ---
    }
  }

}
