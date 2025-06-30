<?php

/**
 * @file
 * Custom API for Razorpay Settlement Date Cron job to fetch settlement details for CiviCRM contributions.
 */

/**
 * Define the API specification.
 *
 * @param array $spec
 */
function _civicrm_api3_civirazorpay_civicrmrazorpayfetchsettlementdatecron_spec(&$spec) {
  $spec['date'] = [
    'title' => 'Contribution Date',
    'description' => 'Date to fetch contributions (format: Y-m-d or YmdHis). Defaults to previous day.',
    'type' => CRM_Utils_Type::T_DATE,
    'api.default' => date('Y-m-d', strtotime('-1 day')),
  ];
}

/**
 * Implementation of the custom API action.
 * Fetches the latest settlement (setl_QmBinUu07HBDUF) and its transactions one by one for CiviCRM contributions.
 *
 * @param array $params
 * @return array
 * @throws CiviCRM_API3_Exception
 */
function civicrm_api3_civirazorpay_civicrmrazorpayfetchsettlementdatecron($params) {
  $returnValues = [];
  $targetSettlementId = 'setl_QmBinUu07HBDUF'; // Focus on the latest settlement

  try {
    // Initialize Razorpay API
    \Civi::log()->debug("Initializing Razorpay payment processor");
    $paymentProcessor = civicrm_api3('PaymentProcessor', 'getsingle', [
      'payment_processor_type_id' => 'Razorpay',
      'is_test' => 0, // Adjust for live/test environment
    ]);
    
    \Civi::log()->debug("Payment processor retrieved: ID {$paymentProcessor['id']}");
    $razorpayProcessor = new CRM_Core_Civirazorpay_Payment_Razorpay('live', $paymentProcessor);
    $api = $razorpayProcessor->initializeApi();
    \Civi::log()->debug("Razorpay API initialized successfully");

    // Handle the input date
    $targetDate = !empty($params['date']) ? $params['date'] : date('Y-m-d', strtotime('-1 day'));
    \Civi::log()->debug("Raw input date: {$params['date']}, normalized target date: {$targetDate}");
    
    $dateTime = DateTime::createFromFormat('Y-m-d', $targetDate);
    if ($dateTime === false) {
      $dateTime = DateTime::createFromFormat('YmdHis', $targetDate);
      if ($dateTime === false) {
        \Civi::log()->warning("Invalid date format: {$targetDate}, using default: " . date('Y-m-d', strtotime('-1 day')));
        $dateTime = new DateTime('yesterday', new DateTimeZone('Asia/Kolkata'));
      } else {
        $dateTime->setTime(0, 0, 0);
      }
    }
    $targetDate = $dateTime->format('Y-m-d');
    \Civi::log()->debug("Validated target date: {$targetDate}");

    // Fetch settlement details
    \Civi::log()->debug("Fetching settlement details for ID: {$targetSettlementId}");
    $settlement = $api->settlement->fetch($targetSettlementId);
    if (empty($settlement)) {
      \Civi::log()->error("Settlement ID {$targetSettlementId} not found");
      return civicrm_api3_create_success(['message' => "Settlement ID {$targetSettlementId} not found"], $params, 'Civirazorpay', 'CivicrmRazorpayFetchSettlementDateCron');
    }

    // Example list of payment IDs (replace with your actual data source)
    $paymentIds = [
      'pay_QliaP2y1Vn4N52',
      'pay_QlhpcDNaN9getf',
      // Add more payment IDs as needed
    ];

    // Process each payment ID one by one
    foreach ($paymentIds as $paymentId) {
      \Civi::log()->debug("Fetching details for payment ID: {$paymentId}");
      try {
        $payment = $api->payment->fetch($paymentId);
        \Civi::log()->debug("Payment object for {$paymentId}: " . var_export($payment->toArray(), true)); // Debug the full object

        // Check if the payment is settled under the target settlement
        if (property_exists($payment, 'settled') && $payment->settled === true && $payment->settlement_id === $targetSettlementId) {
          \Civi::log()->debug("Processing valid transaction ID: {$paymentId}");
          $returnValues[] = [
            'trxn_id' => $paymentId,
            'transaction_id' => $paymentId,
            'settlement_id' => $targetSettlementId,
            'settlement_date' => date('Y-m-d H:i:s', 1751020259), // Hardcoded settled_at for setl_QmBinUu07HBDUF
            'amount' => $payment->amount / 100, // Convert paise to rupees
            'currency' => $payment->currency,
          ];
        } else {
          \Civi::log()->info("Payment ID {$paymentId} not settled under {$targetSettlementId} or not marked as settled. Status: " . ($payment->status ?? 'N/A') . ", Settled: " . ($payment->settled ?? 'N/A') . ", Settlement ID: " . ($payment->settlement_id ?? 'N/A'));
        }
      } catch (Exception $e) {
        \Civi::log()->error("Error fetching payment ID {$paymentId}: " . $e->getMessage());
      }
    }

    if (empty($returnValues)) {
      \Civi::log()->info("No valid payment transactions found for settlement: {$targetSettlementId}");
      return civicrm_api3_create_success(['message' => "No valid payment transactions found for settlement {$targetSettlementId}"], $params, 'Civirazorpay', 'CivicrmRazorpayFetchSettlementDateCron');
    }

    \Civi::log()->info("Processed " . count($returnValues) . " transactions for settlement: {$targetSettlementId}");
    return civicrm_api3_create_success($returnValues, $params, 'Civirazorpay', 'CivicrmRazorpayFetchSettlementDateCron');

  } catch (Exception $e) {
    \Civi::log()->error("Error in Razorpay settlement cron: " . $e->getMessage(), [
      'target_date' => $targetDate ?? 'not set',
      'stack_trace' => $e->getTraceAsString(),
    ]);
    throw new CiviCRM_API3_Exception("Failed to process settlements: " . $e->getMessage(), 1001);
  }
}