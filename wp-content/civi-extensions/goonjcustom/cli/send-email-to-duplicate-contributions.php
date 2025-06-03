<?php

/**
 * @file
 * Script to read contribution IDs, old and new invoice numbers from CSV and send confirmation emails via CiviCRM API.
 * Passes invoice numbers to alterReceiptMail hook for customized email content.
 * Run via `cv scr` for CiviCRM environment setup.
 */

// Below is the cv script that need to be run on terminal
// cv scr ..//civi-extensions/goonjcustom/cli/send-email-to-duplicate-contributions.php | tee send-email-to-duplicates.txt

// Enable error reporting, suppress deprecation warnings temporarily.
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);
// Increase memory limit to handle large email payloads.
ini_set('memory_limit', '256M');

/**
 * Process CSV file and send confirmation emails for each contribution ID.
 *
 * @param string $csvFilePath
 *   Path to the CSV file containing contribution IDs, old_invoice_number, and new_invoice_number.
 *
 * @return void
 */
function sendEmailsFromCsv($csvFilePath) {
  echo "Reading CSV file: $csvFilePath\n";

  if (!file_exists($csvFilePath)) {
    die("CSV file not found at $csvFilePath\n");
  }

  $file = fopen($csvFilePath, 'r');
  if (!$file) {
    die("Unable to open CSV file\n");
  }

  $headers = fgetcsv($file);
  if (!$headers || !in_array('id', $headers) || !in_array('old_invoice_number', $headers) || !in_array('new_invoice_number', $headers)) {
    fclose($file);
    die("CSV must contain 'id', 'old_invoice_number', and 'new_invoice_number' columns\n");
  }

  $results = [];
  $errors = [];
  $rowCount = 0;

  while (($row = fgetcsv($file)) !== FALSE) {
    $rowCount++;
    $data = array_combine($headers, $row);
    $id = $data['id'];
    $oldInvoiceNumber = $data['old_invoice_number'];
    $newInvoiceNumber = $data['new_invoice_number'];
    echo "Processing row $rowCount: ID $id, Old Invoice: $oldInvoiceNumber, New Invoice: $newInvoiceNumber\n";
    echo "Memory usage: " . (memory_get_usage() / 1024 / 1024) . " MB\n";

    try {
      // Store dynamic data for alterReceiptMail.
      $GLOBALS['duplicate'] = [
        'old_invoice_number' => $oldInvoiceNumber,
        'new_invoice_number' => $newInvoiceNumber,
        'contribution_id' => $id,
      ];

      $result = civicrm_api3('Contribution', 'sendconfirmation', [
        'id' => $id,
        'receipt_text' => 'email',
      ]);
      $results[] = "Email sent for ID $id (Old Invoice: $oldInvoiceNumber, New Invoice: $newInvoiceNumber)";
    }
    catch (Exception $e) {
      $errors[] = "Error for ID $id: " . $e->getMessage();
    }
  }

  fclose($file);

  if ($rowCount === 0) {
    echo "No data rows found in CSV\n";
  }

  if ($results) {
    echo "Successes:\n" . implode("\n", $results) . "\n";
  }
  if ($errors) {
    echo "Errors:\n" . implode("\n", $errors) . "\n";
  }

  echo "Processed $rowCount rows\n";
}

/**
 * Main execution function.
 */
function main() {
  $csvFilePath = '/Users/tarunjoshi/Downloads/testing1.csv';
  try {
    sendEmailsFromCsv($csvFilePath);
  }
  catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
  }
}

// Run the script.
main();
