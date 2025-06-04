<?php

/**
 * @file
 * Script to read contribution IDs and new invoice numbers from CSV and update contributions in CiviCRM using API4.
 * Run via `cv scr` for CiviCRM environment setup.
 */

// Below is the cv script that need to be run on terminal
// cv scr ..//civi-extensions/goonjcustom/cli/assign-new-invoice-number-to-duplicate-invoice.php | tee assign-new-invoice-number.txt

use Civi\Api4\Contribution;

// Enable error reporting.
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Process CSV file and update contribution invoice numbers.
 *
 * @param string $csvFilePath
 *   Path to the CSV file containing id and new_invoice_number.
 *
 * @return void
 */
function updateContributionInvoices($csvFilePath) {
  echo "Reading CSV file: $csvFilePath\n";

  if (!file_exists($csvFilePath)) {
    die("CSV file not found at $csvFilePath\n");
  }

  $file = fopen($csvFilePath, 'r');
  if ($file === FALSE) {
    die("Unable to open CSV file\n");
  }

  $headers = fgetcsv($file);
  if (!$headers || !in_array('id', $headers) || !in_array('new_invoice_number', $headers)) {
    fclose($file);
    die("CSV must contain 'id' and 'new_invoice_number' columns\n");
  }

  $results = [];
  $errors = [];
  $rowCount = 0;

  while (($row = fgetcsv($file)) !== FALSE) {
    $rowCount++;
    $data = array_combine($headers, $row);
    $id = $data['id'];
    $newInvoiceNumber = $data['new_invoice_number'];
    $currentDateTime = date('Y-m-d H:i:s');
    echo "Processing row $rowCount: ID $id, New Invoice $newInvoiceNumber\n";

    try {
      $result = Contribution::update(FALSE)
        ->addValue('invoice_number', $newInvoiceNumber)
        ->addValue('receipt_date', $currentDateTime)
        ->addWhere('id', '=', $id)
        ->execute();
      $results[] = "Updated invoice number for ID $id to $newInvoiceNumber";
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
  $csvFilePath = '/Users/tarunjoshi/Downloads/Final1.csv';
  try {
    updateContributionInvoices($csvFilePath);
  }
  catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
  }
}

// Run the script.
main();
