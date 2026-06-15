<?php

/**
 * @file
 * Backfill PAN card on recurring contributions that originated from
 * "Imported from Razorpay" subscriptions.
 *
 * Why: subscriptions imported from Razorpay store identity details in a
 * different notes format than CiviCRM-created subscriptions, so the
 * subscription.charged webhook never captured the PAN for their recurring
 * payments. This reads the PAN straight from each subscription's Razorpay
 * notes and writes it onto the affected contributions.
 *
 * Scope (matches the agreed backfill set):
 *   - contribution belongs to a recur series that has at least one
 *     "Imported from Razorpay" contribution
 *   - contribution was created by the webhook (source IS NULL or '')
 *   - contribution's PAN custom field is empty
 *
 * Usage (DRY RUN by default — writes nothing):
 *   cv scr wp-content/civi-extensions/civirazorpay/cli/backfill-recurring-pan-from-razorpay.php
 *
 * To actually write:
 *   cv scr wp-content/civi-extensions/civirazorpay/cli/backfill-recurring-pan-from-razorpay.php -- --apply
 *
 * Output: backfill-recurring-pan-results.csv in the current working dir.
 */

use Civi\Api4\Contribution;
use Civi\Api4\ContributionRecur;
use Civi\Api4\PaymentProcessor;
use Civi\Payment\System;

civicrm_initialize();

// Backfilling 850+ contributions fires CiviCRM post-hooks on every update, which
// is memory-heavy. Raise the limit so the run does not die silently mid-way.
ini_set('memory_limit', '2G');

$APPLY = in_array('--apply', $argv ?? [], TRUE);
echo $APPLY
  ? "=== BACKFILL MODE: changes WILL be written ===\n"
  : "=== DRY RUN: nothing will be written. Review backfill-recurring-pan-results.csv first, then re-run with --apply ===\n";

/**
 * Init the live Razorpay API (same pattern as update-recurring-contact-info.php).
 */
$processorConfig = PaymentProcessor::get(FALSE)
  ->addWhere('payment_processor_type_id:name', '=', 'Razorpay')
  ->addWhere('is_test', '=', FALSE)
  ->execute()->single();
$processor = System::singleton()->getByProcessor($processorConfig);
$api = $processor->initializeApi();

/**
 * Pull every affected (recur_id, sub_id) once, then the blank contributions per recur.
 */
$rows = CRM_Core_DAO::executeQuery("
  SELECT c.id AS contribution_id, c.contact_id AS contact_id, c.contribution_recur_id AS recur_id, r.processor_id AS sub_id
  FROM civicrm_contribution c
  JOIN civicrm_contribution_recur r ON r.id = c.contribution_recur_id
  LEFT JOIN civicrm_value_contribution__31 cd ON cd.entity_id = c.id
  WHERE c.contribution_recur_id IN (
      SELECT rid FROM (
        SELECT DISTINCT contribution_recur_id rid FROM civicrm_contribution
        WHERE source = 'Imported from Razorpay' AND contribution_recur_id IS NOT NULL
      ) t)
    AND (c.source IS NULL OR c.source = '')
    AND (cd.pan_card_number_278 IS NULL OR cd.pan_card_number_278 IN ('', 'NA'))
  ORDER BY c.contribution_recur_id, c.id
");

// Group affected contributions by sub_id (keep each contribution's contact id).
$bySub = [];
while ($rows->fetch()) {
  if (empty($rows->sub_id)) {
    continue;
  }
  $bySub[$rows->sub_id]['recur_id'] = $rows->recur_id;
  $bySub[$rows->sub_id]['rows'][] = ['cid' => (int) $rows->contribution_id, 'contact_id' => (int) $rows->contact_id];
}

echo 'Affected subscriptions: ' . count($bySub) . "\n";
echo 'Affected contributions: ' . array_sum(array_map(fn($s) => count($s['rows']), $bySub)) . "\n\n";

$out = fopen('backfill-recurring-pan-results.csv', 'w');
fputcsv($out, ['sub_id', 'recur_id', 'pan_found', 'pan_value', 'pan_source', 'contact_id', 'contribution_id', 'status']);

$updated = $skippedNoPan = $errors = 0;

/**
 * Extract a PAN from a Razorpay notes array.
 * 1) explicit "PAN Card" / identity_type-style key
 * 2) fallback: any value that strictly matches the PAN format ABCDE1234F
 *    (deliberately ignores Aadhaar/Voter ID/DL/Passport values).
 */
function extract_pan_from_notes(array $notes): ?string {
  foreach ($notes as $k => $v) {
    if (is_scalar($v) && preg_match('/pan/i', (string) $k)) {
      $val = strtoupper(trim((string) $v));
      if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $val)) {
        return $val;
      }
    }
  }
  foreach ($notes as $v) {
    if (!is_scalar($v)) {
      continue;
    }
    $val = strtoupper(trim((string) $v));
    if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $val)) {
      return $val;
    }
  }
  return NULL;
}

foreach ($bySub as $subId => $info) {
  $recurId = $info['recur_id'];
  $contribRows = $info['rows'];

  try {
    $subscription = $api->subscription->fetch($subId);
    // Razorpay SDK returns entity objects; toArray() gives plain nested arrays.
    $subArray = $subscription->toArray();
    $notes = (array) ($subArray['notes'] ?? []);
  }
  catch (Exception $e) {
    echo "[ERROR] $subId : " . $e->getMessage() . "\n";
    foreach ($contribRows as $row) {
      fputcsv($out, [$subId, $recurId, 'ERROR', '', '', $row['contact_id'], $row['cid'], $e->getMessage()]);
    }
    $errors++;
    continue;
  }

  $pan = extract_pan_from_notes($notes);
  $panSource = 'razorpay_notes';

  // Fallback: if the Razorpay note has no valid PAN, try the PAN stored on this
  // series' "Imported from Razorpay" origin contribution in CiviCRM — but only
  // if it passes the strict PAN-format check (so junk values are never written).
  if (!$pan) {
    $crmPan = CRM_Core_DAO::singleValueQuery(
      "SELECT MAX(cd.pan_card_number_278) FROM civicrm_contribution c
       JOIN civicrm_value_contribution__31 cd ON cd.entity_id = c.id
       WHERE c.contribution_recur_id = %1 AND c.source = 'Imported from Razorpay'",
      [1 => [$recurId, 'Integer']]
    );
    $crmPan = strtoupper(trim((string) $crmPan));
    if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $crmPan)) {
      $pan = $crmPan;
      $panSource = 'crm_imported_origin';
    }
  }

  if (!$pan) {
    echo "[NO PAN] $subId (recur $recurId) — notes keys: " . implode(';', array_keys($notes)) . "\n";
    foreach ($contribRows as $row) {
      fputcsv($out, [$subId, $recurId, 'NO', '', '', $row['contact_id'], $row['cid'], 'skipped-no-valid-pan']);
    }
    $skippedNoPan += count($contribRows);
    continue;
  }

  foreach ($contribRows as $row) {
    $cid = $row['cid'];
    $contactId = $row['contact_id'];
    if ($APPLY) {
      try {
        Contribution::update(FALSE)
          ->addWhere('id', '=', $cid)
          ->addValue('Contribution_Details.PAN_Card_Number', $pan)
          ->execute();
        fputcsv($out, [$subId, $recurId, 'YES', $pan, $panSource, $contactId, $cid, 'updated']);
        $updated++;
      }
      catch (Exception $e) {
        fputcsv($out, [$subId, $recurId, 'YES', $pan, $panSource, $contactId, $cid, 'update-failed: ' . $e->getMessage()]);
        $errors++;
      }
    }
    else {
      fputcsv($out, [$subId, $recurId, 'YES', $pan, $panSource, $contactId, $cid, 'would-update']);
      $updated++;
    }
  }
  echo "[PAN $pan via $panSource] $subId (recur $recurId) — " . count($contribRows) . " contributions\n";
}

fclose($out);

echo "\n=== Summary ===\n";
echo ($APPLY ? 'Updated' : 'Would update') . ": $updated contributions\n";
echo "Skipped (no PAN in Razorpay notes): $skippedNoPan\n";
echo "Errors: $errors\n";
echo "Details written to: backfill-recurring-pan-results.csv\n";
