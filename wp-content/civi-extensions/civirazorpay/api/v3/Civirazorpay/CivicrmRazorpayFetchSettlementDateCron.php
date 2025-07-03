<?php

/**
 * @file
 * Custom API for Razorpay Settlement Date Cron job to fetch settlement details from Razorpay and update CiviCRM contributions.
 */

use Civi\Api4\Contribution;
use Civi\Api4\PaymentProcessor;

// Log early to confirm script execution and __DIR__ for debugging.
error_log("Attempting to start Razorpay Settlement Cron Job, __DIR__ = " . __DIR__);

/**
 * Class to handle fetching and processing Razorpay settlements.
 */
class RazorpaySettlementFetcher {
  private $apiKey;
  private $apiSecret;
  private $processorID;
  private $isTest;
  private $paymentInstrumentId;
  private $targetDate;
  private $retryCount = 0;
  private const MAX_RETRIES = 3;

  /**
   * Constructor to initialize Razorpay API and settings.
   *
   * @param int $paymentInstrumentId
   * @param string $date
   * @param bool $isTest
   */
  public function __construct($paymentInstrumentId, $date, $isTest = TRUE) {
    civicrm_initialize();
    date_default_timezone_set('Asia/Kolkata');

    $this->paymentInstrumentId = $paymentInstrumentId;
    $this->isTest = $isTest;
    $this->targetDate = new DateTime($date);

    // Initialize Razorpay API credentials.
    try {
      $processorConfig = PaymentProcessor::get(FALSE)
        ->addWhere('payment_processor_type_id:name', '=', 'Razorpay')
        ->addWhere('is_test', '=', $this->isTest)
        ->execute()->single();

      $this->processorID = $processorConfig['id'];
      $this->apiKey = $processorConfig['user_name'];
      $this->apiSecret = $processorConfig['password'];
      error_log("Razorpay API credentials initialized for processor ID: {$this->processorID}, key: " . substr($this->apiKey, 0, 8) . "...");
    }
    catch (Exception $e) {
      $errorMsg = "Failed to initialize Razorpay API credentials: " . $e->getMessage();
      error_log($errorMsg);
      \Civi::log()->error($errorMsg);
      throw new Exception($errorMsg);
    }

    \Civi::log()->debug('RazorpaySettlementFetcher initialized', [
      'processorID' => $this->processorID,
      'isTest' => $this->isTest,
      'targetDate' => $this->targetDate->format('Y-m-d'),
      'paymentInstrumentId' => $this->paymentInstrumentId,
      'timezone' => date_default_timezone_get(),
    ]);
    error_log("RazorpaySettlementFetcher initialized: processorID={$this->processorID}, isTest={$this->isTest}, targetDate={$this->targetDate->format('Y-m-d')}, paymentInstrumentId={$this->paymentInstrumentId}");
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

    \Civi::log()->debug('Starting settlement fetch', ['date' => $this->targetDate->format('Y-m-d')]);
    error_log("Starting settlement fetch for {$this->targetDate->format('Y-m-d')}");

    try {
      // Fetch Razorpay transactions for target date.
      $transactions = $this->fetchSettlementTransactions();
      $returnValues['razorpay_transactions_fetched'] = array_sum(array_map('count', $transactions));
      $returnValues['processed'] = $returnValues['razorpay_transactions_fetched'];

      if (empty($transactions)) {
        \Civi::log()->debug('No transactions found from Razorpay', ['date' => $this->targetDate->format('Y-m-d')]);
        error_log("No transactions found from Razorpay for {$this->targetDate->format('Y-m-d')}");
      }
      else {
        $this->processTransactions($transactions, $returnValues);
      }
    }
    catch (Exception $e) {
      $this->handleRetry($e, $returnValues);
    }

    \Civi::log()->debug('Settlement fetch completed', $returnValues);
    error_log("Settlement fetch completed: Processed={$returnValues['processed']}, Updated={$returnValues['updated']}, Errors={$returnValues['errors']}, RazorpayTransactions={$returnValues['razorpay_transactions_fetched']}");
    return $returnValues;
  }

  /**
   * Fetch settlement transactions from Razorpay for the target date only.
   *
   * @return array
   */
  private function fetchSettlementTransactions(): array {
    $transactionsByDay = [];
    // Check only the target date.
    $datesToCheck = [
      $this->targetDate,
    ];

    foreach ($datesToCheck as $checkDate) {
      $year = $checkDate->format('Y');
      $month = $checkDate->format('m');
      $day = $checkDate->format('d');
      $options = ['year' => $year, 'month' => $month, 'day' => $day, 'count' => 100, 'skip' => 0];
      $transactions = [];

      for ($retry = 0; $retry < self::MAX_RETRIES; $retry++) {
        try {
          $url = "https://api.razorpay.com/v1/settlements/recon/combined?year=$year&month=$month&day=$day&count=100&skip={$options['skip']}";
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
            \Civi::log()->warning("Empty or invalid response from Razorpay API for $year-$month-$day", [
              'response' => $responseArray,
              'http_code' => $httpCode,
            ]);
            error_log("Empty or invalid response from Razorpay API for $year-$month-$day: " . json_encode($responseArray));
            break;
          }

          $transactions = array_merge($transactions, $responseArray['items']);
          \Civi::log()->debug("Razorpay transactions for $year-$month-$day", [
            'count' => count($responseArray['items']),
            'entity_ids' => array_column($responseArray['items'], 'entity_id'),
          ]);
          error_log("Fetched " . count($responseArray['items']) . " transactions for $year-$month-$day: " . json_encode(array_column($responseArray['items'], 'entity_id')));

          // Handle pagination.
          while ($responseArray['count'] >= $options['count']) {
            $options['skip'] += $options['count'];
            $url = "https://api.razorpay.com/v1/settlements/recon/combined?year=$year&month=$month&day=$day&count=100&skip={$options['skip']}";
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
              \Civi::log()->debug("Razorpay transactions for $year-$month-$day (paged)", [
                'count' => count($responseArray['items']),
                'entity_ids' => array_column($responseArray['items'], 'entity_id'),
              ]);
              error_log("Fetched " . count($responseArray['items']) . " additional transactions for $year-$month-$day, skip={$options['skip']}: " . json_encode(array_column($responseArray['items'], 'entity_id')));
            }
            // Avoid rate limits.
            sleep(1);
          }
          break;
        }
        catch (Exception $e) {
          if (strpos($e->getMessage(), '429') !== FALSE) {
            \Civi::log()->warning('Rate limit hit, retrying', [
              'retry' => $retry + 1,
              'error' => $e->getMessage(),
            ]);
            error_log("Rate limit hit for $year-$month-$day, retry " . ($retry + 1) . ": " . $e->getMessage());
            sleep(pow(2, $retry));
            continue;
          }
          \Civi::log()->error('API error', [
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'error' => $e->getMessage(),
            'response' => $response ?? 'No response',
          ]);
          error_log("API error for $year-$month-$day: " . $e->getMessage() . ", Response: " . ($response ?? 'No response'));
          break;
        }
      }

      $transactionsByDay["$year-$month-$day"] = $transactions;
    }

    \Civi::log()->debug('Transactions fetched', [
      'dates' => array_keys($transactionsByDay),
      'total_count' => array_sum(array_map('count', $transactionsByDay)),
    ]);
    error_log("Fetched transactions for dates: " . implode(', ', array_keys($transactionsByDay)));
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
        if ($transaction['type'] !== 'payment' || !isset($transaction['entity_id'], $transaction['settlement_id'], $transaction['settled_at'])) {
          \Civi::log()->debug('Skipping invalid transaction', [
            'entity_id' => $transaction['entity_id'] ?? 'N/A',
            'type' => $transaction['type'] ?? 'N/A',
          ]);
          error_log("Skipping invalid transaction for entity_id: " . ($transaction['entity_id'] ?? 'N/A'));
          $returnValues['errors']++;
          continue;
        }

        $paymentId = $transaction['entity_id'];
        if (!preg_match('/^pay_/', $paymentId)) {
          \Civi::log()->debug('Skipping non-payment entity_id', ['entity_id' => $paymentId]);
          error_log("Skipping non-payment entity_id: $paymentId");
          $returnValues['errors']++;
          continue;
        }

        $settlementId = $transaction['settlement_id'];
        // Log raw settled_at for debugging.
        \Civi::log()->debug('Raw settled_at', ['settled_at' => $transaction['settled_at']]);
        error_log("Raw settled_at for $paymentId: " . json_encode($transaction['settled_at']));

        // Handle settled_at parsing.
        $settledAt = $transaction['settled_at'];
        if (is_numeric($settledAt)) {
          // Assume Unix timestamp (seconds or milliseconds)
          $settledAt = strlen($settledAt) > 10 ? $settledAt / 1000 : $settledAt;
          $settlementDate = date('Y-m-d', $settledAt);
        }
        else {
          // Assume string date.
          $settlementDate = date('Y-m-d', strtotime($settledAt));
        }

        // Validate date.
        if ($settlementDate === FALSE || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $settlementDate)) {
          \Civi::log()->error('Invalid settlement date format', [
            'entity_id' => $paymentId,
            'settled_at' => $settledAt,
            'parsed_date' => $settlementDate,
          ]);
          error_log("Invalid settlement date format for $paymentId: settled_at=$settledAt, parsed=$settlementDate");
          $returnValues['errors']++;
          continue;
        }

        \Civi::log()->debug('Processing transaction', [
          'entity_id' => $paymentId,
          'settlement_id' => $settlementId,
          'settlement_date' => $settlementDate,
        ]);
        error_log("Processing transaction, entity_id: $paymentId, settlement_id: $settlementId, settlement_date: $settlementDate");

        try {
          $contribution = Contribution::get(FALSE)
            ->addSelect('id', 'Contribution_Details.Settlement_Id')
            ->addWhere('trxn_id', 'LIKE', '%' . $paymentId)
            ->addWhere('is_test', '=', $this->isTest)
            ->execute()->first();

          \Civi::log()->debug('Contribution Get', [
            'contribution' => $contribution,
            'settlementId' => $settlementId,
            'settlementDate' => $settlementDate,
          ]);

          if (!$contribution) {
            \Civi::log()->debug('No contribution found', ['trxn_id' => $paymentId]);
            error_log("No contribution found for trxn_id: $paymentId");
            $returnValues['errors']++;
            continue;
          }

          // Skip if already settled.
          if (!empty($contribution['Contribution_Details.Settlement_Id'])) {
            \Civi::log()->debug('Skipping already settled contribution', ['trxn_id' => $paymentId]);
            error_log("Skipping already settled contribution for trxn_id: $paymentId");
            $returnValues['processed']++;
            continue;
          }

          $updateResult = Contribution::update(FALSE)
            ->addWhere('trxn_id', 'LIKE', '%' . $paymentId)
            ->addWhere('is_test', '=', $this->isTest)
            ->addValue($settlementIdField, $settlementId)
            ->addValue($settlementDateField, $settlementDate)
            ->execute();

          \Civi::log()->debug('Contribution updateResult', ['updateResult' => $updateResult]);

          if ($updateResult->rowCount) {
            $returnValues['updated']++;
          }
          else {
            \Civi::log()->warning('No rows updated', ['trxn_id' => $paymentId]);
            error_log("No rows updated for trxn_id: $paymentId");
            $returnValues['errors']++;
          }
        }
        catch (Exception $e) {
          \Civi::log()->error('Contribution update failed', [
            'trxn_id' => $paymentId,
            'error' => $e->getMessage(),
            'settlementIdField' => $settlementIdField,
            'settlementDateField' => $settlementDateField,
          ]);
          error_log("Contribution update failed for trxn_id: $paymentId, error: " . $e->getMessage());
          $returnValues['errors']++;
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
    \Civi::log()->error('Retry attempt', [
      'retry' => $this->retryCount,
      'error' => $e->getMessage(),
    ]);
    error_log("Error on attempt {$this->retryCount}: {$e->getMessage()}");

    if ($this->retryCount >= self::MAX_RETRIES) {
      $errorMsg = "Maximum retries reached: {$e->getMessage()}";
      \Civi::log()->error($errorMsg);
      error_log($errorMsg);
      $returnValues['errors']++;
      throw new Exception($errorMsg);
    }

    sleep(pow(2, $this->retryCount));
    $this->run();
  }

}

/**
 * Define the API specification.
 *
 * @param array $spec
 */
function _civicrm_api3_civirazorpay_civicrmrazorpayfetchsettlementdatecron_spec(&$spec) {
  $spec['date'] = [
    'title' => 'Contribution Date',
    'description' => 'Date to fetch settlements for (defaults to yesterday)',
    'type' => CRM_Utils_Type::T_DATE,
    'api.default' => date('Y-m-d', strtotime('-1 day')),
  ];
  $spec['payment_instrument_id'] = [
    'title' => 'Payment Instrument ID',
    'description' => 'ID used to identify Razorpay contributions',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 1,
  ];
  $spec['is_test'] = [
    'title' => 'Test Mode',
    'description' => 'Process test contributions (1) or live contributions (0). Defaults to 1.',
    'type' => CRM_Utils_Type::T_INT,
    'api.default' => 1,
  ];
}

/**
 * Implementation of the custom API action.
 *
 * @param array $params
 *
 * @return array
 *
 * @throws CiviCRM_API3_Exception
 */
function civicrm_api3_civirazorpay_civicrmrazorpayfetchsettlementdatecron($params) {
  try {
    $fetcher = new RazorpaySettlementFetcher(
      $params['payment_instrument_id'],
      $params['date'] ?? date('Y-m-d', strtotime('-1 day')),
      $params['is_test'] ?? 1
    );
    $result = $fetcher->run();
    return civicrm_api3_create_success($result, $params, 'Civirazorpay', 'CivicrmRazorpayFetchSettlementDateCron');
  }
  catch (Exception $e) {
    \Civi::log()->error('Scheduled job failed', ['error' => $e->getMessage()]);
    error_log("Scheduled job failed: " . $e->getMessage());
    CRM_Core_Error::debug_log_message("Error in scheduled job: " . $e->getMessage());
    return civicrm_api3_create_error("Failed to process settlements: " . $e->getMessage(), [
      'processed' => 0,
      'updated' => 0,
      'errors' => 1,
    ]);
  }
}
