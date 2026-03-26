<?php

/**
 * @file
 * CLI Script to bulk verify PAN card numbers via CashFree API.
 *
 * Fetches contacts who have a PAN saved on their profile and API has not been called yet.
 * PAN was already populated on the Contact by the backfill-contact-pan.php script.
 *
 * Resume-safe: contacts where API was already called (PAN_API_Status = Called) are skipped.
 * Rerunning is safe — no duplicate API charges.
 *
 * Usage: cv scr wp-content/civi-extensions/goonjcustom/cli/bulk-pan-verification.php
 */

use Civi\Api4\Contact;
use Civi\PanVerificationService;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

// Delay in microseconds between API calls to avoid rate limiting (0.5 seconds).
define('API_CALL_DELAY_US', 500000);
define('VERIFICATION_LOG_PATH', __DIR__ . '/pan-verification-log.csv');

$stats = [
  'total'        => 0,
  'verified'     => 0,
  'not_verified' => 0,
  'api_error'    => 0,
];

/**
 * Fetch contacts who:
 * - Have a PAN card saved on their Contact record
 * - PAN_API_Status = Not_Called (API not yet called)
 * - PAN_Verification_Status = Not_Verified
 */
function fetchContactsToVerify(): array {
  return Contact::get(FALSE)
    ->addSelect('id', 'display_name', 'PAN_Card_Details.PAN_Card_Number')
    ->addWhere('PAN_Card_Details.PAN_Card_Number', 'IS NOT EMPTY')
    ->addClause('OR',
      ['PAN_Card_Details.PAN_API_Status:name', '=', 'Not_Called'],
      ['PAN_Card_Details.PAN_API_Status', 'IS NULL']
    )
    ->addWhere('PAN_Card_Details.PAN_Verification_Status:name', '=', PanVerificationService::PAN_STATUS_NOT_VERIFIED)
    ->execute()
    ->getArrayCopy();
}

/**
 * Write the verification log CSV.
 */
function writeVerificationLog(array $log): void {
  $file = fopen(VERIFICATION_LOG_PATH, 'w');
  fputcsv($file, ['Contact ID', 'Contact Name', 'PAN Number', 'Result', 'Message']);
  foreach ($log as $row) {
    fputcsv($file, $row);
  }
  fclose($file);
}

/**
 * Run the bulk verification process.
 */
function runBulkVerification(array &$stats): void {
  $contacts = fetchContactsToVerify();
  $stats['total'] = count($contacts);
  echo "Contacts to process: {$stats['total']}\n\n";

  if (empty($contacts)) {
    echo "Nothing to process.\n";
    return;
  }

  $log = [];

  foreach ($contacts as $contact) {
    $contactId = $contact['id'];
    $pan       = $contact['PAN_Card_Details.PAN_Card_Number'];
    $name      = $contact['display_name'];

    echo "Processing contact ID $contactId ($name) — PAN: $pan ... ";

    $result = PanVerificationService::verifyPanViaApi($pan);

    if (!empty($result['api_error'])) {
      echo "API ERROR: {$result['message']}\n";
      $stats['api_error']++;
      $log[] = [$contactId, $name, $pan, 'API Error', $result['message']];
      // Do NOT mark as Called — leave as Not_Called so it is retried on next run.
      usleep(API_CALL_DELAY_US);
      continue;
    }

    if ($result['verified']) {
      PanVerificationService::saveContactPan($contactId, $pan, PanVerificationService::PAN_STATUS_VERIFIED);
      echo "VERIFIED ✓\n";
      $stats['verified']++;
      $log[] = [$contactId, $name, $pan, 'Verified', $result['message']];
    }
    else {
      // PAN_Verification_Status stays Not_Verified — no update needed.
      echo "NOT VERIFIED ✗ — {$result['message']}\n";
      $stats['not_verified']++;
      $log[] = [$contactId, $name, $pan, 'Not Verified', $result['message']];
    }

    // Mark API as called — contact will never be processed again on rerun.
    Contact::update(FALSE)
      ->addWhere('id', '=', $contactId)
      ->addValue('PAN_Card_Details.PAN_API_Status:name', 'Called')
      ->execute();

    usleep(API_CALL_DELAY_US);
  }

  writeVerificationLog($log);
  echo "\nLog saved to: " . realpath(dirname(VERIFICATION_LOG_PATH)) . "/pan-verification-log.csv\n";
}

// Run the process.
echo "=== Starting Bulk PAN Verification ===\n\n";
runBulkVerification($stats);

echo "\n=== Complete ===\n";
echo "Total processed : {$stats['total']}\n";
echo "Verified        : {$stats['verified']}\n";
echo "Not Verified    : {$stats['not_verified']}\n";
echo "API Errors      : {$stats['api_error']}\n";
