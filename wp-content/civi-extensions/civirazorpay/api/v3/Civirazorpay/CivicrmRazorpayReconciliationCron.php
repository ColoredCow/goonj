<?php

/**
 * @file
 * Daily Razorpay -> CiviCRM reconciliation.
 *
 * For a given day it takes the payments Razorpay captured and checks each one
 * exists in CiviCRM as a Completed contribution of the same amount. If the
 * counts or amounts do not match, it emails the accounts team the list of
 * payment IDs (with contributor names) that did not reconcile. A day where
 * everything matches sends nothing.
 *
 * READ-ONLY: it only GETs Razorpay and reads CiviCRM. It never creates or
 * updates any payment, contribution or record.
 *
 * Manual run:
 *   cv api Civirazorpay.civicrm_razorpay_reconciliation_cron --user=devteam
 *   cv api Civirazorpay.civicrm_razorpay_reconciliation_cron date=2026-07-18 --user=devteam
 */

use Civi\Api4\PaymentProcessor;

// Who gets the mismatch alert.
// CiviCRM's mailer formats only ONE address cleanly in "To" (multiple get
// wrapped in a single <...> and then show up as Bcc). So keep one address in
// To and put the rest on Cc — everyone still receives it, no Bcc.
const RZP_RECON_TO = 'priyanka@goonj.org';
const RZP_RECON_CC = 'patel.amarjeet@goonj.org, tarun.joshi@coloredcow.in, shivangi@goonj.org';

// Attempts before giving up on a transient Razorpay/DB failure.
const RZP_RECON_MAX_RETRIES = 3;

// Bookmark of the last day fully reconciled (one auto-managed internal value —
// not a custom field, nothing to create in admin). The cron reconciles every
// day AFTER this up to yesterday, so a night it never ran is caught up next
// time and no day is silently skipped.
const RZP_RECON_STATE_KEY = 'razorpay_reconciliation_last_date';

// Most days one run will catch up at once, so a long outage does not hammer the
// Razorpay API in a single run — the rest follow on later runs.
const RZP_RECON_MAX_CATCHUP_DAYS = 30;

/**
 * Read-only comparison of one day's Razorpay captured payments against CiviCRM
 * Completed contributions. Never writes anything.
 */
class RazorpayReconciler {

  private string $apiKey;
  private string $apiSecret;
  private bool $isTest;
  private DateTime $start;
  private DateTime $end;

  /**
   * Pay_id => matched contribution info, preloaded once (see preload()).
   */
  private array $payMap = [];
  private bool $preloaded = FALSE;

  public function __construct(string $startDate, string $endDate, bool $isTest) {
    $this->isTest = $isTest;
    $this->start = new DateTime($startDate);
    $this->end = new DateTime($endDate);

    $processor = PaymentProcessor::get(FALSE)
      ->addWhere('payment_processor_type_id:name', '=', 'Razorpay')
      ->addWhere('is_test', '=', $isTest)
      ->addWhere('is_active', '=', TRUE)
      ->execute()->single();

    $this->apiKey = $processor['user_name'];
    $this->apiSecret = $processor['password'];
  }

  /**
   * Compare and return ['summary' => [...], 'issues' => [...]].
   * Throws on a hard failure so the caller can retry.
   */
  public function reconcile(): array {
    $captured = $this->fetchCapturedPayments();
    $this->preload();

    $issues = [];
    $matchedCount = 0;
    $matchedTotal = 0.0;
    $capturedTotal = 0.0;

    foreach ($captured as $p) {
      $payId = $p['id'];
      $amount = $p['amount'] / 100;
      $capturedTotal += $amount;
      $email = $p['email'] ?? '-';

      // Look the payment up anywhere in CiviCRM (any date) — so a payment
      // entered later under a different date still reconciles (date drift).
      $contribution = $this->findContribution($payId);

      if (!$contribution) {
        $issues[] = $this->issue('MISSING_IN_CIVI', $payId, $email, $amount,
          "{$payId} (Rs {$amount}, " . date('Y-m-d', $p['created_at']) . ", {$email}) is captured at Razorpay but has NO contribution in CiviCRM");
        continue;
      }

      $name = $contribution['contact_id.display_name'] ?? $email;
      $civiAmount = (float) $contribution['total_amount'];
      $status = $contribution['contribution_status_id:name'];

      if ($status !== 'Completed') {
        $issues[] = $this->issue('NOT_COMPLETED', $payId, $name, $amount,
          "{$payId} (Rs {$amount}) is captured at Razorpay but contribution {$contribution['id']} is '{$status}', not Completed");
        continue;
      }
      if (abs($civiAmount - $amount) > 0.01) {
        $issues[] = $this->issue('AMOUNT_MISMATCH', $payId, $name, $amount,
          "{$payId}: Razorpay Rs {$amount} vs CiviCRM contribution {$contribution['id']} Rs {$civiAmount}");
        continue;
      }

      $matchedCount++;
      $matchedTotal += $amount;
    }

    return [
      'window' => ['start' => $this->start->format('Y-m-d H:i:s'), 'end' => $this->end->format('Y-m-d H:i:s')],
      'summary' => [
        'rzp_captured_count' => count($captured),
        'rzp_captured_total' => round($capturedTotal, 2),
        'matched_in_civi_count' => $matchedCount,
        'matched_in_civi_total' => round($matchedTotal, 2),
        'reconciled' => empty($issues),
      ],
      'issues' => $issues,
    ];
  }

  /**
   *
   */
  private function issue(string $category, string $payId, string $contributor, $amount, string $detail): array {
    return [
      'category' => $category,
      'pay_id' => $payId,
      'contributor' => $contributor,
      'amount' => $amount,
      'detail' => $detail,
    ];
  }

  /**
   * GET a Razorpay API path. Read-only by construction.
   */
  private function razorpayGet(string $path, array $query = []): array {
    $url = 'https://api.razorpay.com/v1/' . $path . ($query ? ('?' . http_build_query($query)) : '');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_USERPWD => $this->apiKey . ':' . $this->apiSecret,
      CURLOPT_TIMEOUT => 60,
    ]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($body === FALSE) {
      throw new Exception("Razorpay API curl error on {$path}: {$curlErr}");
    }
    $decoded = json_decode($body, TRUE);
    if ($httpCode !== 200) {
      throw new Exception("Razorpay API HTTP {$httpCode} on {$path}: " . ($decoded['error']['description'] ?? $body));
    }
    return $decoded ?? [];
  }

  /**
   * All CAPTURED Razorpay payments created inside the window.
   */
  private function fetchCapturedPayments(): array {
    $out = [];
    $from = $this->start->getTimestamp();
    $to = $this->end->getTimestamp();
    $skip = 0;

    do {
      $page = $this->razorpayGet('payments', ['from' => $from, 'to' => $to, 'count' => 100, 'skip' => $skip]);
      $items = $page['items'] ?? [];
      foreach ($items as $p) {
        if (($p['status'] ?? '') === 'captured') {
          $out[$p['id']] = $p;
        }
      }
      $skip += count($items);
    } while (count($items) === 100);

    return $out;
  }

  /**
   * O(1) lookup of a payment id in the preloaded map (any date — date-drift safe).
   */
  private function findContribution(string $payId): ?array {
    return $this->payMap[$payId] ?? NULL;
  }

  /**
   * Preload every Razorpay pay_ id already in CiviCRM into memory ONCE (from
   * contribution.trxn_id and financial_trxn), so each payment is an O(1) lookup
   * instead of a per-payment leading-wildcard full scan. This keeps even a big
   * (monthly/yearly) window light: one scan up front, not N scans.
   */
  private function preload(): void {
    if ($this->preloaded) {
      return;
    }
    $isTest = $this->isTest ? 1 : 0;

    // contribution_status_id -> label (to report Pending/Cancelled/etc.).
    $statusLabels = [];
    $sd = CRM_Core_DAO::executeQuery(
      "SELECT ov.value, ov.label
         FROM civicrm_option_value ov
         JOIN civicrm_option_group og ON og.id = ov.option_group_id
        WHERE og.name = 'contribution_status'");
    while ($sd->fetch()) {
      $statusLabels[(int) $sd->value] = $sd->label;
    }

    // 1) pay_ ids stored in contribution.trxn_id ("pay_X" or "order_X,pay_Y").
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT c.id, c.trxn_id, c.total_amount, c.contribution_status_id st, ct.display_name name
         FROM civicrm_contribution c
         LEFT JOIN civicrm_contact ct ON ct.id = c.contact_id
        WHERE c.is_test = %1 AND c.trxn_id LIKE '%pay%'",
      [1 => [$isTest, 'Integer']]
    );
    while ($dao->fetch()) {
      if (preg_match_all('/pay_[A-Za-z0-9]+/', (string) $dao->trxn_id, $m)) {
        foreach ($m[0] as $pid) {
          $this->payMap[$pid] = [
            'id' => $dao->id,
            'contact_id.display_name' => $dao->name,
            'total_amount' => $dao->total_amount,
            'contribution_status_id:name' => $statusLabels[(int) $dao->st] ?? (string) $dao->st,
          ];
        }
      }
    }

    // 2) pay_ ids that live only in financial_trxn (older rows whose trxn_id
    // holds just the order id).
    $dao2 = CRM_Core_DAO::executeQuery(
      "SELECT eft.entity_id id, ft.trxn_id, c.total_amount, c.contribution_status_id st, ct.display_name name
         FROM civicrm_financial_trxn ft
         JOIN civicrm_entity_financial_trxn eft ON eft.financial_trxn_id = ft.id AND eft.entity_table = 'civicrm_contribution'
         JOIN civicrm_contribution c ON c.id = eft.entity_id AND c.is_test = %1
         LEFT JOIN civicrm_contact ct ON ct.id = c.contact_id
        WHERE ft.trxn_id LIKE 'pay%'",
      [1 => [$isTest, 'Integer']]
    );
    while ($dao2->fetch()) {
      if (preg_match('/pay_[A-Za-z0-9]+/', (string) $dao2->trxn_id, $m)) {
        if (!isset($this->payMap[$m[0]])) {
          $this->payMap[$m[0]] = [
            'id' => $dao2->id,
            'contact_id.display_name' => $dao2->name,
            'total_amount' => $dao2->total_amount,
            'contribution_status_id:name' => $statusLabels[(int) $dao2->st] ?? (string) $dao2->st,
          ];
        }
      }
    }

    $this->preloaded = TRUE;
  }

}

/**
 * API spec.
 *
 * @param array $spec
 */
function _civicrm_api3_civirazorpay_civicrm_razorpay_reconciliation_cron_spec(&$spec) {
  $spec['date'] = ['title' => 'End day to reconcile (Y-m-d), inclusive. Default: yesterday.', 'type' => CRM_Utils_Type::T_STRING];
  $spec['from_date'] = ['title' => 'Start day (Y-m-d). Overrides the saved marker — use for a manual/historical range.', 'type' => CRM_Utils_Type::T_STRING];
  $spec['is_test'] = ['title' => 'Reconcile the test processor.', 'type' => CRM_Utils_Type::T_BOOLEAN];
  $spec['send_email'] = ['title' => 'Send the mismatch email (default on for nightly, off for a manual from_date range).', 'type' => CRM_Utils_Type::T_BOOLEAN];
}

/**
 * Reconciliation cron: reconcile one day, email on mismatch.
 *
 * @param array $params
 *
 * @return array
 */
function civicrm_api3_civirazorpay_civicrm_razorpay_reconciliation_cron($params) {
  $isTest = !empty($params['is_test']) ? 1 : 0;

  $endDay = new DateTime($params['date'] ?? 'yesterday');
  $endDay->setTime(0, 0, 0);

  // Where to start: continue from the saved bookmark (catch-up), or an explicit
  // manual range that ignores the bookmark.
  if (!empty($params['from_date'])) {
    $startDay = new DateTime($params['from_date']);
    $useMarker = FALSE;
  }
  else {
    $marker = (string) Civi::settings()->get(RZP_RECON_STATE_KEY);
    $startDay = $marker !== '' ? (new DateTime($marker))->modify('+1 day') : clone $endDay;
    $useMarker = TRUE;
  }
  $startDay->setTime(0, 0, 0);

  // Nightly runs alert; a manual from_date range stays silent unless asked.
  $sendEmail = array_key_exists('send_email', $params) ? (bool) $params['send_email'] : $useMarker;

  $result = [
    'is_test' => $isTest,
    'range_start' => $startDay->format('Y-m-d'),
    'range_end' => $endDay->format('Y-m-d'),
    'days_reconciled' => 0,
    'total_issues' => 0,
    'stopped_early' => FALSE,
    'email_sent' => FALSE,
    'issues' => [],
  ];

  if ($startDay > $endDay) {
    Civi::log()->info('Razorpay reconciliation: already up to date, nothing to do', $result);
    return civicrm_api3_create_success($result, $params, 'Civirazorpay', 'civicrm_razorpay_reconciliation_cron');
  }

  // Cap catch-up per run.
  $lastDay = clone $endDay;
  if ((int) $startDay->diff($endDay)->days >= RZP_RECON_MAX_CATCHUP_DAYS) {
    $lastDay = (clone $startDay)->modify('+' . (RZP_RECON_MAX_CATCHUP_DAYS - 1) . ' day');
    $result['range_end'] = $lastDay->format('Y-m-d');
  }

  $allIssues = [];
  for ($d = clone $startDay; $d <= $lastDay; $d->modify('+1 day')) {
    $dayStr = $d->format('Y-m-d');
    try {
      $dayRes = _razorpay_reconcile_one_day($dayStr, (bool) $isTest);
    }
    catch (\Throwable $e) {
      // A day that fails after retries is NOT marked done — stop here so the
      // bookmark stays behind and the next run retries from this day.
      Civi::log()->error('Razorpay reconciliation: day FAILED after retries, stopping run', ['date' => $dayStr, 'error' => $e->getMessage()]);
      $result['stopped_early'] = TRUE;
      $result['failed_date'] = $dayStr;
      break;
    }

    $result['days_reconciled']++;
    foreach ($dayRes['issues'] as $issue) {
      $issue['date'] = $dayStr;
      $allIssues[] = $issue;
    }
    Civi::log()->info('Razorpay reconciliation: day done', ['date' => $dayStr, 'summary' => $dayRes['summary'], 'mismatches' => count($dayRes['issues'])]);

    // This day is reconciled (clean or mismatch) — move the bookmark forward.
    if ($useMarker) {
      Civi::settings()->set(RZP_RECON_STATE_KEY, $dayStr);
    }
  }

  $result['issues'] = $allIssues;
  $result['total_issues'] = count($allIssues);

  if ($allIssues && $sendEmail) {
    $result['email_sent'] = _razorpay_reconciliation_send_alert($allIssues, $result);
  }

  Civi::log()->info('Razorpay reconciliation: run complete', [
    'range_start' => $result['range_start'],
    'range_end' => $result['range_end'],
    'days_reconciled' => $result['days_reconciled'],
    'total_issues' => $result['total_issues'],
    'stopped_early' => $result['stopped_early'],
    'email_sent' => $result['email_sent'],
  ]);
  return civicrm_api3_create_success($result, $params, 'Civirazorpay', 'civicrm_razorpay_reconciliation_cron');
}

/**
 * Reconcile a single day, retrying transient failures with back-off.
 *
 * @param string $dayStr
 * @param bool $isTest
 *
 * @return array
 *
 * @throws \Throwable
 */
function _razorpay_reconcile_one_day(string $dayStr, bool $isTest): array {
  $lastError = NULL;
  for ($attempt = 1; $attempt <= RZP_RECON_MAX_RETRIES; $attempt++) {
    try {
      $reconciler = new RazorpayReconciler($dayStr . ' 00:00:00', $dayStr . ' 23:59:59', $isTest);
      return $reconciler->reconcile();
    }
    catch (\Throwable $e) {
      $lastError = $e;
      Civi::log()->warning('Razorpay reconciliation: attempt failed, will retry', ['date' => $dayStr, 'attempt' => $attempt, 'error' => $e->getMessage()]);
      if ($attempt < RZP_RECON_MAX_RETRIES) {
        sleep(min(60, 10 * (2 ** ($attempt - 1))));
      }
    }
  }
  throw $lastError;
}

/**
 * Send the mismatch alert (simple house-style HTML). One email lists every
 * unreconciled payment, each with its own date, since a run may cover more than
 * one day after a catch-up. Returns TRUE if accepted for delivery.
 *
 * @param array $issues
 *   Each: date, category, pay_id, contributor, amount, detail.
 * @param array $result
 *
 * @return bool
 */
function _razorpay_reconciliation_send_alert(array $issues, array $result): bool {
  [$fromName, $fromEmail] = CRM_Core_BAO_Domain::getNameAndEmail();
  $count = count($issues);

  $reasons = [
    'MISSING_IN_CIVI' => 'Captured at Razorpay but no contribution in CiviCRM',
    'NOT_COMPLETED' => 'Captured at Razorpay but the contribution is not Completed',
    'AMOUNT_MISMATCH' => 'Amount does not match between Razorpay and CiviCRM',
  ];

  $rows = '';
  $n = 0;
  foreach ($issues as $i) {
    $n++;
    $why = $reasons[$i['category']] ?? $i['category'];
    $rows .= "<p style='margin:0 0 12px'>"
      . "{$n}. <strong>{$i['pay_id']}</strong> &mdash; " . htmlspecialchars((string) $i['contributor'])
      . " &mdash; <strong>Rs " . htmlspecialchars((string) $i['amount']) . "</strong>"
      . " &mdash; " . htmlspecialchars((string) $i['date']) . "<br>"
      . "<span style='color:#555'>" . htmlspecialchars($why) . "</span>"
      . "</p>";
  }

  $html = "<p>Greetings from Goonj!</p>"
    . "<p>The daily Razorpay reconciliation found <strong>{$count} payment(s)</strong> that did not match CiviCRM "
    . "(checked {$result['range_start']} to {$result['range_end']}). Please verify the payment IDs below in Razorpay and CiviCRM:</p>"
    . $rows
    . "<p>Warm regards<br>Team Goonj</p>";

  $params = [
    'from' => "\"{$fromName}\" <{$fromEmail}>",
    'toEmail' => RZP_RECON_TO,
    'cc' => RZP_RECON_CC,
    'subject' => "Razorpay reconciliation: {$count} payment(s) need checking",
    'html' => $html,
  ];

  try {
    $sent = CRM_Utils_Mail::send($params);
    if (!$sent) {
      Civi::log()->error('Razorpay reconciliation: mailer returned failure');
    }
    return (bool) $sent;
  }
  catch (\Throwable $e) {
    Civi::log()->error('Razorpay reconciliation: sending alert threw', ['error' => $e->getMessage()]);
    return FALSE;
  }
}
