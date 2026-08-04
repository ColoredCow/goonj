<?php

/**
 * @file
 * Import ENTIRELY-MISSING Razorpay subscriptions into CiviCRM: create the
 * Contact (resolve or create) + ContributionRecur + all paid Contributions.
 * Reads two CSVs produced by the read-only Phase-1 build:
 *   - subscriptions CSV (recur params + email/phone/name per subscription)
 *   - payments CSV (one row per paid invoice, full contribution fields)
 *
 * SAFETY:
 *   - DRY_RUN=true (default): logs what WOULD happen, writes NOTHING.
 *   - LIMIT=N: process at most N NEW subscriptions this run (batching).
 *   - CATEGORY=single_match|new_contact|ambiguous: only that category.
 *   - Idempotent: recur skipped if processor_id exists; contribution skipped if
 *     trxn_id exists (both checked from a preloaded in-memory set).
 *   - NO email: contact create is minimal (name/email/phone, no group/sub_type);
 *     recur is_email_receipt=0; contribution sets no is_email_receipt / no
 *     Send_Receipt_via_WhatsApp; recur status = actual (never 'In Progress');
 *     next_sched_contribution_date forced NULL.
 *   - LOW LOAD: all existing state is preloaded once (a few batched, indexed
 *     queries); the loop does ZERO per-row DB lookups (no full-table scans).
 *
 * Contact resolution (in-memory): email (MIN contact_id) -> phone (MIN
 * contact_id) -> create new. Ambiguous auto-takes the lowest id.
 *
 * Usage:
 *   cv scr .../razorpay-import-subscriptions.php "/path/subs.csv" "/path/pays.csv"
 *   DRY_RUN=false LIMIT=1 CATEGORY=new_contact cv scr ... subs.csv pays.csv
 */

use Civi\Api4\Contribution;
use Civi\Api4\ContributionRecur;
use Civi\Api4\Individual;
use Civi\Api4\Email;
use Civi\Api4\Phone;
use Civi\Api4\PaymentProcessor;

@set_time_limit(0);
@ini_set('memory_limit', '2048M');
error_reporting(E_ALL & ~E_DEPRECATED);

if (!function_exists('civicrm_api3')) {
  fwrite(STDERR, "CiviCRM not bootstrapped. Run with: cv scr <path>\n");
  exit(1);
}

$envDry  = getenv('DRY_RUN');
$DRY_RUN = ($envDry === FALSE) ? TRUE
  : !in_array(strtolower(trim($envDry)), ['false', '0', 'no', 'off'], TRUE);
$LIMIT    = (int) (getenv('LIMIT') ?: 0);
$CATEGORY = trim((string) getenv('CATEGORY'));
$LOG      = getenv('LOG') ?: '/tmp/razorpay_subs_import_log.csv';

$argv = $_SERVER['argv'] ?? [];
$csvs = [];
foreach ($argv as $a) {
  if (preg_match('/\.csv$/i', $a)) {
    $csvs[] = $a;
  }
}
if (count($csvs) < 2) {
  fwrite(STDERR, "Need TWO CSVs: subscriptions and payments.\n");
  exit(1);
}
[$subsCsv, $paysCsv] = $csvs;

$proc = PaymentProcessor::get(FALSE)
  ->addWhere('payment_processor_type_id:name', '=', 'Razorpay')
  ->addWhere('is_test', '=', 0)->addWhere('is_active', '=', TRUE)
  ->execute()->single();
$processorID = $proc['id'];

$mb = Civi::settings()->get('mailing_backend');
echo "==== Razorpay MISSING-SUBSCRIPTIONS import ====\n";
echo ($DRY_RUN ? "[DRY RUN — nothing created]\n" : "[LIVE — creating contact/recur/contributions]\n");
echo "CATEGORY filter : " . ($CATEGORY ?: 'ALL') . "\n";
echo "LIMIT (subs)    : " . ($LIMIT ?: 'no cap') . "\n";
echo "Mail backend    : outBound_option=" . ($mb['outBound_option'] ?? '?') . "\n\n";

/**
 * Load a CSV into an array of assoc rows (header-keyed).
 */
function loadCsv($path) {
  $fh = fopen($path, 'r');
  if ($fh === FALSE) {
    fwrite(STDERR, "Cannot read CSV: {$path}\n");
    exit(1);
  }
  $h = fgetcsv($fh);
  if ($h === FALSE) {
    fwrite(STDERR, "Empty CSV: {$path}\n");
    exit(1);
  }
  $rows = [];
  $skipped = 0;
  while (($r = fgetcsv($fh)) !== FALSE) {
    if (count($r) < count($h)) {
      $skipped++;
      continue;
    }
    $rows[] = array_combine($h, array_slice($r, 0, count($h)));
  }
  fclose($fh);
  if ($skipped > 0) {
    echo "WARNING: {$skipped} malformed row(s) skipped in {$path}\n";
  }
  return $rows;
}

$payRows = loadCsv($paysCsv);
$paysBySub = [];
foreach ($payRows as $p) {
  $paysBySub[$p['subscription_id']][] = $p;
}
$subRows = loadCsv($subsCsv);

// ---------------------------------------------------------------------------
// PRELOAD existing state ONCE (a handful of batched, indexed queries) so the
// per-subscription / per-payment loop does ZERO per-row DB lookups. This avoids
// ~1400 leading-wildcard full-table scans on prod (which would load the DB /
// slow the live site).
// ---------------------------------------------------------------------------
$EXISTING_PAY = [];     // pay_id => TRUE (already-imported payments)
$pdao = CRM_Core_DAO::executeQuery("SELECT trxn_id FROM civicrm_contribution WHERE trxn_id LIKE '%pay%'");
while ($pdao->fetch()) {
  if (preg_match_all('/pay_[A-Za-z0-9]+/', (string) $pdao->trxn_id, $m)) {
    foreach ($m[0] as $pid) {
      $EXISTING_PAY[$pid] = TRUE;
    }
  }
}
$EXISTING_RECUR = [];   // processor_id (sub_XXX) => ['id'=>recur_id,'contact'=>contact_id]
$rdao = CRM_Core_DAO::executeQuery("SELECT id, processor_id, contact_id FROM civicrm_contribution_recur WHERE processor_id LIKE 'sub\\_%'");
while ($rdao->fetch()) {
  $EXISTING_RECUR[$rdao->processor_id] = ['id' => (int) $rdao->id, 'contact' => (int) $rdao->contact_id];
}
$allEmails = [];
$allPhones = [];
foreach ($subRows as $s) {
  if (trim($s['email']) !== '') {
    $allEmails[trim($s['email'])] = 1;
  }
  if (trim($s['phone']) !== '') {
    $allPhones[trim($s['phone'])] = 1;
  }
}
$EMAIL_MIN = [];
$PHONE_MIN = [];
$mkIn = function ($arr) {
  return implode(',', array_map(fn($v) => "'" . CRM_Core_DAO::escapeString($v) . "'", array_keys($arr)));
};
if ($allEmails) {
  $d = CRM_Core_DAO::executeQuery("SELECT email, MIN(contact_id) mn FROM civicrm_email WHERE email IN (" . $mkIn($allEmails) . ") GROUP BY email");
  while ($d->fetch()) {
    $EMAIL_MIN[$d->email] = (int) $d->mn;
  }
}
if ($allPhones) {
  $d = CRM_Core_DAO::executeQuery("SELECT phone, MIN(contact_id) mn FROM civicrm_phone WHERE phone IN (" . $mkIn($allPhones) . ") GROUP BY phone");
  while ($d->fetch()) {
    $PHONE_MIN[$d->phone] = (int) $d->mn;
  }
}
echo "preloaded: pay_ids=" . count($EXISTING_PAY) . " recurs=" . count($EXISTING_RECUR) . " emails=" . count($EMAIL_MIN) . " phones=" . count($PHONE_MIN) . "\n\n";

$logNew = (!file_exists($LOG) || filesize($LOG) === 0);
$fh = fopen($LOG, 'a');
if ($logNew) {
  fputcsv($fh, ['time', 'subscription_id', 'category', 'mode', 'result', 'ref', 'detail']);
}
$ts = date('Y-m-d H:i:s');

$subDone = 0; $recurCreated = 0; $recurExisting = 0; $contactsCreated = 0; $contactsResolved = 0;
$contribCreated = 0; $contribSkipped = 0; $errors = 0;

foreach ($subRows as $s) {
  if ($CATEGORY && $s['category'] !== $CATEGORY) {
    continue;
  }
  $subId = $s['subscription_id'];
  $email = trim($s['email']);
  $phone = trim($s['phone']);
  $pays  = $paysBySub[$subId] ?? [];
  if (!$pays) {
    continue;   // no paid payments -> nothing to import
  }

  if ($LIMIT > 0 && $subDone >= $LIMIT) {
    break;
  }
  $subDone++;
  $newlyCreatedRecur = FALSE;

  try {
    // 1. Recur idempotency by processor_id (preloaded in-memory).
    $recurId = NULL;
    $contactId = NULL;
    if (isset($EXISTING_RECUR[$subId])) {
      $recurExisting++;
      $recurId = $EXISTING_RECUR[$subId]['id'];
      $contactId = $EXISTING_RECUR[$subId]['contact'];
    }
    else {
      // 2. Resolve contact (in-memory: email MIN id -> phone MIN id) or create.
      $contactId = ($email !== '' && isset($EMAIL_MIN[$email])) ? $EMAIL_MIN[$email]
        : (($phone !== '' && isset($PHONE_MIN[$phone])) ? $PHONE_MIN[$phone] : NULL);
      if ($contactId) {
        $contactsResolved++;
      }
      else {
        if ($DRY_RUN) {
          $contactId = 0;   // placeholder
        }
        else {
          $c = Individual::create(FALSE)
            ->addValue('first_name', $s['name'] !== '' ? explode(' ', $s['name'])[0] : '')
            ->addValue('last_name', (strpos($s['name'], ' ') !== FALSE) ? trim(substr($s['name'], strpos($s['name'], ' ') + 1)) : '')
            ->addValue('source', 'Razorpay subscription import')
            ->execute()->first();
          $contactId = (int) $c['id'];
          if ($email) {
            Email::create(FALSE)->addValue('contact_id', $contactId)->addValue('email', $email)->addValue('is_primary', TRUE)->execute();
            $EMAIL_MIN[$email] = $contactId;   // a later sub with same email reuses it
          }
          if ($phone) {
            Phone::create(FALSE)->addValue('contact_id', $contactId)->addValue('phone', $phone)->addValue('is_primary', TRUE)->execute();
            if (!isset($PHONE_MIN[$phone])) {
              $PHONE_MIN[$phone] = $contactId;
            }
          }
        }
        $contactsCreated++;
      }

      // 3. Create the recur.
      if ($DRY_RUN) {
        $recurId = 0;
      }
      else {
        $r = ContributionRecur::create(FALSE)
          ->addValue('contact_id', $contactId)
          ->addValue('amount', $s['recur_amount'])
          ->addValue('currency', $s['currency'] ?: 'INR')
          ->addValue('frequency_unit', $s['frequency_unit'] ?: 'month')
          ->addValue('frequency_interval', (int) ($s['frequency_interval'] ?: 1))
          ->addValue('installments', $s['installments'] !== '' ? (int) $s['installments'] : NULL)
          ->addValue('start_date', $s['start_date'] ?: NULL)
          // create_date = the subscription's real Razorpay created_at (NOT today).
          ->addValue('create_date', $s['create_date'] ?: ($s['start_date'] ?: date('Y-m-d H:i:s')))
          ->addValue('modified_date', date('Y-m-d H:i:s'))
          // end_date = when the subscription actually ended (completed/cancelled/halted).
          ->addValue('end_date', !empty($s['end_date']) ? $s['end_date'] : NULL)
          ->addValue('processor_id', $subId)
          ->addValue('is_test', 0)
          ->addValue('contribution_status_id:name', $s['recur_status_civi'])
          ->addValue('financial_type_id:name', 'Donation')
          ->addValue('payment_instrument_id:name', 'Credit Card')
          ->addValue('payment_processor_id', $processorID)
          ->addValue('campaign_id', 2)
          ->addValue('is_email_receipt', 0)
          // next_sched forced NULL below (after contributions) + non-In-Progress
          // status => NO future charge / reminder / email for these historical recurs.
          ->execute()->first();
        $recurId = (int) $r['id'];
        $newlyCreatedRecur = TRUE;
        $EXISTING_RECUR[$subId] = ['id' => $recurId, 'contact' => $contactId];
      }
      $recurCreated++;
    }

    fputcsv($fh, [$ts, $subId, $s['category'], $DRY_RUN ? 'DRY' : 'LIVE', 'RECUR', $recurId ?: '(dry)', "contact={$contactId}"]);

    // 4. Contributions for each paid payment.
    foreach ($pays as $p) {
      $trxn = trim($p['trxn_id']);
      if (!preg_match('/^pay_[A-Za-z0-9]+$/', $trxn)) {
        continue;
      }
      if (isset($EXISTING_PAY[$trxn])) {
        $contribSkipped++;
        fputcsv($fh, [$ts, $subId, $s['category'], $DRY_RUN ? 'DRY' : 'LIVE', 'SKIP_EXISTS', $trxn, 'already-in-db']);
        continue;
      }
      if ($DRY_RUN) {
        $contribCreated++;
        fputcsv($fh, [$ts, $subId, $s['category'], 'DRY', 'WOULD_CREATE', $trxn, $p['total_amount']]);
        continue;
      }
      $cr = Contribution::create(FALSE)
        ->addValue('contact_id', $contactId)
        ->addValue('contribution_recur_id', $recurId)
        ->addValue('financial_type_id:name', 'Donation')
        ->addValue('payment_instrument_id:name', 'Credit Card')
        ->addValue('receive_date', $p['receive_date'])
        ->addValue('total_amount', $p['total_amount'])
        ->addValue('currency', $p['currency'] ?: 'INR')
        ->addValue('trxn_id', $trxn)
        ->addValue('contribution_status_id:name', 'Completed')
        ->addValue('source', 'Imported from Razorpay')
        ->addValue('campaign_id', 2)
        // NO invoice_number, NO is_email_receipt, NO Send_Receipt_via_WhatsApp.
        ->execute()->first();
      $EXISTING_PAY[$trxn] = TRUE;   // so a re-run / same run never duplicates it
      $contribCreated++;
      fputcsv($fh, [$ts, $subId, $s['category'], 'LIVE', 'CONTRIB', $trxn, "cid={$cr['id']}"]);
    }

    // AFTER contributions: CiviCRM recomputes next_sched_contribution_date (and
    // overrides end_date=now for Completed status) when payments are added to a
    // recur. Force next_sched NULL and restore the REAL end_date so NO future
    // charge/reminder/email can ever fire for these historical recurs.
    if ($newlyCreatedRecur && !$DRY_RUN && $recurId) {
      $endSql = !empty($s['end_date']) ? "'" . CRM_Core_DAO::escapeString($s['end_date']) . "'" : 'NULL';
      CRM_Core_DAO::executeQuery(
        "UPDATE civicrm_contribution_recur SET next_sched_contribution_date = NULL, end_date = {$endSql} WHERE id = %1",
        [1 => [$recurId, 'Integer']]
      );
    }
  }
  catch (\Throwable $e) {
    $errors++;
    fputcsv($fh, [$ts, $subId, $s['category'] ?? '', $DRY_RUN ? 'DRY' : 'LIVE', 'ERROR', '', $e->getMessage()]);
    echo "ERROR {$subId}: " . $e->getMessage() . "\n";
  }
}
fclose($fh);

echo "\n==== SUMMARY (" . ($DRY_RUN ? 'DRY RUN' : 'LIVE') . ") ====\n";
echo "subscriptions processed : {$subDone}\n";
echo "recur created / existing : {$recurCreated} / {$recurExisting}\n";
echo "contacts resolved / created : {$contactsResolved} / {$contactsCreated}\n";
echo ($DRY_RUN ? "contributions would-create : " : "contributions created : ") . $contribCreated . "\n";
echo "contributions skipped(exist): {$contribSkipped}\n";
echo "errors : {$errors}\n";
echo "log : {$LOG}\n";
