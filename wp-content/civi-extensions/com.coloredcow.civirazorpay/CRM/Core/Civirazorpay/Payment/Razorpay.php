<?php

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
   * IPN Handler for Razorpay
   * This method will handle the IPN (Instant Payment Notification) or webhook response from Razorpay after payment completion
   *
   * @param array $params
   */
  public function handlePaymentNotification($params) {
    $orderId = $params['razorpay_order_id'];
    $paymentId = $params['razorpay_payment_id'];

    try {
      // Capture the payment.
      $payment = $this->razorpayApi->payment->fetch($paymentId);
      $payment->capture(['amount' => $payment->amount]);

      // Update contribution status to completed in CiviCRM.
      $contributionId = civicrm_api3('Contribution', 'get', [
        'trxn_id' => $orderId,
        'sequential' => 1,
      ])['values'][0]['id'];

      civicrm_api3('Contribution', 'create', [
        'id' => $contributionId,
      // Completed.
        'contribution_status_id' => 1,
      ]);
    }
    catch (\Exception $e) {
      CRM_Core_Error::debug_log_message('Error capturing Razorpay payment: ' . $e->getMessage());
    }
  }

  /**
   *
   */
  public function checkConfig() {
    // @todo
    return [];
  }

}
