<?php

/**
 * @file
 * Custom API for Razorpay Settlement Date Cron job to fetch settlement details from Razorpay and update CiviCRM contributions.
 */

use Civi\Api4\Contribution;
use Civi\Api4\PaymentProcessor;

// cv api Civirazorpay.CivicrmRazorpayFetchSettlementDateCron payment_instrument_id=1 is_test=0 --user=devteam

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

    $this->paymentInstrumentId = $paymentInstrumentId;
    $this->isTest = $isTest;
    $this->targetDate = new DateTime($date);

    try {
      $processorConfig = PaymentProcessor::get(FALSE)
        ->addWhere('payment_processor_type_id:name', '=', 'Razorpay')
        ->addWhere('is_test', '=', $this->isTest)
        ->execute()->single();

      $this->processorID = $processorConfig['id'];
      $this->apiKey = $processorConfig['user_name'];
      $this->apiSecret = $processorConfig['password'];
    }
    catch (Exception $e) {
      \Civi::log()->error('Failed to initialize payment processor', [
        'error_message' => $e->getMessage(),
      ]);
      throw new Exception("Failed to initialize payment processor: " . $e->getMessage());
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
      $transactions = $this->fetchSettlementTransactions();
      $returnValues['razorpay_transactions_fetched'] = array_sum(array_map('count', $transactions));
      $returnValues['processed'] = $returnValues['razorpay_transactions_fetched'];

      if (empty($transactions)) {
        \Civi::log()->debug('No transactions found from Razorpay', [
          'date' => $this->targetDate->format('Y-m-d'),
        ]);
      }
      else {
        $this->processTransactions($transactions, $returnValues);
      }
    }
    catch (Exception $e) {
      $this->handleRetry($e, $returnValues);
    }

    \Civi::log()->info('Razorpay settlement job completed', $returnValues);
    return $returnValues;
  }

  /**
   * Fetch settlement transactions from Razorpay for the target date only.
   *
   * @return array
   */
  private function fetchSettlementTransactions(): array {
    $transactionsByDay = [];
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
            \Civi::log()->error('Razorpay API request failed', [
              'url' => $url,
              'http_code' => $httpCode,
              'response' => $response ?: 'No response',
            ]);
            throw new Exception("HTTP $httpCode: " . ($response ?: 'No response'));
          }

          $responseArray = json_decode($response, TRUE);
          if (empty($responseArray) || !isset($responseArray['items'])) {
            break;
          }

          $transactions = array_merge($transactions, $responseArray['items']);

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
              \Civi::log()->error('Razorpay API request failed', [
                'url' => $url,
                'http_code' => $httpCode,
                'response' => $response ?: 'No response',
              ]);
              throw new Exception("HTTP $httpCode: " . ($response ?: 'No response'));
            }

            $responseArray = json_decode($response, TRUE);
            if (isset($responseArray['items'])) {
              $transactions = array_merge($transactions, $responseArray['items']);
            }
            // Avoid rate limits.
            sleep(1);
          }

          \Civi::log()->debug('Fetched transactions from Razorpay', [
            'date' => "$year-$month-$day",
            'transaction_count' => count($transactions),
          ]);
          break;
        }
        catch (Exception $e) {
          if (strpos($e->getMessage(), '429') !== FALSE) {
            sleep(pow(2, $retry));
            continue;
          }
          \Civi::log()->error('Failed to fetch transactions', [
            'date' => "$year-$month-$day",
            'error_message' => $e->getMessage(),
          ]);
          break;
        }
      }

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
        if ($transaction['type'] !== 'payment' || !isset($transaction['entity_id'], $transaction['settlement_id'], $transaction['settled_at'])) {
          $returnValues['errors']++;
          \Civi::log()->error('Invalid transaction data', [
            'date' => $date,
            'transaction' => $transaction,
          ]);
          continue;
        }

        $paymentId = $transaction['entity_id'];
        if (!preg_match('/^pay_/', $paymentId)) {
          $returnValues['errors']++;
          \Civi::log()->error('Invalid payment ID', [
            'payment_id' => $paymentId,
            'date' => $date,
          ]);
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
          \Civi::log()->error('Invalid settlement date', [
            'payment_id' => $paymentId,
            'settled_at' => $transaction['settled_at'],
            'date' => $date,
          ]);
          continue;
        }

        try {
          $contribution = Contribution::get(FALSE)
            ->addSelect('id', 'Contribution_Details.Settlement_Id')
            ->addWhere('trxn_id', 'LIKE', '%' . $paymentId)
            ->addWhere('is_test', '=', $this->isTest)
            ->execute()->first();

          if (!$contribution) {
            $returnValues['errors']++;
            \Civi::log()->error('Contribution not found', [
              'payment_id' => $paymentId,
              'date' => $date,
            ]);
            continue;
          }

          // Skip if already settled.
          if (!empty($contribution['Contribution_Details.Settlement_Id'])) {
            $returnValues['processed']++;
            \Civi::log()->debug('Contribution already settled', [
              'contribution_id' => $contribution['id'],
              'payment_id' => $paymentId,
              'date' => $date,
            ]);
            continue;
          }

          $updateResult = Contribution::update(FALSE)
            ->addWhere('trxn_id', 'LIKE', '%' . $paymentId)
            ->addWhere('is_test', '=', $this->isTest)
            ->addValue($settlementIdField, $settlementId)
            ->addValue($settlementDateField, $settlementDate)
            ->execute();

          if ($updateResult->rowCount) {
            $returnValues['updated']++;
            \Civi::log()->debug('Updated contribution', [
              'contribution_id' => $contribution['id'],
              'payment_id' => $paymentId,
              'settlement_id' => $settlementId,
              'settlement_date' => $settlementDate,
            ]);
          }
          else {
            $returnValues['errors']++;
            \Civi::log()->error('Failed to update contribution', [
              'contribution_id' => $contribution['id'],
              'payment_id' => $paymentId,
              'date' => $date,
            ]);
          }
        }
        catch (Exception $e) {
          $returnValues['errors']++;
          \Civi::log()->error('Failed to process contribution', [
            'payment_id' => $paymentId,
            'date' => $date,
            'error_message' => $e->getMessage(),
          ]);
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
      \Civi::log()->error('Max retries exceeded', [
        'error_message' => $e->getMessage(),
      ]);
      throw new Exception("Max retries exceeded: " . $e->getMessage());
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
    \Civi::log()->error('API execution failed', [
      'error_message' => $e->getMessage(),
      'params' => $params,
    ]);
    return civicrm_api3_create_error("Failed to process settlements: " . $e->getMessage(), [
      'processed' => 0,
      'updated' => 0,
      'errors' => 1,
    ]);
  }
}
