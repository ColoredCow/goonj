<?php

/**
 * @file
 * Import the MISSING recurring Razorpay payments listed in an import-ready CSV
 * (built by razorpay-build-import-csv.php) as Completed CiviCRM contributions.
 *
 * SAFETY:
 *   - DRY_RUN=true (default): logs what WOULD be created, writes NOTHING.
 *   - LIMIT=N: create at most N NEW contributions this run (batching: 1, 10, ...).
 *             LIMIT=0 / unset = no cap (all remaining).
 *   - Idempotent: a payment whose trxn_id already exists is SKIPPED (no dup).
 *   - NO email: uses API4 Contribution.create (does not send a receipt) and does
 *               NOT enable is_email_receipt. Verify with maildev on local first.
 *   - Does NOT set invoice_number (invoicing is off; must stay blank).
 *
 * Env vars:
 *   DRY_RUN=false   -> actually create (anything else/unset = safe dry-run)
 *   LIMIT=1         -> create at most 1 this run
 *   LOG=/path.csv   -> action log (default /tmp/razorpay_import_log.csv)
 *
 * Usage:
 *   cv scr .../razorpay-import-missing.php "/path/import_ready.csv"
 *   LIMIT=1 cv scr .../razorpay-import-missing.php "/path/import_ready.csv"          (dry-run, 1)
 *   DRY_RUN=false LIMIT=1 cv scr .../razorpay-import-missing.php "/path/import_ready.csv"  (LIVE, 1)
 */

use Civi\Api4\Contribution;

@set_time_limit(0);
@ini_set('memory_limit', '1024M');
error_reporting(E_ALL & ~E_DEPRECATED);

if (!function_exists('civicrm_api3')) {
  fwrite(STDERR, "CiviCRM not bootstrapped. Run with: cv scr <path>\n");
  exit(1);
}

$envDry  = getenv('DRY_RUN');
$DRY_RUN = ($envDry === FALSE) ? TRUE
  : !in_array(strtolower(trim($envDry)), ['false', '0', 'no', 'off'], TRUE);
$LIMIT   = (int) (getenv('LIMIT') ?: 0);          // 0 = no cap
$LOG     = getenv('LOG') ?: '/tmp/razorpay_import_log.csv';

$argv = $_SERVER['argv'] ?? [];
$inCsv = '';
foreach ($argv as $a) {
  if (preg_match('/\.csv$/i', $a)) {
    $inCsv = $a;
  }
}
if (!$inCsv || !file_exists($inCsv)) {
  fwrite(STDERR, "Import CSV not found. Pass it as the argument.\n");
  exit(1);
}

// Show where mail WOULD go, so we never import blind.
$mb = Civi::settings()->get('mailing_backend');
$mailWhere = ($mb['outBound_option'] ?? '') == 0
  ? "SMTP {$mb['smtpServer']}:{$mb['smtpPort']}"
  : ("outBound_option=" . ($mb['outBound_option'] ?? '?'));
echo "==== Razorpay missing-payment import ====\n";
echo ($DRY_RUN ? "[DRY RUN — nothing will be created]\n" : "[LIVE — creating contributions]\n");
echo "LIMIT (max new this run) : " . ($LIMIT ?: 'no cap') . "\n";
echo "Mail backend             : {$mailWhere}\n";
echo "Input CSV                : {$inCsv}\n";
echo "Log                      : {$LOG}\n\n";

$fh = fopen($LOG, 'a');
fputcsv($fh, [date('Y-m-d H:i:s'), 'trxn_id', 'contact_id', 'recur_id', 'amount', 'receive_date', 'result', 'detail']);

$in = fopen($inCsv, 'r');
$header = fgetcsv($in);
$col = array_flip(array_map('trim', $header));
$need = ['contact_id', 'contribution_recur_id', 'trxn_id', 'invoice_id', 'total_amount', 'currency', 'receive_date', 'campaign_id'];
foreach ($need as $c) {
  if (!isset($col[$c])) {
    fwrite(STDERR, "Input CSV missing column: {$c}\n");
    exit(1);
  }
}

$created = $skipped = $errors = 0;
$ts = date('Y-m-d H:i:s');
while (($row = fgetcsv($in)) !== FALSE) {
  if (count($row) < count($header)) {
    continue;
  }
  $trxn    = trim($row[$col['trxn_id']]);
  $contact = (int) $row[$col['contact_id']];
  $recur   = (int) $row[$col['contribution_recur_id']];
  $invoice = trim($row[$col['invoice_id']]);
  $amount  = $row[$col['total_amount']];
  $currency = trim($row[$col['currency']]) ?: 'INR';
  $recv    = trim($row[$col['receive_date']]);
  $campaign = (int) $row[$col['campaign_id']];
  $pan     = isset($col['pan']) ? strtoupper(trim($row[$col['pan']])) : '';

  if (!preg_match('/^pay_[A-Za-z0-9]+$/', $trxn)) {
    continue;
  }

  // Idempotent: skip if this payment already exists anywhere.
  $exists = CRM_Core_DAO::singleValueQuery(
    "SELECT id FROM civicrm_contribution WHERE trxn_id LIKE %1 LIMIT 1",
    [1 => ['%' . $trxn, 'String']]
  );
  if ($exists) {
    $skipped++;
    fputcsv($fh, [$ts, $trxn, $contact, $recur, $amount, $recv, 'SKIP_EXISTS', "cid={$exists}"]);
    continue;
  }

  if ($LIMIT > 0 && $created >= $LIMIT) {
    break;   // batch cap reached (only counts NEW creates).
  }

  if ($DRY_RUN) {
    $created++;
    fputcsv($fh, [$ts, $trxn, $contact, $recur, $amount, $recv, 'DRY_RUN', 'would create']);
    continue;
  }

  try {
    $create = Contribution::create(FALSE)
      ->addValue('contact_id', $contact)
      ->addValue('contribution_recur_id', $recur)
      ->addValue('financial_type_id:name', 'Donation')
      ->addValue('payment_instrument_id:name', 'Credit Card')
      ->addValue('receive_date', $recv)
      ->addValue('total_amount', $amount)
      ->addValue('currency', $currency)
      ->addValue('trxn_id', $trxn)
      ->addValue('invoice_id', $invoice)
      ->addValue('contribution_status_id:name', 'Completed')
      ->addValue('source', 'Imported from Razorpay')
      ->addValue('campaign_id', $campaign ?: 2);
    // PAN (Contribution_Details.PAN_Card_Number) — only if a valid PAN is present.
    if ($pan !== '' && preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
      $create->addValue('Contribution_Details.PAN_Card_Number', $pan);
    }
    // NOTE: intentionally NOT setting invoice_number or is_email_receipt.
    $r = $create->execute();
    $newId = $r->first()['id'] ?? '?';
    $created++;
    fputcsv($fh, [$ts, $trxn, $contact, $recur, $amount, $recv, 'CREATED', "cid={$newId}"]);
    echo "CREATED cid={$newId} {$trxn} Rs{$amount} {$recv}\n";
  }
  catch (\Throwable $e) {
    $errors++;
    fputcsv($fh, [$ts, $trxn, $contact, $recur, $amount, $recv, 'ERROR', $e->getMessage()]);
    echo "ERROR {$trxn}: " . $e->getMessage() . "\n";
  }
}
fclose($in);
fclose($fh);

echo "\n==== SUMMARY ====\n";
echo ($DRY_RUN ? "[DRY RUN]\n" : "[LIVE]\n");
echo ($DRY_RUN ? "would create : " : "created      : ") . $created . "\n";
echo "skipped(exist) : {$skipped}\n";
echo "errors         : {$errors}\n";
echo "log            : {$LOG}\n";
