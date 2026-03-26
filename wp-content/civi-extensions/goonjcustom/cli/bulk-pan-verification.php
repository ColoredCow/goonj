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

$stats = [
  'total'       => 0,
  'verified'    => 0,
  'not_verified' => 0,
  'api_error'   => 0,
];

/**
 * Fetch contacts who have a PAN card saved on their profile and API has not been called yet.
 * No need to go through contributions — PAN was already populated by the backfill script.
 */
function fetchContactsToVerify(): array {
  return Contact::get(FALSE)
    ->addSelect('id', 'display_name', 'PAN_Card_Details.PAN_Card_Number')
    ->addWhere('PAN_Card_Details.PAN_Card_Number', 'IS NOT EMPTY')
    ->addWhere('PAN_Card_Details.PAN_API_Status:name', '=', 'Not_Called')
    ->execute()
    ->getArrayCopy();
}

/**
 * Run the bulk verification process.
 */
function runBulkVerification(array &$stats): void {
  $contacts = fetchContactsToVerify();
  $stats['total'] = count($contacts);
  echo "Contacts with unverified PAN to process: {$stats['total']}\n\n";

  if (empty($contacts)) {
    echo "Nothing to process.\n";
    return;
  }

  foreach ($contacts as $contact) {
    $contactId = $contact['id'];
    $pan = $contact['PAN_Card_Details.PAN_Card_Number'];
    $name = $contact['display_name'];

    echo "Processing contact ID $contactId ($name) — PAN: $pan ... ";

    $result = PanVerificationService::verifyPanViaApi($pan);

    if (!empty($result['api_error'])) {
      echo "API ERROR: {$result['message']}\n";
      $stats['api_error']++;
      // Do NOT mark as Called — leave as Not_Called so it is retried on next run.
      usleep(API_CALL_DELAY_US);
      continue;
    }

    if ($result['verified']) {
      PanVerificationService::saveContactPan($contactId, $pan, PanVerificationService::PAN_STATUS_VERIFIED);
      echo "VERIFIED ✓\n";
      $stats['verified']++;
    }
    else {
      // PAN_Verification_Status stays Not_Verified — no change needed.
      echo "NOT VERIFIED ✗ — {$result['message']}\n";
      $stats['not_verified']++;
    }

    // Mark API as called — contact will never be processed again on rerun.
    Contact::update(FALSE)
      ->addWhere('id', '=', $contactId)
      ->addValue('PAN_Card_Details.PAN_API_Status:name', 'Called')
      ->execute();

    // Delay between API calls to respect rate limits.
    usleep(API_CALL_DELAY_US);
  }
}

// Run the process.
echo "=== Starting Bulk PAN Verification (FY 2025-2026) ===\n\n";
runBulkVerification($stats);

echo "\n=== Bulk Verification Complete ===\n";
echo "Total processed : {$stats['total']}\n";
echo "Verified        : {$stats['verified']}\n";
echo "Not Verified    : {$stats['not_verified']}\n";
echo "API Errors      : {$stats['api_error']}\n";
