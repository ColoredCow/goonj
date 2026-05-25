<?php

/**
 * @file
 * CLI Script to import already-verified PAN card data from a client-provided CSV file.
 *
 * Contact matching (fallback order):
 *   1. first_name + last_name
 *   2. first_name + phone
 *   3. email + phone
 *
 * For each row in the CSV:
 * - If contact found → save PAN to Contact (Verified) + mark PAN_API_Status = Called
 *                      (overwrites any existing PAN on contact; contribution PAN untouched)
 * - If contact NOT found → log to a "not found" CSV for re-import + write CiviCRM warning log
 * - If PAN format invalid → log + skip
 *
 * Input CSV expected columns:
 *   PAN Card Number, Amount, Name (first name), Last Name, Address, Email, Phone
 *
 * Usage: cv scr wp-content/civi-extensions/goonjcustom/cli/import-verified-pans.php
 */

use Civi\Api4\Contact;
use Civi\PanVerificationService;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

// Allow long-running import for large CSVs (4K+ rows).
set_time_limit(0);
ini_set('memory_limit', '512M');

define('INPUT_CSV_PATH', __DIR__ . '/already-verified-pans.csv');
define('NOT_FOUND_CSV_PATH', __DIR__ . '/already-verified-pans-not-found.csv');
define('PROGRESS_EVERY', 100);

$stats = [
  'total'          => 0,
  'saved'          => 0,
  'overwritten'    => 0,
  'not_found'      => 0,
  'invalid_format' => 0,
  'errors'         => 0,
];

/**
 * Read input CSV and return associative array of rows keyed by header.
 */
function readInputCsv(): array {
  if (!file_exists(INPUT_CSV_PATH)) {
    exit("ERROR: Input CSV not found at: " . INPUT_CSV_PATH . "\n");
  }
  $rows = [];
  $file = fopen(INPUT_CSV_PATH, 'r');
  $headers = fgetcsv($file);
  if (!$headers) {
    fclose($file);
    exit("ERROR: Could not read CSV headers.\n");
  }
  $headers = array_map('trim', $headers);
  while (($row = fgetcsv($file)) !== FALSE) {
    if (count($row) < count($headers)) {
      $row = array_pad($row, count($headers), '');
    }
    $rows[] = array_combine($headers, array_map('trim', $row));
  }
  fclose($file);
  return $rows;
}

/**
 * Try to find contact ID using the fallback chain:
 * first_name + email → first_name + phone → email + phone.
 */
function findContactByFallback(array $row): ?int {
  $firstName = $row['Name'] ?? '';
  $email     = $row['Email'] ?? '';
  $phone     = $row['Phone'] ?? '';

  if ($firstName && $email) {
    $contact = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('email_primary.email', '=', $email)
      ->addWhere('is_deleted', '=', FALSE)
      ->setLimit(1)
      ->execute()
      ->first();
    if ($contact) {
      return (int) $contact['id'];
    }
  }

  if ($firstName && $phone) {
    $contact = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('first_name', '=', $firstName)
      ->addWhere('phone_primary.phone', '=', $phone)
      ->addWhere('is_deleted', '=', FALSE)
      ->setLimit(1)
      ->execute()
      ->first();
    if ($contact) {
      return (int) $contact['id'];
    }
  }

  if ($email && $phone) {
    $contact = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('email_primary.email', '=', $email)
      ->addWhere('phone_primary.phone', '=', $phone)
      ->addWhere('is_deleted', '=', FALSE)
      ->setLimit(1)
      ->execute()
      ->first();
    if ($contact) {
      return (int) $contact['id'];
    }
  }

  return NULL;
}

/**
 * Save the PAN to the contact as Verified and mark API status as Called.
 * Overwrites any existing PAN on the contact.
 */
function importPanToContact(int $contactId, string $pan): void {
  Contact::update(FALSE)
    ->addWhere('id', '=', $contactId)
    ->addValue('PAN_Card_Details.PAN_Card_Number', strtoupper($pan))
    ->addValue('PAN_Card_Details.PAN_Verification_Status:name', PanVerificationService::PAN_STATUS_VERIFIED)
    ->addValue('PAN_Card_Details.PAN_API_Status:name', 'Called')
    ->execute();
}

/**
 * Write rows with contacts that couldn't be matched to a CSV for re-import.
 */
function writeNotFoundReport(array $notFound): void {
  $file = fopen(NOT_FOUND_CSV_PATH, 'w');
  fputcsv($file, ['PAN Card Number', 'Amount', 'Name', 'Last Name', 'Address', 'Email', 'Phone', 'Reason']);
  foreach ($notFound as $row) {
    fputcsv($file, [
      $row['PAN Card Number'] ?? '',
      $row['Amount']          ?? '',
      $row['Name']            ?? '',
      $row['Last Name']       ?? '',
      $row['Address']         ?? '',
      $row['Email']           ?? '',
      $row['Phone']           ?? '',
      $row['_reason']         ?? '',
    ]);
  }
  fclose($file);
}

/**
 * Main process: iterate rows, match contact, save PAN.
 */
function importVerifiedPans(array &$stats): void {
  $rows = readInputCsv();
  $stats['total'] = count($rows);
  echo "Rows to process: {$stats['total']}\n\n";

  $notFound = [];
  $processed = 0;

  foreach ($rows as $index => $row) {
    $processed++;
    $rowNum = $index + 2; // +2 because CSV row 1 is the header and arrays are 0-indexed.

    try {
      $pan = strtoupper(trim($row['PAN Card Number'] ?? ''));

      if (!PanVerificationService::isValidPanFormat($pan)) {
        \Civi::log()->warning('PanImport: skipped row with invalid PAN format', [
          'row'  => $rowNum,
          'name' => $row['Name'] ?? '',
          'pan'  => $pan,
        ]);
        $row['_reason'] = 'Invalid PAN format';
        $notFound[] = $row;
        $stats['invalid_format']++;
        continue;
      }

      $contactId = findContactByFallback($row);

      if (!$contactId) {
        \Civi::log()->warning('PanImport: contact not found in CRM', [
          'row'   => $rowNum,
          'name'  => $row['Name'] ?? '',
          'last'  => $row['Last Name'] ?? '',
          'email' => $row['Email'] ?? '',
          'phone' => $row['Phone'] ?? '',
          'pan'   => $pan,
        ]);
        $row['_reason'] = 'Contact not found by first_name/email, first_name/phone, or email/phone';
        $notFound[] = $row;
        $stats['not_found']++;
        continue;
      }

      $existing    = PanVerificationService::getContactPan($contactId);
      $hadExisting = !empty($existing['pan_number']);

      importPanToContact($contactId, $pan);

      if ($hadExisting) {
        \Civi::log()->info('PanImport: overwrote existing PAN on contact (Verified)', [
          'row'          => $rowNum,
          'contact_id'   => $contactId,
          'previous_pan' => $existing['pan_number'],
          'new_pan'      => $pan,
        ]);
        $stats['overwritten']++;
      }
      else {
        \Civi::log()->info('PanImport: PAN saved to contact as Verified', [
          'row'        => $rowNum,
          'contact_id' => $contactId,
          'pan'        => $pan,
        ]);
        $stats['saved']++;
      }
    }
    catch (\Throwable $e) {
      // Catch ANY error (including PHP 7+ TypeError, fatal-ish errors) so the loop never dies mid-batch.
      \Civi::log()->error('PanImport: unexpected error on row', [
        'row'   => $rowNum,
        'name'  => $row['Name'] ?? '',
        'pan'   => $row['PAN Card Number'] ?? '',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      $row['_reason'] = 'Error: ' . $e->getMessage();
      $notFound[] = $row;
      $stats['errors']++;
    }

    // Periodic progress + flush stats so the log doesn't go silent for 4K rows.
    if ($processed % PROGRESS_EVERY === 0) {
      echo "Processed {$processed}/{$stats['total']} — saved:{$stats['saved']} overwritten:{$stats['overwritten']} not_found:{$stats['not_found']} invalid:{$stats['invalid_format']} errors:{$stats['errors']}\n";
    }
  }

  if (!empty($notFound)) {
    writeNotFoundReport($notFound);
    echo "\nNot-found report saved to: " . NOT_FOUND_CSV_PATH . "\n";
  }
}

echo "=== Starting Verified PAN Import ===\n";
echo "Input CSV: " . INPUT_CSV_PATH . "\n\n";

importVerifiedPans($stats);

echo "\n=== Import Complete ===\n";
echo "Total rows         : {$stats['total']}\n";
echo "Saved (new PAN)    : {$stats['saved']}\n";
echo "Overwritten        : {$stats['overwritten']}\n";
echo "Not found          : {$stats['not_found']}\n";
echo "Invalid format     : {$stats['invalid_format']}\n";
echo "Errors             : {$stats['errors']}\n";
