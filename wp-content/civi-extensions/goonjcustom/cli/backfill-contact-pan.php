<?php

/**
 * @file
 * CLI Script to backfill Contact PAN card numbers from FY 2025-2026 contributions.
 *
 * For each contact:
 * - If exactly 1 unique valid-format PAN found across contributions → save to Contact (Not Verified)
 * - If multiple different valid PANs found → log to conflict report CSV, do not save
 * - If no valid PAN found → skip and log
 *
 * Usage: cv scr wp-content/civi-extensions/goonjcustom/cli/backfill-contact-pan.php
 */

use Civi\Api4\Contact;
use Civi\Api4\Contribution;
use Civi\PanVerificationService;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

define('FY_START', '2025-04-01');
define('FY_END', '2026-03-31');
define('CONFLICT_REPORT_PATH', __DIR__ . '/pan-conflict-report.csv');

$stats = [
  'total_contacts' => 0,
  'saved'          => 0,
  'skipped_already_has_pan' => 0,
  'skipped_no_valid_pan'    => 0,
  'conflicts'      => 0,
];

/**
 * Fetch all contributions from FY 2025-2026 that have a PAN card number.
 * Returns contributions grouped by contact_id.
 */
function fetchContributionsByContact(): array {
  $contributions = Contribution::get(FALSE)
    ->addSelect('contact_id', 'Contribution_Details.PAN_Card_Number')
    ->addWhere('receive_date', '>=', FY_START)
    ->addWhere('receive_date', '<=', FY_END)
    ->addWhere('Contribution_Details.PAN_Card_Number', 'IS NOT EMPTY')
    ->execute();

  $grouped = [];
  foreach ($contributions as $contribution) {
    $contactId = $contribution['contact_id'];
    $pan = strtoupper(trim($contribution['Contribution_Details.PAN_Card_Number']));
    if (!isset($grouped[$contactId])) {
      $grouped[$contactId] = [];
    }
    $grouped[$contactId][] = $pan;
  }

  return $grouped;
}

/**
 * Write conflict/skipped records to a CSV file for the accounts team.
 * Each row contains contact ID, all PANs submitted, valid PANs, invalid PANs, and reason.
 */
function writeConflictReport(array $conflicts): void {
  $file = fopen(CONFLICT_REPORT_PATH, 'w');
  fputcsv($file, ['Contact ID', 'All PANs Submitted', 'Valid PANs', 'Invalid PANs', 'Reason']);
  foreach ($conflicts as $record) {
    fputcsv($file, [
      $record['contact_id'],
      implode(' | ', $record['all_pans']),
      implode(' | ', $record['valid_pans']),
      implode(' | ', $record['invalid_pans']),
      $record['reason'],
    ]);
  }
  fclose($file);
}

/**
 * Process all contacts and populate PAN card number from contributions.
 */
function backfillContactPan(array &$stats): void {
  $grouped = fetchContributionsByContact();
  $conflicts = [];

  echo "Total contacts with PAN in contributions: " . count($grouped) . "\n";
  $stats['total_contacts'] = count($grouped);

  foreach ($grouped as $contactId => $pans) {
    // Check if contact already has a PAN saved.
    $existing = PanVerificationService::getContactPan($contactId);
    if (!empty($existing['pan_number'])) {
      echo "Skipping contact ID $contactId: already has PAN saved.\n";
      $stats['skipped_already_has_pan']++;
      continue;
    }

    // Collect only valid-format PANs, deduplicate.
    $validPans = array_unique(array_filter($pans, function ($pan) {
      return PanVerificationService::isValidPanFormat($pan);
    }));

    $invalidPans = array_unique(array_filter($pans, function ($pan) {
      return !PanVerificationService::isValidPanFormat($pan);
    }));

    if (empty($validPans)) {
      echo "Skipping contact ID $contactId: no valid-format PAN found. Logged for manual review.\n";
      $conflicts[] = [
        'contact_id'   => $contactId,
        'all_pans'     => array_unique($pans),
        'valid_pans'   => [],
        'invalid_pans' => array_values($invalidPans),
        'reason'       => 'No valid format PAN found',
      ];
      $stats['skipped_no_valid_pan']++;
      continue;
    }

    if (count($validPans) > 1) {
      echo "Conflict for contact ID $contactId: multiple valid PANs found (" . implode(', ', $validPans) . "). Logged for manual review.\n";
      $conflicts[] = [
        'contact_id'   => $contactId,
        'all_pans'     => array_unique($pans),
        'valid_pans'   => array_values($validPans),
        'invalid_pans' => array_values($invalidPans),
        'reason'       => 'Multiple valid PANs found',
      ];
      $stats['conflicts']++;
      continue;
    }

    // Exactly 1 valid PAN — save to contact as Not Verified.
    $pan = reset($validPans);
    PanVerificationService::saveContactPan($contactId, $pan, PanVerificationService::PAN_STATUS_NOT_VERIFIED);
    echo "Saved PAN $pan to contact ID $contactId (Not Verified).\n";
    $stats['saved']++;
  }

  if (!empty($conflicts)) {
    writeConflictReport($conflicts);
    echo "\nConflict report saved to: " . realpath(dirname(CONFLICT_REPORT_PATH)) . "/pan-conflict-report.csv\n";
  }
}

// Run the process.
echo "=== Starting PAN Card Backfill (FY 2025-2026) ===\n\n";
backfillContactPan($stats);

echo "\n=== Backfill Complete ===\n";
echo "Total contacts processed : {$stats['total_contacts']}\n";
echo "PAN saved                : {$stats['saved']}\n";
echo "Already had PAN (skipped): {$stats['skipped_already_has_pan']}\n";
echo "No valid PAN (skipped)   : {$stats['skipped_no_valid_pan']}\n";
echo "Conflicts (manual review): {$stats['conflicts']}\n";
