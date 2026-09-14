<?php

/**
 * @file
 * READ-ONLY manual reconciliation of CiviCRM contributions vs Razorpay payments
 * for a given date/time window. Nothing is created or updated anywhere.
 *
 * This is a thin CLI wrapper around CRM_Civirazorpay_RazorpayReconciler — the
 * SAME engine the nightly reconciliation cron uses — so the manual check and
 * the automated check can never drift apart.
 *
 * Usage:
 *   cv scr wp-content/civi-extensions/civirazorpay/cli/verify-razorpay-reconciliation.php "<start>" "<end>" [is_test]
 *
 * Examples:
 *   # Live mode (prod), window in server timezone:
 *   cv scr .../verify-razorpay-reconciliation.php "2026-07-18 00:00:00" "2026-07-18 23:59:59" 0
 *
 *   # Test mode (local):
 *   cv scr .../verify-razorpay-reconciliation.php "2026-07-01 00:00:00" "2026-07-20 23:59:59" 1
 */

error_reporting(E_ALL & ~E_DEPRECATED);

// Reuse the SAME reconciler the nightly cron uses (single source of truth).
// Requiring the cron file only defines the class + functions; it runs nothing.
require_once __DIR__ . '/../api/v3/Civirazorpay/CivicrmRazorpayReconciliationCron.php';

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
  $result = $reconciler->reconcile();

  $mode = $isTest ? 'TEST' : 'LIVE';
  echo "Mode: {$mode}\n";
  echo "Window: {$result['window']['start']} -> {$result['window']['end']} (server TZ " . date_default_timezone_get() . ")\n\n";

  $s = $result['summary'];
  echo "== Count / amount summary ==\n";
  echo "  Razorpay captured : {$s['rzp_captured_count']} payments, Rs {$s['rzp_captured_total']}\n";
  echo "  Matched in Civi   : {$s['matched_in_civi_count']} payments, Rs {$s['matched_in_civi_total']}\n";
  echo "  reconciled: " . ($s['reconciled'] ? 'YES' : 'NO') . "\n\n";

  $issues = $result['issues'];
  echo "==================== SUMMARY ====================\n";
  if (!$issues) {
    echo "NO MISMATCHES. Civi and Razorpay agree for this window.\n";
    exit(0);
  }

  $byCategory = [];
  foreach ($issues as $issue) {
    $byCategory[$issue['category']][] = $issue;
    echo "  [{$issue['category']}] {$issue['detail']}\n";
  }
  echo "\n";
  foreach ($byCategory as $category => $rows) {
    echo count($rows) . " x {$category}\n";
  }

  $csv = rtrim(sys_get_temp_dir(), '/') . '/razorpay-reconcile-' . date('Ymd-His', strtotime($startDate)) . '.csv';
  $fh = fopen($csv, 'w');
  fputcsv($fh, ['category', 'detail', 'contribution_id', 'pay_id', 'amount', 'contributor']);
  foreach ($issues as $issue) {
    fputcsv($fh, [
      $issue['category'],
      $issue['detail'],
      $issue['contribution_id'] ?? '',
      $issue['pay_id'] ?? '',
      $issue['amount'] ?? '',
      $issue['contributor'] ?? '',
    ]);
  }
  fclose($fh);
  echo "\nDetails written to: {$csv}\n";
  exit(count($issues) > 0 ? 2 : 0);
}

razorpay_reconcile_main();
