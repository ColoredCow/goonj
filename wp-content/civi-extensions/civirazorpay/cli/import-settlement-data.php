<?php

/**
 * @file
 * One-time script to fetch Razorpay settlement details for a given date range and update CiviCRM contributions.
 */

use Civi\Api4\Contribution;
use Civi\Api4\PaymentProcessor;

// cv scr /Users/tarunjoshi/Projects/goonj/wp-content/civi-extensions/civirazorpay/cli/import-settlement-data.php 1 2025-04-03 2025-04-03 0 | tee save-data.txt
// Enable error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Class to handle fetching and processing Razorpay settlements.
 */
class RazorpaySettlementFetcher {
  private $apiKey;
  private $apiSecret;
  private $processorID;
  private $isTest;
  private $paymentInstrumentId;
  private $startDate;
  private $endDate;
  private $retryCount = 0;
  private const MAX_RETRIES = 3;

  /**
   * Constructor to initialize Razorpay API and settings.
   *
   * @param int $paymentInstrumentId
   * @param string $startDate
   * @param string $endDate
   * @param bool $isTest
   */
  public function __construct($paymentInstrumentId, $startDate, $endDate, $isTest = TRUE) {
    echo "Initializing CiviCRM...\n";
    try {
      civicrm_initialize();
      echo "CiviCRM initialized successfully.\n";
    }
    catch (Exception $e) {
      echo "Error: Failed to initialize CiviCRM: " . $e->getMessage() . "\n";
      \Civi::log()->error("Failed to initialize CiviCRM: " . $e->getMessage());
      exit(1);
    }

    $this->paymentInstrumentId = $paymentInstrumentId;
    $this->isTest = $isTest;
    $this->startDate = new DateTime($startDate);
    $this->endDate = new DateTime($endDate);

    echo "Fetching payment processor configuration...\n";
    try {
      $processorConfig = PaymentProcessor::get(FALSE)
        ->addWhere('payment_processor_type_id:name', '=', 'Razorpay')
        ->addWhere('is_test', '=', $this->isTest)
        ->execute()->single();

      $this->processorID = $processorConfig['id'];
      $this->apiKey = $processorConfig['user_name'];
      $this->apiSecret = $processorConfig['password'];
      echo "Payment processor found: ID {$this->processorID}\n";
    }
    catch (Exception $e) {
      echo "Error: Failed to fetch payment processor: " . $e->getMessage() . "\n";
      \Civi::log()->error("Failed to fetch payment processor: " . $e->getMessage());
      exit(1);
    }
  }

  /**
   * Run the settlement fetch and update process.
   *
   * @return array
   */
  public function run(): array {
    $returnValues = [
      'processed' => 0,
      'updated' => 0,
      'errors' => 0,
      'razorpay_transactions_fetched' => 0,
    ];

    try {
      echo "Fetching settlement transactions...\n";
      $transactions = $this->fetchSettlementTransactions();
      $returnValues['razorpay_transactions_fetched'] = array_sum(array_map('count', $transactions));
      $returnValues['processed'] = $returnValues['razorpay_transactions_fetched'];

      if (empty($transactions)) {
        $message = "No transactions found from Razorpay for date range {$this->startDate->format('Y-m-d')} to {$this->endDate->format('Y-m-d')}";
        echo "$message\n";
        \Civi::log()->debug($message);
      }
      else {
        echo "Processing transactions...\n";
        $this->processTransactions($transactions, $returnValues);
      }
    }
    catch (Exception $e) {
      echo "Error in run(): " . $e->getMessage() . "\n";
      \Civi::log()->error("Error in run(): " . $e->getMessage());
      $this->handleRetry($e, $returnValues);
    }

    return $returnValues;
  }

  /**
   * Fetch settlement transactions from Razorpay for the date range.
   *
   * @return array
   */
  private function fetchSettlementTransactions(): array {
    $transactionsByDay = [];
    $currentDate = clone $this->startDate;
    $datesToCheck = [];

    // Generate array of dates from startDate to endDate.
    while ($currentDate <= $this->endDate) {
      $datesToCheck[] = clone $currentDate;
      $currentDate->modify('+1 day');
    }

    foreach ($datesToCheck as $checkDate) {
      $year = $checkDate->format('Y');
      $month = $checkDate->format('m');
      $day = $checkDate->format('d');
      $options = ['year' => $year, 'month' => $month, 'day' => $day, 'count' => 50, 'skip' => 0];
      $transactions = [];

      for ($retry = 0; $retry < self::MAX_RETRIES; $retry++) {
        try {
          $url = "https://api.razorpay.com/v1/settlements/recon/combined?year=$year&month=$month&day=$day&count=50&skip={$options['skip']}";
          echo "Fetching transactions for $year-$month-$day (skip: {$options['skip']})...\n";
          $ch = curl_init($url);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
          curl_setopt($ch, CURLOPT_USERPWD, "{$this->apiKey}:{$this->apiSecret}");
          curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
          $response = curl_exec($ch);
          $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          curl_close($ch);

          if ($httpCode != 200) {
            throw new Exception("HTTP $httpCode: " . ($response ?: 'No response'));
          }

          $responseArray = json_decode($response, TRUE);
          if (empty($responseArray) || !isset($responseArray['items'])) {
            echo "No items found for $year-$month-$day\n";
            \Civi::log()->debug("No items found for $year-$month-$day");
            break;
          }

          $transactions = array_merge($transactions, $responseArray['items']);

          while ($responseArray['count'] >= $options['count']) {
            $options['skip'] += $options['count'];
            $url = "https://api.razorpay.com/v1/settlements/recon/combined?year=$year&month=$month&day=$day&count=50&skip={$options['skip']}";
            echo "Fetching more transactions for $year-$month-$day (skip: {$options['skip']})...\n";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->apiKey}:{$this->apiSecret}");
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode != 200) {
              throw new Exception("HTTP $httpCode: " . ($response ?: 'No response'));
            }

            $responseArray = json_decode($response, TRUE);
            if (isset($responseArray['items'])) {
              $transactions = array_merge($transactions, $responseArray['items']);
            }
            // Avoid rate limits.
            sleep(1);
          }
          break;
        }
        catch (Exception $e) {
          echo "Retry $retry failed for $year-$month-$day: " . $e->getMessage() . "\n";
          \Civi::log()->error("Retry $retry failed for $year-$month-$day: " . $e->getMessage());
          if (strpos($e->getMessage(), '429') !== FALSE) {
            sleep(pow(2, $retry));
            continue;
          }
          break;
        }
      }

      $transactionCount = count($transactions);
      echo "Fetched $transactionCount transactions for $year-$month-$day\n";
      \Civi::log()->debug("Fetched $transactionCount transactions for $year-$month-$day");
      $transactionsByDay["$year-$month-$day"] = $transactions;
    }

    return $transactionsByDay;
  }

  /**
   * Process Razorpay transactions and update CiviCRM contributions.
   *
   * @param array $transactionsByDay
   * @param array $returnValues
   */
  private function processTransactions(array $transactionsByDay, array &$returnValues): void {
    $settlementIdField = 'Contribution_Details.Settlement_Id';
    $settlementDateField = 'Contribution_Details.Settlement_Date';

    foreach ($transactionsByDay as $date => $transactions) {
      foreach ($transactions as $transaction) {
        if ($transaction['type'] !== 'payment' || !isset($transaction['entity_id'], $transaction['settlement_id'], $transaction['settled_at'], $transaction['fee'], $transaction['tax'])) {
          $returnValues['errors']++;
          \Civi::log()->error("Invalid transaction for $date: Missing required fields");
          continue;
        }

        $paymentId = $transaction['entity_id'];
        if (!preg_match('/^pay_/', $paymentId)) {
          $returnValues['errors']++;
          \Civi::log()->error("Invalid payment ID for $date: $paymentId");
          continue;
        }

        $settlementId = $transaction['settlement_id'];
        // Handle settled_at parsing.
        $settledAt = $transaction['settled_at'];
        if (is_numeric($settledAt)) {
          // Assume Unix timestamp (seconds or milliseconds)
          $settledAt = strlen($settledAt) > 10 ? $settledAt / 1000 : $settledAt;
          $settlementDate = date('Y-m-d', $settledAt);
        }
        else {
          $settlementDate = date('Y-m-d', strtotime($settledAt));
        }

        if ($settlementDate === FALSE || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $settlementDate)) {
          $returnValues['errors']++;
          \Civi::log()->error("Invalid settlement date for $date, payment $paymentId: $settledAt");
          continue;
        }

        // Convert fee and tax from paise to rupees.
        $feeAmount = $transaction['fee'] / 100;
        $taxAmount = $transaction['tax'] / 100;

        // Calculate Razorpay base fee (excluding GST)
        $razorpayFee = $feeAmount - $taxAmount;

        try {
          $contribution = Contribution::get(FALSE)
            ->addSelect('id', 'Contribution_Details.Settlement_Id', 'Contribution_Details.Razorpay_Fee', 'Contribution_Details.Razorpay_Tax')
            ->addWhere('trxn_id', 'LIKE', '%' . $paymentId)
            ->addWhere('is_test', '=', $this->isTest)
            ->execute()->first();

          if (!$contribution) {
            $returnValues['errors']++;
            \Civi::log()->error("No contribution found for payment $paymentId");
            continue;
          }

          // Skip if already settled.
          if (!empty($contribution['Contribution_Details.Settlement_Id'])) {
            $returnValues['processed']++;
            \Civi::log()->debug("Contribution already settled for payment $paymentId");
            continue;
          }

          $updateResult = Contribution::update(FALSE)
            ->addWhere('trxn_id', 'LIKE', '%' . $paymentId)
            ->addWhere('is_test', '=', $this->isTest)
            ->addValue($settlementIdField, $settlementId)
            ->addValue($settlementDateField, $settlementDate)
            ->addValue($feeAmountField, $razorpayFee)
            ->addValue($taxAmountField, $taxAmount)
            ->execute();

          if ($updateResult->rowCount) {
            $returnValues['updated']++;
            \Civi::log()->debug("Updated contribution for payment $paymentId with settlement $settlementId on $settlementDate");
          }
          else {
            $returnValues['errors']++;
            \Civi::log()->error("Failed to update contribution for payment $paymentId");
          }
        }
        catch (Exception $e) {
          $returnValues['errors']++;
          \Civi::log()->error("Error processing contribution for payment $paymentId: " . $e->getMessage());
        }

        $returnValues['processed']++;
      }
    }
  }

  /**
   * Handle retry logic on failure.
   *
   * @param Exception $e
   * @param array $returnValues
   */
  private function handleRetry(Exception $e, array &$returnValues): void {
    $this->retryCount++;

    if ($this->retryCount >= self::MAX_RETRIES) {
      throw new Exception("Failed after max retries: " . $e->getMessage());
    }

    echo "Retrying after error: " . $e->getMessage() . "\n";
    \Civi::log()->error("Retrying after error: " . $e->getMessage());
    sleep(pow(2, $this->retryCount));
    $this->run();
  }

}

/**
 * Main execution function for the script.
 */
function main($argv = NULL) {
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);

  // Fallback for undefined $argv.
  if ($argv === NULL) {
    $argv = $_SERVER['argv'] ?? [];
    echo "Warning: \$argv is undefined, using \$_SERVER['argv'] as fallback: " . print_r($argv, TRUE) . "\n";
  }

  // Enhanced debugging.
  echo "Execution context: CLI=" . (php_sapi_name() === 'cli' ? 'Yes' : 'No') . "\n";
  echo "Received arguments: " . print_r($argv, TRUE) . "\n";

  // Extract arguments, adjusting for cv scr.
  $paymentInstrumentId = (int) $argv[3];
  $startDate = $argv[4];
  $endDate = $argv[5];
  $isTest = filter_var($argv[6], FILTER_VALIDATE_BOOLEAN);

  // Validate dates.
  try {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    if ($start > $end) {
      echo "Error: Start date must be before or equal to end date.\n";
      \Civi::log()->error("Error: Start date $startDate is after end date $endDate");
      exit(1);
    }
  }
  catch (Exception $e) {
    echo "Error: Invalid date format for $startDate or $endDate: " . $e->getMessage() . "\n";
    \Civi::log()->error("Error: Invalid date format for $startDate or $endDate: " . $e->getMessage());
    exit(1);
  }

  try {
    echo "Creating RazorpaySettlementFetcher...\n";
    $fetcher = new RazorpaySettlementFetcher($paymentInstrumentId, $startDate, $endDate, $isTest);
    $result = $fetcher->run();
    echo "Settlement processing completed for date range $startDate to $endDate:\n";
    echo "Transactions fetched: {$result['razorpay_transactions_fetched']}\n";
    echo "Transactions processed: {$result['processed']}\n";
    echo "Contributions updated: {$result['updated']}\n";
  }
  catch (Exception $e) {
    echo "Error: Failed to process settlements: " . $e->getMessage() . "\n";
    \Civi::log()->error("Error: Failed to process settlements: " . $e->getMessage());
    exit(1);
  }
}

// Execute the script.
main();
