<?php

/**
 * @file
 * READ-ONLY reconciliation of CiviCRM contributions vs Razorpay payments
 * for a given date/time window. Nothing is created or updated anywhere —
 * only GET calls to Razorpay and read queries in CiviCRM.
 *
 * Usage:
 *   cv scr wp-content/civi-extensions/civirazorpay/cli/verify-razorpay-reconciliation.php "<start>" "<end>" [is_test]
 *
 * Examples:
 *   # Live mode (prod), window in server timezone:
 *   cv scr .../verify-razorpay-reconciliation.php "2026-07-18 14:00:00" "2026-07-18 20:00:00" 0
 *
 *   # Test mode (local):
 *   cv scr .../verify-razorpay-reconciliation.php "2026-07-01 00:00:00" "2026-07-20 23:59:59" 1
 *
 * API credentials are read from the CiviCRM Razorpay payment processor
 * config (live or test row depending on is_test) — nothing is passed on
 * the command line.
 */

use Civi\Api4\Contribution;
use Civi\Api4\PaymentProcessor;

ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

/**
 * Read-only reconciler between CiviCRM contributions and Razorpay payments.
 */
class RazorpayReconciler {

  private string $apiKey;
  private string $apiSecret;
  private bool $isTest;
  private DateTime $start;
  private DateTime $end;

  /**
   * Razorpay payments in the window, keyed by payment id (pay_xxx).
   */
  private array $rzpPayments = [];

  /**
   * Captured Razorpay payments keyed by order id (order_xxx).
   */
  private array $rzpByOrder = [];

  /**
   * CiviCRM contributions in the window.
   */
  private array $civiContributions = [];

  /**
   * Map of pay_xxx => contribution row for the window contributions.
   */
  private array $civiByPayId = [];

  /**
   * Map of order_xxx => contribution row for the window contributions.
   */
  private array $civiByOrderId = [];

  /**
   * Keys already flagged, to avoid double-reporting the same pair.
   */
  private array $flagged = [];

  /**
   * Collected mismatch rows for the final report.
   */
  private array $issues = [];

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

    $mode = $isTest ? 'TEST' : 'LIVE';
    echo "Mode: {$mode} (processor id {$processor['id']}, key {$this->apiKey})\n";
    echo "Window: {$this->start->format('Y-m-d H:i:s')} -> {$this->end->format('Y-m-d H:i:s')} (server TZ " . date_default_timezone_get() . ")\n\n";
  }

  /**
   * GET a Razorpay API path. Read-only by construction (no POST anywhere).
   */
  private function razorpayGet(string $path, array $query = []): array {
    $url = 'https://api.razorpay.com/v1/' . $path;
    if ($query) {
      $url .= '?' . http_build_query($query);
    }

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
    $desc = $decoded['error']['description'] ?? '';
    // Razorpay answers 400 "The id provided does not exist" for unknown ids.
    if ($httpCode === 404 || ($httpCode === 400 && stripos($desc, 'does not exist') !== FALSE)) {
      return ['__not_found' => TRUE];
    }
    if ($httpCode !== 200) {
      throw new Exception("Razorpay API HTTP {$httpCode} on {$path}: " . ($desc ?: $body));
    }
    return $decoded ?? [];
  }

  /**
   * Fetch every Razorpay payment created inside the window (all statuses).
   */
  private function fetchRazorpayPayments(): void {
    $from = $this->start->getTimestamp();
    $to = $this->end->getTimestamp();
    $skip = 0;

    do {
      $page = $this->razorpayGet('payments', [
        'from' => $from,
        'to' => $to,
        'count' => 100,
        'skip' => $skip,
      ]);
      $items = $page['items'] ?? [];
      foreach ($items as $p) {
        $this->rzpPayments[$p['id']] = $p;
        if (!empty($p['order_id']) && $p['status'] === 'captured') {
          $this->rzpByOrder[$p['order_id']] = $p;
        }
      }
      $skip += count($items);
      echo "  fetched " . count($items) . " payments (total {$skip})\n";
    } while (count($items) === 100);

    $byStatus = [];
    foreach ($this->rzpPayments as $p) {
      $byStatus[$p['status']] = ($byStatus[$p['status']] ?? 0) + 1;
    }
    echo "Razorpay payments in window: " . count($this->rzpPayments) . " — " . json_encode($byStatus) . "\n\n";
  }

  /**
   * Fetch CiviCRM contributions received inside the window.
   */
  private function fetchCiviContributions(): void {
    $this->civiContributions = (array) Contribution::get(FALSE)
      ->addSelect('id', 'contact_id', 'contact_id.display_name', 'total_amount', 'trxn_id',
        'contribution_status_id:name', 'receive_date', 'contribution_recur_id', 'is_test',
        'financial_type_id:name', 'payment_instrument_id:name')
      ->addWhere('receive_date', '>=', $this->start->format('Y-m-d H:i:s'))
      ->addWhere('receive_date', '<=', $this->end->format('Y-m-d H:i:s'))
      ->addWhere('is_test', '=', $this->isTest)
      ->setLimit(0)
      ->execute()
      ->getArrayCopy();

    foreach ($this->civiContributions as $c) {
      foreach ($this->extractPayIds($c) as $payId) {
        $this->civiByPayId[$payId] = $c;
      }
      if (preg_match('/order_[A-Za-z0-9]+/', (string) ($c['trxn_id'] ?? ''), $m)) {
        $this->civiByOrderId[$m[0]] = $c;
      }
    }

    $byStatus = [];
    foreach ($this->civiContributions as $c) {
      $s = $c['contribution_status_id:name'];
      $byStatus[$s] = ($byStatus[$s] ?? 0) + 1;
    }
    echo "CiviCRM contributions in window: " . count($this->civiContributions) . " — " . json_encode($byStatus) . "\n\n";
  }

  /**
   * Extract every Razorpay payment id referenced by a contribution:
   * trxn_id itself ("pay_X" or "order_X,pay_Y") plus its financial_trxn rows.
   */
  private function extractPayIds(array $contribution): array {
    $ids = [];
    if (preg_match_all('/pay_[A-Za-z0-9]+/', (string) ($contribution['trxn_id'] ?? ''), $m)) {
      $ids = $m[0];
    }
    if (!$ids) {
      // Fall back to the payment records (financial_trxn) for older rows
      // where the contribution trxn_id only holds the order id.
      $dao = CRM_Core_DAO::executeQuery(
        "SELECT ft.trxn_id
           FROM civicrm_entity_financial_trxn eft
           JOIN civicrm_financial_trxn ft ON ft.id = eft.financial_trxn_id
          WHERE eft.entity_table = 'civicrm_contribution'
            AND eft.entity_id = %1
            AND ft.trxn_id LIKE 'pay_%'",
        [1 => [$contribution['id'], 'Integer']]
      );
      while ($dao->fetch()) {
        $ids[] = $dao->trxn_id;
      }
    }
    return array_unique($ids);
  }

  /**
   * DB-wide lookup (any date) of a Razorpay payment id in contribution
   * trxn_id or financial_trxn trxn_id. Catches receive_date drift.
   */
  private function findContributionAnywhere(string $payId): ?array {
    $found = Contribution::get(FALSE)
      ->addSelect('id', 'total_amount', 'trxn_id', 'contribution_status_id:name', 'receive_date', 'contribution_recur_id')
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
        WHERE ft.trxn_id LIKE %1
          AND eft.entity_table = 'civicrm_contribution'
        LIMIT 1",
      [1 => ['%' . $payId . '%', 'String']]
    );
    if ($dao->fetch()) {
      return Contribution::get(FALSE)
        ->addSelect('id', 'total_amount', 'trxn_id', 'contribution_status_id:name', 'receive_date', 'contribution_recur_id')
        ->addWhere('id', '=', (int) $dao->entity_id)
        ->execute()->first();
    }
    return NULL;
  }

  /**
   *
   */
  private function flag(string $category, string $detail, array $extra = []): void {
    $key = $category . '|' . ($extra['pay_id'] ?? '') . '|' . ($extra['contribution_id'] ?? '');
    if (isset($this->flagged[$key])) {
      return;
    }
    $this->flagged[$key] = TRUE;
    $this->issues[] = ['category' => $category, 'detail' => $detail] + $extra;
    echo "  [{$category}] {$detail}\n";
  }

  /**
   * Direction 1: every captured Razorpay payment must exist in CiviCRM
   * with status Completed and the same amount.
   */
  private function checkRazorpayAgainstCivi(): void {
    echo "== Direction 1: Razorpay captured payments -> CiviCRM ==\n";
    $ok = 0;

    foreach ($this->rzpPayments as $payId => $p) {
      if ($p['status'] !== 'captured' && $p['status'] !== 'refunded') {
        continue;
      }
      $kind = !empty($p['invoice_id']) ? 'recurring' : 'one-time';
      $rzpAmount = $p['amount'] / 100;

      $contribution = $this->civiByPayId[$payId] ?? $this->findContributionAnywhere($payId);

      // The payment id may be unrecorded while its order still has a (stuck
      // Pending) contribution — a missed webhook. Report that as a status
      // mismatch, not a missing record.
      if (!$contribution && !empty($p['order_id']) && isset($this->civiByOrderId[$p['order_id']])) {
        $contribution = $this->civiByOrderId[$p['order_id']];
      }

      if (!$contribution) {
        $this->flag('MISSING_IN_CIVI',
          "{$payId} ({$kind}, Rs {$rzpAmount}, rzp status {$p['status']}, created " . date('Y-m-d H:i:s', $p['created_at']) . ", email " . ($p['email'] ?? '-') . ") has NO contribution in CiviCRM",
          ['pay_id' => $payId, 'amount' => $rzpAmount]);
        continue;
      }

      $civiStatus = $contribution['contribution_status_id:name'];
      $civiAmount = (float) $contribution['total_amount'];

      if (abs($civiAmount - $rzpAmount) > 0.01) {
        $this->flag('AMOUNT_MISMATCH',
          "{$payId} ({$kind}): Razorpay Rs {$rzpAmount} vs Civi contribution {$contribution['id']} Rs {$civiAmount}",
          ['pay_id' => $payId, 'contribution_id' => $contribution['id']]);
      }

      if ($p['status'] === 'captured' && $civiStatus !== 'Completed') {
        $this->flag('STATUS_MISMATCH',
          "{$payId} ({$kind}): captured at Razorpay (Rs {$rzpAmount}) but contribution {$contribution['id']} is '{$civiStatus}' in Civi",
          ['pay_id' => $payId, 'contribution_id' => $contribution['id']]);
        continue;
      }

      if ($p['status'] === 'refunded') {
        $this->flag('REFUNDED_AT_RAZORPAY',
          "{$payId} ({$kind}, Rs {$rzpAmount}) is refunded at Razorpay; Civi contribution {$contribution['id']} status '{$civiStatus}' — verify manually",
          ['pay_id' => $payId, 'contribution_id' => $contribution['id']]);
        continue;
      }

      $ok++;
    }
    echo "Matched OK: {$ok}\n\n";
  }

  /**
   * Direction 2: every Razorpay-linked contribution in the window must map
   * back to a real Razorpay payment in the expected state.
   */
  private function checkCiviAgainstRazorpay(): void {
    echo "== Direction 2: CiviCRM contributions -> Razorpay ==\n";
    $ok = 0;
    $nonRazorpay = 0;

    foreach ($this->civiContributions as $c) {
      $trxn = (string) ($c['trxn_id'] ?? '');
      $payIds = $this->extractPayIds($c);
      $status = $c['contribution_status_id:name'];
      $kind = $c['contribution_recur_id'] ? 'recurring' : 'one-time';

      // Not a Razorpay record (cheque/cash/import etc.) — out of scope.
      if (!$payIds && !preg_match('/(order_|pay_|sub_)/', $trxn)) {
        $nonRazorpay++;
        continue;
      }

      // Pending one-time rows hold only the order id. Money should NOT have
      // been captured for these; if it was, that's the webhook-miss case.
      if (!$payIds) {
        if (preg_match('/order_[A-Za-z0-9]+/', $trxn, $m)) {
          $orderId = $m[0];
          $captured = $this->rzpByOrder[$orderId] ?? NULL;
          if (!$captured) {
            // Definitive per-order check, still read-only.
            $orderPayments = $this->razorpayGet("orders/{$orderId}/payments");
            foreach ($orderPayments['items'] ?? [] as $op) {
              if ($op['status'] === 'captured') {
                $captured = $op;
                break;
              }
            }
          }
          if ($captured && $status !== 'Completed') {
            $this->flag('STATUS_MISMATCH',
              "contribution {$c['id']} ({$kind}, Rs {$c['total_amount']}, status '{$status}', {$c['contact_id.display_name']}): order {$orderId} HAS captured payment {$captured['id']} at Razorpay",
              ['contribution_id' => $c['id'], 'pay_id' => $captured['id']]);
          }
          elseif (!$captured && $status === 'Completed') {
            $this->flag('COMPLETED_WITHOUT_PAYMENT',
              "contribution {$c['id']} ({$kind}, Rs {$c['total_amount']}, {$c['contact_id.display_name']}) is Completed but order {$orderId} has no captured payment and no pay_ id recorded",
              ['contribution_id' => $c['id']]);
          }
          else {
            $ok++;
          }
        }
        else {
          // sub_ or other razorpay-ish trxn with no payment id.
          if ($status === 'Completed') {
            $this->flag('COMPLETED_WITHOUT_PAYMENT',
              "contribution {$c['id']} ({$kind}, Rs {$c['total_amount']}, trxn '{$trxn}') is Completed but carries no Razorpay payment id",
              ['contribution_id' => $c['id']]);
          }
          else {
            $ok++;
          }
        }
        continue;
      }

      // Has payment id(s): confirm each against Razorpay.
      foreach ($payIds as $payId) {
        $p = $this->rzpPayments[$payId] ?? NULL;
        if (!$p) {
          // Payment created outside the window (date drift) — fetch directly.
          $p = $this->razorpayGet("payments/{$payId}");
          if (!empty($p['__not_found'])) {
            $this->flag('PAY_ID_NOT_AT_RAZORPAY',
              "contribution {$c['id']} ({$kind}, Rs {$c['total_amount']}, status '{$status}') references {$payId} which does NOT exist at Razorpay ({$this->apiKey})",
              ['contribution_id' => $c['id'], 'pay_id' => $payId]);
            continue;
          }
        }
        $rzpAmount = $p['amount'] / 100;
        if ($status === 'Completed' && !in_array($p['status'], ['captured', 'refunded'], TRUE)) {
          $this->flag('COMPLETED_BUT_NOT_CAPTURED',
            "contribution {$c['id']} ({$kind}, Rs {$c['total_amount']}, {$c['contact_id.display_name']}): Civi says Completed but Razorpay {$payId} status is '{$p['status']}'",
            ['contribution_id' => $c['id'], 'pay_id' => $payId]);
        }
        elseif (abs((float) $c['total_amount'] - $rzpAmount) > 0.01) {
          $this->flag('AMOUNT_MISMATCH',
            "contribution {$c['id']} ({$kind}): Civi Rs {$c['total_amount']} vs Razorpay {$payId} Rs {$rzpAmount}",
            ['contribution_id' => $c['id'], 'pay_id' => $payId]);
        }
        else {
          $ok++;
        }
      }
    }
    echo "Matched OK: {$ok}; non-Razorpay contributions skipped: {$nonRazorpay}\n\n";
  }

  /**
   * Run both directions and print the summary.
   */
  public function run(): int {
    echo "Fetching Razorpay payments...\n";
    $this->fetchRazorpayPayments();
    $this->fetchCiviContributions();
    $this->checkRazorpayAgainstCivi();
    $this->checkCiviAgainstRazorpay();

    echo "==================== SUMMARY ====================\n";
    if (!$this->issues) {
      echo "NO MISMATCHES. Civi and Razorpay agree for this window.\n";
      return 0;
    }

    $byCategory = [];
    foreach ($this->issues as $issue) {
      $byCategory[$issue['category']][] = $issue;
    }
    foreach ($byCategory as $category => $rows) {
      echo count($rows) . " x {$category}\n";
    }

    $csv = rtrim(sys_get_temp_dir(), '/') . '/razorpay-reconcile-' . $this->start->format('Ymd-His') . '.csv';
    $fh = fopen($csv, 'w');
    fputcsv($fh, ['category', 'detail', 'contribution_id', 'pay_id', 'amount']);
    foreach ($this->issues as $issue) {
      fputcsv($fh, [
        $issue['category'],
        $issue['detail'],
        $issue['contribution_id'] ?? '',
        $issue['pay_id'] ?? '',
        $issue['amount'] ?? '',
      ]);
    }
    fclose($fh);
    echo "\nDetails written to: {$csv}\n";
    return count($this->issues);
  }

}

/**
 * Entry point: locate our args regardless of how cv passes argv.
 */
function razorpay_reconcile_main(): void {
  $argv = $_SERVER['argv'] ?? [];
  $scriptIndex = NULL;
  foreach ($argv as $i => $arg) {
    if (strpos($arg, basename(__FILE__)) !== FALSE) {
      $scriptIndex = $i;
      break;
    }
  }
  $args = $scriptIndex !== NULL ? array_slice($argv, $scriptIndex + 1) : [];

  if (count($args) < 2) {
    echo "Usage: cv scr " . __FILE__ . " \"<start datetime>\" \"<end datetime>\" [is_test 0|1]\n";
    exit(1);
  }

  [$startDate, $endDate] = $args;
  $isTest = filter_var($args[2] ?? '0', FILTER_VALIDATE_BOOLEAN);

  if (strtotime($startDate) === FALSE || strtotime($endDate) === FALSE || strtotime($startDate) > strtotime($endDate)) {
    echo "Error: invalid date range '{$startDate}' -> '{$endDate}'\n";
    exit(1);
  }

  $reconciler = new RazorpayReconciler($startDate, $endDate, $isTest);
  exit($reconciler->run() > 0 ? 2 : 0);
}

razorpay_reconcile_main();
