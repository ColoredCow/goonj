<?php

require_once __DIR__ . '/../../../../lib/razorpay/Razorpay.php';

use Civi\Payment\Exception\PaymentProcessorException;
use Razorpay\Api\Api;

/**
 * Class CRM_Core_Civirazorpay_Payment_Razorpay
 * Handles Razorpay integration as a payment processor in CiviCRM.
 */
class CRM_Core_Civirazorpay_Payment_Razorpay extends CRM_Core_Payment {
  const CHARSET = 'utf-8';

  protected $_mode = NULL;

  /**
   * Constructor.
   */
  public function __construct($mode, &$paymentProcessor) {
    $this->_mode = $mode;
    $this->_paymentProcessor = $paymentProcessor;
  }

  /**
   * Process payment by creating an order in Razorpay and injecting checkout script.
   *
   * @param array $params
   *   Payment parameters.
   * @param string $component
   *
   * @return array
   */
  public function doPayment(&$params, $component = 'contribute') {
    $apiKey = $this->_paymentProcessor['user_name'];
    $apiSecret = $this->_paymentProcessor['password'];
    $api = new Api($apiKey, $apiSecret);

    // Create Razorpay order.
    try {
      $order = $api->order->create([
      // Amount in paise.
        'amount' => $params['amount'] * 100,
        'currency' => 'INR',
        'receipt' => 'RCPT-' . uniqid(),
      // Auto-capture on payment success.
        'payment_capture' => 1,
      ]);
    }
    catch (\Exception $e) {
      throw new PaymentProcessorException('Error creating Razorpay order: ' . $e->getMessage());
    }

    // 2 is pending.
    $params['contribution_status_id'] = 2;

    // Build the URL to redirect to the custom payment processing page.
    $redirectUrl = CRM_Utils_System::url(
        'civicrm/razorpay/payment',
        [
          'order_id' => $order->id,
          'amount' => $params['amount'] * 100,
          'currency' => 'INR',
          'qfKey' => $params['qfKey'],
        ],
        // Absolute URL.
        TRUE,
        // Fragment.
        NULL,
        // Add SID if enabled in Civi.
        FALSE
    );

    CRM_Utils_System::redirect($redirectUrl);
  }

  /**
   *
   */
  public function handlePaymentNotification() {
    $paymentProcessorID = isset($_GET['processor_id']) ? (int) $_GET['processor_id'] : 0;

    if (!$paymentProcessorID) {
      throw new CRM_Core_Exception("Missing or invalid payment processor ID");
    }

    try {
      $this->_paymentProcessor = civicrm_api3('payment_processor', 'getsingle', [
        'id' => $paymentProcessorID,
      ]);
    }
    catch (CiviCRM_API3_Exception $e) {
      \Civi::log()->error('Error fetching payment processor details: ' . $e->getMessage());
      throw new CRM_Core_Exception('Could not fetch payment processor details');
    }

    $rawData = file_get_contents("php://input");
    $event = json_decode($rawData, TRUE);

    \Civi::log()->info('Razorpay IPN received', ['rawData' => $rawData, 'parsedEvent' => $event]);

    $this->processPaymentNotification($params);
  }

  /**
   * Update CiviCRM based on outcome of the transaction processing.
   *
   * @param array $params
   *
   * @throws CRM_Core_Exception
   * @throws CiviCRM_API3_Exception
   */
  public function processPaymentNotification(array $params): void {
    // Obviously all the below variables need to be extracted from the params.
    if ($isSuccess) {
      civicrm_api3('Payment', 'create', [
        'contribution_id' => $contributionID,
        'total_amount' => $totalAmount,
        'payment_instrument_id' => $this->_paymentProcessor['payment_instrment_id'],
        'trxn_id' => $trxnID,
        'credit_card_pan' => $last4CardsOfCardIfReturnedHere,
      ]);
      // Perhaps you are saving a payment token for future use (a token
      // is a string provided by the processor to allow you to recharge the card)
      $paymentToken = civicrm_api3('PaymentToken', 'create', [
        'contact_id' => $params['contact_id'],
        'token' => $params['token'],
        'payment_processor_id' => $params['payment_processor_id'] ?? $this->_paymentProcessor['id'],
        'created_id' => CRM_Core_Session::getLoggedInContactID() ?? $params['contact_id'],
        'email' => $params['email'],
        'billing_first_name' => $params['billing_first_name'] ?? NULL,
        'billing_middle_name' => $params['billing_middle_name'] ?? NULL,
        'billing_last_name' => $params['billing_last_name'] ?? NULL,
        'expiry_date' => $this->getCreditCardExpiry($params),
        'masked_account_number' => $this->getMaskedCreditCardNumber($params),
        'ip_address' => CRM_Utils_System::ipAddress(),
      ]);
    }

    if ($thisIsABrowserIwantToRedirect) {
      // This url was stored in the doPayment example above.
      $redirectURL = CRM_Core_Session::singleton()->get("ipn_success_url_{$this->transaction_id}");
      CRM_Utils_System::redirect($redirectUrl);
    }
    // Or perhaps just exit out for a server call.
    CRM_Utils_System::civiExit();
  }

  /**
   *
   */
  public function checkConfig() {
    // @todo
    return [];
  }

}
