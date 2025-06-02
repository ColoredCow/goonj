<?php

/**
 * @file
 * Minimal script to read contribution IDs from CSV and send confirmation emails via CiviCRM API.
 * Run via `cv scr` for CiviCRM environment setup.
 */

// Enable error reporting.
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Process CSV file and send confirmation emails for each contribution ID.
 *
 * @param string $csvFilePath
 *   Path to the CSV file containing contribution IDs.
 *
 * @return void
 */
function sendEmailsFromCsv($csvFilePath) {
  echo "Reading CSV file: $csvFilePath\n";

  if (!file_exists($csvFilePath)) {
    die("CSV file not found at $csvFilePath\n");
  }

  $file = fopen($csvFilePath, 'r');
  if ($file === FALSE) {
    die("Unable to open CSV file\n");
  }

  $headers = fgetcsv($file);
  if (!$headers || !in_array('id', $headers)) {
    fclose($file);
    die("CSV must contain 'id' column\n");
  }

  $results = [];
  $errors = [];
  $rowCount = 0;

  while (($row = fgetcsv($file)) !== FALSE) {
    $rowCount++;
    $data = array_combine($headers, $row);
    $id = $data['id'];
    echo "Processing row $rowCount: ID $id\n";

    try {
      $result = civicrm_api3('Contribution', 'sendconfirmation', [
        'id' => $id,
        'receipt_text' => 'Backend',
      ]);
      $results[] = "Email sent for ID $id";
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
