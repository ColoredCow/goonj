<?php

/**
 * @file
 * READ-ONLY: build an import-ready CSV of the MISSING recurring Razorpay
 * payments. For each subscription in the input CSV, fetch its paid invoices
 * from Razorpay, keep only the ones whose payment_id (pay_xxx) is NOT already
 * in CiviCRM (matched on contribution.trxn_id), and write one row per missing
 * payment with everything the import needs — INCLUDING the PAN for that
 * subscription (from the Razorpay subscription notes, else the contact's
 * existing CRM PAN). So the import can set PAN at create time (no 2nd script).
 *
 * Nothing is written to the DB. Only Razorpay GET + CiviCRM reads.
 * Creds come from the CiviCRM Razorpay payment processor row (live).
 *
 * Usage:
 *   cv scr .../razorpay-build-import-csv.php "/path/missing_sheet.csv" "/path/import_ready.csv"
 */

use Civi\Api4\PaymentProcessor;

@set_time_limit(0);
@ini_set('memory_limit', '1024M');
error_reporting(E_ALL & ~E_DEPRECATED);

if (!function_exists('civicrm_api3')) {
  fwrite(STDERR, "CiviCRM not bootstrapped. Run with: cv scr <path>\n");
  exit(1);
}

// Fixed values every imported recurring contribution must carry.
const FINANCIAL_TYPE   = 'Donation';
const PAYMENT_INSTR    = 'Credit Card';
const CONTRIB_STATUS   = 'Completed';
const SOURCE_TAG       = 'Imported from Razorpay';
const CAMPAIGN_ID      = 2;            // "Goonj it" (matches 5484 existing imported + website)

$argv = $_SERVER['argv'] ?? [];
$paths = [];
foreach ($argv as $a) {
  if (preg_match('/\.csv$/i', $a)) {
    $paths[] = $a;
  }
}
$inCsv  = $paths[0] ?? '';
$outCsv = $paths[1] ?? (rtrim(sys_get_temp_dir(), '/') . '/razorpay_import_ready.csv');
if (!$inCsv || !file_exists($inCsv)) {
  fwrite(STDERR, "Input (missing-sheet) CSV not found. Pass it as the first argument.\n");
  exit(1);
}

$proc = PaymentProcessor::get(FALSE)
  ->addWhere('payment_processor_type_id:name', '=', 'Razorpay')
  ->addWhere('is_test', '=', 0)->addWhere('is_active', '=', TRUE)
  ->execute()->single();
$key = $proc['user_name'];
$secret = $proc['password'];
echo "LIVE processor id={$proc['id']}\n";
echo "In : {$inCsv}\nOut: {$outCsv}\n\n";

/**
 * Read-only Razorpay GET.
 */
function rzpGet(string $path, array $q, string $key, string $secret): array {
  $url = 'https://api.razorpay.com/v1/' . $path . ($q ? ('?' . http_build_query($q)) : '');
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => TRUE, CURLOPT_USERPWD => "$key:$secret", CURLOPT_TIMEOUT => 60]);
  $b = curl_exec($ch);
  $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $d = json_decode($b, TRUE);
  if ($c !== 200) {
    throw new Exception("HTTP {$c} on {$path}: " . ($d['error']['description'] ?? $b));
  }
  return $d ?? [];
}

/**
 * Extract a valid PAN (ABCDE1234F) from a Razorpay notes array.
 * Prefer a key mentioning "pan"; else any value matching the strict PAN format
 * (so Aadhaar / voter id / etc. are ignored).
 */
function extractPan(array $notes): string {
  foreach ($notes as $k => $v) {
    if (is_scalar($v) && preg_match('/pan/i', (string) $k)) {
      $val = strtoupper(trim((string) $v));
      if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $val)) {
        return $val;
      }
    }
  }
  foreach ($notes as $v) {
    if (is_scalar($v)) {
      $val = strtoupper(trim((string) $v));
      if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $val)) {
        return $val;
      }
    }
  }
  return '';
}

// Preload ALL existing Razorpay pay_ ids from contribution trxn_id ONCE
// (one scan + in-memory hash) — avoids a leading-wildcard LIKE per payment.
$EXISTING = [];
$pdao = CRM_Core_DAO::executeQuery("SELECT trxn_id FROM civicrm_contribution WHERE trxn_id LIKE '%pay%'");
while ($pdao->fetch()) {
  if (preg_match_all('/pay_[A-Za-z0-9]+/', (string) $pdao->trxn_id, $m)) {
    foreach ($m[0] as $pid) {
      $EXISTING[$pid] = TRUE;
    }
  }
}
echo "preloaded existing pay_ ids: " . count($EXISTING) . "\n\n";

// Read subscription ids from the input sheet.
$in = fopen($inCsv, 'r');
$header = fgetcsv($in);
$subCol = NULL;
foreach ($header as $i => $h) {
  if (stripos($h, 'subscription_id') !== FALSE) {
    $subCol = $i;
  }
}
if ($subCol === NULL) {
  fwrite(STDERR, "No subscription_id column in input.\n");
  exit(1);
}
$subIds = [];
while (($row = fgetcsv($in)) !== FALSE) {
  $s = trim($row[$subCol] ?? '');
  if (preg_match('/^sub_[A-Za-z0-9]+$/', $s)) {
    $subIds[$s] = TRUE;
  }
}
fclose($in);
$subIds = array_keys($subIds);

$out = fopen($outCsv, 'w');
fputcsv($out, [
  'contact_id', 'contribution_recur_id', 'first_name', 'last_name', 'email',
  'trxn_id', 'invoice_id', 'total_amount', 'currency', 'receive_date',
  'financial_type', 'payment_instrument', 'contribution_status', 'source', 'campaign_id', 'pan',
]);

$subN = 0; $rowsOut = 0; $noRecur = []; $panFromNotes = 0; $panFromCrm = 0; $panNone = 0;
foreach ($subIds as $subId) {
  $subN++;
  // contact + recur for this subscription.
  $r = CRM_Core_DAO::executeQuery(
    "SELECT r.id AS recur_id, r.contact_id, ct.first_name, ct.last_name,
            (SELECT e.email FROM civicrm_email e WHERE e.contact_id=r.contact_id AND e.is_primary=1 ORDER BY e.id LIMIT 1) AS email
       FROM civicrm_contribution_recur r
       JOIN civicrm_contact ct ON ct.id=r.contact_id
      WHERE r.processor_id = %1 LIMIT 1",
    [1 => [$subId, 'String']]
  );
  if (!$r->fetch()) {
    $noRecur[] = $subId;
    continue;
  }
  $recurId = $r->recur_id; $contactId = $r->contact_id; $fn = $r->first_name; $ln = $r->last_name; $email = $r->email;

  // PAN for this subscription: 1) Razorpay subscription notes, 2) contact's existing CRM PAN.
  $pan = '';
  try {
    $sub = rzpGet("subscriptions/{$subId}", [], $key, $secret);
    $pan = extractPan((array) ($sub['notes'] ?? []));
  }
  catch (\Throwable $e) {
    // ignore; fall through to CRM fallback
  }
  if ($pan !== '') {
    $panFromNotes++;
  }
  else {
    $crmPan = CRM_Core_DAO::singleValueQuery(
      "SELECT MAX(cd.pan_card_number_278)
         FROM civicrm_value_contribution__31 cd
         JOIN civicrm_contribution c ON c.id = cd.entity_id
        WHERE c.contact_id = %1
          AND cd.pan_card_number_278 REGEXP '^[A-Z]{5}[0-9]{4}[A-Z]$'",
      [1 => [$contactId, 'Integer']]
    );
    if ($crmPan) {
      $pan = strtoupper(trim($crmPan));
      $panFromCrm++;
    }
    else {
      $panNone++;
    }
  }

  // paid invoices for this subscription.
  $skip = 0;
  do {
    $page = rzpGet('invoices', ['subscription_id' => $subId, 'count' => 100, 'skip' => $skip], $key, $secret);
    $items = $page['items'] ?? [];
    foreach ($items as $inv) {
      if (($inv['status'] ?? '') !== 'paid' || empty($inv['payment_id'])) {
        continue;
      }
      $payId = $inv['payment_id'];
      if (isset($EXISTING[$payId])) {
        continue;   // already in CiviCRM — skip (in-memory, same scope).
      }
      $amount = isset($inv['amount']) ? $inv['amount'] / 100 : '';
      $currency = $inv['currency'] ?? 'INR';
      $date = !empty($inv['paid_at']) ? date('Y-m-d H:i:s', $inv['paid_at']) : '';
      fputcsv($out, [
        $contactId, $recurId, $fn, $ln, $email,
        $payId, $inv['id'] ?? '', $amount, $currency, $date,
        FINANCIAL_TYPE, PAYMENT_INSTR, CONTRIB_STATUS, SOURCE_TAG, CAMPAIGN_ID, $pan,
      ]);
      $rowsOut++;
    }
    $skip += count($items);
    usleep(120000);
  } while (count($items) === 100);

  if ($subN % 20 === 0) {
    echo "processed {$subN}/" . count($subIds) . " subs, rows so far: {$rowsOut}\n";
  }
}
fclose($out);

echo "\n==== DONE ====\n";
echo "subscriptions read : " . count($subIds) . "\n";
echo "missing payments   : {$rowsOut}\n";
echo "PAN: from notes={$panFromNotes}  from CRM fallback={$panFromCrm}  none={$panNone}\n";
if ($noRecur) {
  echo "NO recur found for : " . implode(', ', $noRecur) . "\n";
}
echo "CSV                : {$outCsv}\n";
