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

use Civi\Api4\Contribution;
use Civi\Api4\PaymentProcessor;

// Who gets the mismatch alert
const RZP_RECON_TO = 'priyanka@goonj.org, accounts@goonj.org';
const RZP_RECON_CC = 'tarun.joshi@coloredcow.in';

// Attempts before giving up on a transient Razorpay/DB failure.
const RZP_RECON_MAX_RETRIES = 3;

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
   * Find a contribution carrying this payment id (in trxn_id, or its
   * financial_trxn rows), regardless of its receive_date.
   */
  private function findContribution(string $payId): ?array {
    $found = Contribution::get(FALSE)
      ->addSelect('id', 'contact_id.display_name', 'total_amount', 'contribution_status_id:name')
      ->addWhere('trxn_id', 'LIKE', '%' . $payId . '%')
      ->addWhere('is_test', '=', $this->isTest)
      ->execute()->first();
    if ($found) {
      return $found;
    }

    $dao = CRM_Core_DAO::executeQuery(
      "SELECT eft.entity_id
         FROM civicrm_financial_trxn ft
         JOIN civicrm_entity_financial_trxn eft ON eft.financial_trxn_id = ft.id
        WHERE ft.trxn_id LIKE %1 AND eft.entity_table = 'civicrm_contribution'
        LIMIT 1",
      [1 => ['%' . $payId . '%', 'String']]
    );
    if ($dao->fetch()) {
      return Contribution::get(FALSE)
        ->addSelect('id', 'contact_id.display_name', 'total_amount', 'contribution_status_id:name')
        ->addWhere('id', '=', (int) $dao->entity_id)
        ->execute()->first();
    }
    return NULL;
  }

}

/**
 * API spec.
 *
 * @param array $spec
 */
function _civicrm_api3_civirazorpay_civicrm_razorpay_reconciliation_cron_spec(&$spec) {
  $spec['date'] = ['title' => 'Day to reconcile (Y-m-d). Default: yesterday.', 'type' => CRM_Utils_Type::T_STRING];
  $spec['is_test'] = ['title' => 'Reconcile the test processor.', 'type' => CRM_Utils_Type::T_BOOLEAN];
  $spec['send_email'] = ['title' => 'Send the mismatch email (default on).', 'type' => CRM_Utils_Type::T_BOOLEAN];
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
  $sendEmail = array_key_exists('send_email', $params) ? (bool) $params['send_email'] : TRUE;
  $day = (new DateTime($params['date'] ?? 'yesterday'))->format('Y-m-d');

  // Retry a transient failure a few times before giving up (an error is not
  // treated as a clean day — nothing is emailed and the log shows the failure).
  $lastError = NULL;
  for ($attempt = 1; $attempt <= RZP_RECON_MAX_RETRIES; $attempt++) {
    try {
      $reconciler = new RazorpayReconciler($day . ' 00:00:00', $day . ' 23:59:59', (bool) $isTest);
      $res = $reconciler->reconcile();

      $res['date'] = $day;
      $res['is_test'] = $isTest;
      $res['email_sent'] = FALSE;
      if (!empty($res['issues']) && $sendEmail) {
        $res['email_sent'] = _razorpay_reconciliation_send_alert($day, $res);
      }

      Civi::log()->info('Razorpay reconciliation complete', [
        'date' => $day,
        'captured' => $res['summary']['rzp_captured_count'],
        'matched' => $res['summary']['matched_in_civi_count'],
        'mismatches' => count($res['issues']),
        'email_sent' => $res['email_sent'],
      ]);
      return civicrm_api3_create_success($res, $params, 'Civirazorpay', 'civicrm_razorpay_reconciliation_cron');
    }
    catch (\Throwable $e) {
      $lastError = $e;
      Civi::log()->warning('Razorpay reconciliation attempt failed, will retry', ['date' => $day, 'attempt' => $attempt, 'error' => $e->getMessage()]);
      if ($attempt < RZP_RECON_MAX_RETRIES) {
        sleep(min(60, 10 * (2 ** ($attempt - 1))));
      }
    }
  }

  Civi::log()->error('Razorpay reconciliation FAILED after retries', ['date' => $day, 'error' => $lastError->getMessage()]);
  return civicrm_api3_create_error('Razorpay reconciliation failed: ' . $lastError->getMessage());
}

/**
 * Send a short plain-text mismatch alert. Returns TRUE if accepted for delivery.
 *
 * @param string $day
 * @param array $res
 *
 * @return bool
 */
function _razorpay_reconciliation_send_alert(string $day, array $res): bool {
  [$fromName, $fromEmail] = CRM_Core_BAO_Domain::getNameAndEmail();
  $s = $res['summary'];

  $lines = [];
  $lines[] = "Razorpay - CiviCRM reconciliation for {$day} found " . count($res['issues']) . " mismatch(es).";
  $lines[] = "Razorpay captured {$s['rzp_captured_count']} payment(s) (Rs {$s['rzp_captured_total']}); matched in CiviCRM {$s['matched_in_civi_count']} (Rs {$s['matched_in_civi_total']}).";
  $lines[] = 'Please check the payment IDs below in CiviCRM and Razorpay.';
  $lines[] = '';
  $n = 0;
  foreach ($res['issues'] as $i) {
    $n++;
    $lines[] = "{$n}. {$i['category']} | Payment: {$i['pay_id']} | Contributor: {$i['contributor']} | Amount: Rs {$i['amount']}";
    $lines[] = "   {$i['detail']}";
  }

  $params = [
    'from' => "\"{$fromName}\" <{$fromEmail}>",
    'toEmail' => RZP_RECON_TO,
    'cc' => RZP_RECON_CC,
    'subject' => "Razorpay reconciliation: " . count($res['issues']) . " mismatch(es) on {$day}",
    'text' => implode("\n", $lines),
  ];

  try {
    $sent = CRM_Utils_Mail::send($params);
    if (!$sent) {
      Civi::log()->error('Razorpay reconciliation: mailer returned failure', ['date' => $day]);
    }
    return (bool) $sent;
  }
  catch (\Throwable $e) {
    Civi::log()->error('Razorpay reconciliation: sending alert threw', ['date' => $day, 'error' => $e->getMessage()]);
    return FALSE;
  }
}
