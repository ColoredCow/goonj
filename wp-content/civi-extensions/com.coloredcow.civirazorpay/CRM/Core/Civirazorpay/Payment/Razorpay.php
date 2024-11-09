<?php

require_once __DIR__ . '/../../../../lib/razorpay/Razorpay.php';

use Civi\Api4\Contribution;
use Civi\Payment\Exception\PaymentProcessorException;
use Razorpay\Api\Api;

/**
 * Class CRM_Core_Civirazorpay_Payment_Razorpay
 * Handles Razorpay integration as a payment processor in CiviCRM.
 */
class CRM_Core_Civirazorpay_Payment_Razorpay extends CRM_Core_Payment {
  const CHARSET = 'utf-8';

  // Define constants for contribution statuses.
  const CONTRIB_STATUS_COMPLETED = 1;
  const CONTRIB_STATUS_PENDING = 2;
  const CONTRIB_STATUS_FAILED = 4;

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

    $contributionId = $params['contributionID'];

    // Save the Razorpay order ID in the contribution record.
    try {
      $result = civicrm_api3('Contribution', 'create', [
        'id' => $contributionId,
        'trxn_id' => $order->id,
        'contribution_status_id' => self::CONTRIB_STATUS_PENDING,
      ]);
    }
    catch (CiviCRM_API3_Exception $e) {
      throw new PaymentProcessorException('Error updating contribution with Razorpay order ID: ' . $e->getMessage());
    }

    $qfKey = $params['qfKey'];

    // Build the URL to redirect to the custom payment processing page.
    $redirectUrl = CRM_Utils_System::url(
        'civicrm/razorpay/payment',
        [
          'contribution' => $contributionId,
          'processor' => $this->_paymentProcessor['id'],
          'qfKey' => $qfKey,
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
      \Civi::log()->error('Error fetching payment processor details: ', [
        'error' => $e,
      ]);
      throw new CRM_Core_Exception('Could not fetch payment processor details');
    }

    $rawData = file_get_contents("php://input");
    $event = json_decode($rawData, TRUE);

    $this->processPaymentNotification($event);
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
    $isSuccess = $params['event'] === 'payment.captured';

    $razorpayOrderId = $params['payload']['payment']['entity']['order_id'] ?? NULL;
    $razorpayPaymentId = $params['payload']['payment']['entity']['id'] ?? NULL;
    $amount = $params['payload']['payment']['entity']['amount'] / 100;
    $last4CardDigits = $params['payload']['payment']['entity']['card']['last4'] ?? NULL;

    $contribution = $this->getContributionByOrderId($razorpayOrderId);
    $contributionID = $contribution['id'];
    $contactID = $contribution['contact_id'];

    if ($isSuccess) {
      civicrm_api3('Payment', 'create', [
        'contribution_id' => $contributionID,
        'total_amount' => $amount,
        'payment_instrument_id' => $this->_paymentProcessor['payment_instrument_id'],
        'trxn_id' => $razorpayPaymentId,
        'credit_card_pan' => $last4CardDigits,
      ]);

      civicrm_api3('Contribution', 'create', [
        'id' => $contributionID,
        'contribution_status_id' => self::CONTRIB_STATUS_COMPLETED,
      ]);

      \Civi::log()->info("Contribution ID $contributionID updated to Completed.");
    }
    else {
      civicrm_api3('Contribution', 'create', [
        'id' => $contributionID,
        'contribution_status_id' => self::CONTRIB_STATUS_FAILED,
      ]);

      \Civi::log()->info("Contribution ID $contributionID updated to Failed.");
    }

    CRM_Utils_System::civiExit();
  }

  /**
   *
   */
  private function getContributionByOrderId($razorpayOrderId) {
    $isTestMode = $this->_mode === 'test';

    $contribution = Contribution::get(FALSE)
      ->addWhere('trxn_id', '=', $razorpayOrderId)
      ->addWhere('is_test', '=', $isTestMode ? TRUE : FALSE)
      ->setLimit(1)
      ->execute()
      ->single();

    return $contribution;
  }

  /**
   *
   */
  public function checkConfig() {
    // @todo
    return [];
  }

}
