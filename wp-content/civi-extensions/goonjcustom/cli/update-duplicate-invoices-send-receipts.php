<?php

/**
 * @file
 * Update duplicate contribution invoice numbers and send email receipts.
 */

use Civi\Api4\Contribution;

echo "Starting Duplicate Contributions Processing...\n";

/**
 * Main function to process duplicate contributions.
 */
function processDuplicateContributions() {
  echo "Finding duplicate contributions...\n";

  // Find all invoice_numbers with duplicates.
  $dao = \CRM_Core_DAO::executeQuery("
    SELECT invoice_number
    FROM civicrm_contribution
    WHERE invoice_number IS NOT NULL AND TRIM(invoice_number) <> ''
    GROUP BY invoice_number
    HAVING COUNT(*) > 1
  ");

  $duplicateInvoices = [];
  while ($dao->fetch()) {
    $duplicateInvoices[] = $dao->invoice_number;
  }

  echo "Found " . count($duplicateInvoices) . " duplicate invoice numbers.\n";

  foreach ($duplicateInvoices as $invoiceNumber) {
    echo "\nProcessing duplicates for invoice number: {$invoiceNumber}\n";

    // Find the earliest contribution (by receive_date or ID)
    $dao = \CRM_Core_DAO::executeQuery("
      SELECT id
      FROM civicrm_contribution
      WHERE invoice_number = %1
      ORDER BY receive_date ASC, id ASC
      LIMIT 1
    ", [1 => [$invoiceNumber, 'String']]);

    $dao->fetch();
    $earliestId = $dao->id;
    echo "Earliest contribution ID: {$earliestId}\n";

    // Find all other contributions with this invoice_number (the duplicates)
    $dao = \CRM_Core_DAO::executeQuery("
      SELECT id
      FROM civicrm_contribution
      WHERE invoice_number = %1 AND id <> %2
    ", [
      1 => [$invoiceNumber, 'String'],
      2 => [$earliestId, 'Integer'],
    ]);

    $duplicateIds = [];
    while ($dao->fetch()) {
      $duplicateIds[] = $dao->id;
    }

    echo "Duplicate contribution IDs: " . implode(', ', $duplicateIds) . "\n";

    // Process each duplicate: update invoice_number and send email.
    foreach ($duplicateIds as $dupId) {
      try {
        $invoiceSeqName = 'GNJCRM_25_26';

        \CRM_Core_DAO::executeQuery('START TRANSACTION');

        $seqDao = \CRM_Core_DAO::executeQuery("
          SELECT ov.id, ov.value, ov.label
          FROM civicrm_option_value ov
          JOIN civicrm_option_group og ON ov.option_group_id = og.id
          WHERE og.name = 'invoice_sequence' AND ov.name = %1
          FOR UPDATE
        ", [1 => [$invoiceSeqName, 'String']]);

        if (!$seqDao->fetch()) {
          throw new \Exception("Invoice sequence not initialized for prefix $invoiceSeqName");
        }

        $last = (int) $seqDao->value;
        $prefix = $seqDao->label;
        $next = $last + 1;
        $newInvoice = $prefix . $next;

        \CRM_Core_DAO::executeQuery("
          UPDATE civicrm_option_value
          SET value = %1
          WHERE id = %2
        ", [
          1 => [$next, 'Integer'],
          2 => [$seqDao->id, 'Integer'],
        ]);

        Contribution::update(FALSE)
          ->addValue('invoice_number', $newInvoice)
          ->addWhere('id', '=', $dupId)
          ->execute();

        \CRM_Core_DAO::executeQuery('COMMIT');

        echo "Assigned new invoice number {$newInvoice} to contribution ID: {$dupId}\n";
        \Civi::log()->info("Assigned invoice number {$newInvoice} to contribution ID: {$dupId}");

        // Send email receipt for the updated contribution.
        sendContributionReceipt($dupId);
      }
      catch (\Exception $e) {
        \CRM_Core_DAO::executeQuery('ROLLBACK');
        echo "Error updating contribution ID: {$dupId}. Error: " . $e->getMessage() . "\n";
        \Civi::log()->error("Invoice number generation failed.", [
          'message' => $e->getMessage(),
          'trace' => $e->getTraceAsString(),
        ]);
      }
    }
  }

  echo "\nDuplicate contributions processing complete.\n";
}

/**
 * Send email receipt for a contribution.
 *
 * @param int $contributionId
 *   Contribution ID.
 */
function sendContributionReceipt($contributionId) {
  echo "Checking: {$contributionId}\n";

  sleep(10); 

  try {
    civicrm_api3('Contribution', 'sendconfirmation', [
      'id' => $contributionId,
    ]);
    echo "Receipt sent for contribution ID: {$contributionId}\n";
    \Civi::log()->info("Receipt sent for contribution ID: {$contributionId}");
  }
  catch (\Exception $e) {
    echo "Failed to send receipt for contribution ID: {$contributionId}. Error: " . $e->getMessage() . "\n";
    \Civi::log()->error("Failed to send receipt for contribution ID: {$contributionId}", [
      'error' => $e->getMessage(),
    ]);
  }
}

processDuplicateContributions();
