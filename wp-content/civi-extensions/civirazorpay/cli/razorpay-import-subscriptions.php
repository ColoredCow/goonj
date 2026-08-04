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
 *     trxn_id exists.
 *   - NO email: contact create is minimal (name/email/phone, no group/sub_type);
 *     recur is_email_receipt=0; contribution sets no is_email_receipt / no
 *     Send_Receipt_via_WhatsApp; recur status = actual (never 'In Progress').
 *
 * Contact resolution at RUNTIME (not the CSV's baked id): email (MIN contact_id)
 * -> phone (MIN contact_id) -> create new. So ambiguous auto-takes the lowest id
 * and it self-corrects against the live DB.
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

// Mail backend banner (visibility).
$mb = Civi::settings()->get('mailing_backend');
echo "==== Razorpay MISSING-SUBSCRIPTIONS import ====\n";
echo ($DRY_RUN ? "[DRY RUN — nothing created]\n" : "[LIVE — creating contact/recur/contributions]\n");
echo "CATEGORY filter : " . ($CATEGORY ?: 'ALL') . "\n";
echo "LIMIT (subs)    : " . ($LIMIT ?: 'no cap') . "\n";
echo "Mail backend    : outBound_option=" . ($mb['outBound_option'] ?? '?') . "\n\n";

// ---- load payments grouped by subscription_id ----
function loadCsv($path) {
  $fh = fopen($path, 'r');
  $h = fgetcsv($fh);
  $rows = [];
  while (($r = fgetcsv($fh)) !== FALSE) {
    if (count($r) < count($h)) {
      continue;
    }
    $rows[] = array_combine($h, array_slice($r, 0, count($h)));
  }
  fclose($fh);
  return $rows;
}
$payRows = loadCsv($paysCsv);
$paysBySub = [];
foreach ($payRows as $p) {
  $paysBySub[$p['subscription_id']][] = $p;
}
$subRows = loadCsv($subsCsv);

$logNew = (!file_exists($LOG) || filesize($LOG) === 0);
$fh = fopen($LOG, 'a');
if ($logNew) {
  fputcsv($fh, ['time', 'subscription_id', 'category', 'mode', 'result', 'ref', 'detail']);
}
$ts = date('Y-m-d H:i:s');

/**
 * Resolve a contact by email (MIN id) then phone (MIN id). Null if none.
 */
function resolveContact($email, $phone) {
  if ($email) {
    $id = CRM_Core_DAO::singleValueQuery("SELECT MIN(contact_id) FROM civicrm_email WHERE email=%1", [1 => [$email, 'String']]);
    if ($id) {
      return (int) $id;
    }
  }
  if ($phone) {
    $id = CRM_Core_DAO::singleValueQuery("SELECT MIN(contact_id) FROM civicrm_phone WHERE phone=%1", [1 => [$phone, 'String']]);
    if ($id) {
      return (int) $id;
    }
  }
  return NULL;
}

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
    // 1. Recur idempotency by processor_id.
    $recurId = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_contribution_recur WHERE processor_id=%1 LIMIT 1", [1 => [$subId, 'String']]);
    $contactId = NULL;

    if ($recurId) {
      $recurExisting++;
      $contactId = (int) CRM_Core_DAO::singleValueQuery("SELECT contact_id FROM civicrm_contribution_recur WHERE id=%1", [1 => [$recurId, 'Integer']]);
    }
    else {
      // 2. Resolve or create contact.
      $contactId = resolveContact($email, $phone);
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
          }
          if ($phone) {
            Phone::create(FALSE)->addValue('contact_id', $contactId)->addValue('phone', $phone)->addValue('is_primary', TRUE)->execute();
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
          // NOTE: next_sched_contribution_date intentionally NOT set (NULL) +
          // status is Completed/Cancelled/Failed (never In Progress) so NO future
          // charge / reminder / email ever fires for these historical recurs.
          ->execute()->first();
        $recurId = (int) $r['id'];
        $newlyCreatedRecur = TRUE;
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
      $exists = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_contribution WHERE trxn_id LIKE %1 LIMIT 1", [1 => ['%' . $trxn, 'String']]);
      if ($exists) {
        $contribSkipped++;
        fputcsv($fh, [$ts, $subId, $s['category'], $DRY_RUN ? 'DRY' : 'LIVE', 'SKIP_EXISTS', $trxn, "cid={$exists}"]);
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
      $contribCreated++;
      fputcsv($fh, [$ts, $subId, $s['category'], 'LIVE', 'CONTRIB', $trxn, "cid={$cr['id']}"]);
    }

    // AFTER contributions: CiviCRM recomputes next_sched_contribution_date (and
    // may reset end_date) when payments are added to a recur. Force next_sched
    // NULL and restore the REAL end_date so NO future charge/reminder/email can
    // ever fire for these historical (Completed/Cancelled/Failed) recurs.
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
