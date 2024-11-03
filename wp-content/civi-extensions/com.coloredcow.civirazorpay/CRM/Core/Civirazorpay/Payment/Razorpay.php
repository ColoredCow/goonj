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
    \CRM_Core_Error::debug_log_message('Razorpay doPayment called');
    // Initialize Razorpay API.
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

      \Civi::log()->info(__FUNCTION__, [
        'order' => $order,
      ]);
    }
    catch (\Exception $e) {
      \Civi::log()->info(__FUNCTION__, [
        'e' => $e,
      ]);
      throw new PaymentProcessorException('Error creating Razorpay order: ' . $e->getMessage());
    }

    // Mark contribution as pending in CiviCRM.
    // Pending.
    $params['contribution_status_id'] = 2;

    $successUrl = CRM_Utils_System::url('civicrm/contribute/transact', "_qf_ThankYou_display=1&qfKey={$params['qfKey']}", TRUE, NULL, FALSE);
    $failUrl = CRM_Utils_System::url('civicrm/contribute/transact', "_qf_Main_display=1&qfKey={$params['qfKey']}&cancel=1", TRUE, NULL, FALSE);

    $razorpayRedirectUrl = "https://checkout.razorpay.com/v1/checkout.js?order_id={$order->id}&key_id={$apiKey}&prefill[email]={$params['email']}&callback_url=" . urlencode($successUrl) . "&cancel_url=" . urlencode($failUrl);

    \Civi::log()->info(__FUNCTION__, [
      'params' => $params,
      'order' => $order,
      'razorpayRedirectUrl' => $razorpayRedirectUrl,
      'successUrl' => $successUrl,
      'failUrl' => $failUrl,
    ]);

    // Allow each CMS to do a pre-flight check before redirecting to Razorpay.
    CRM_Core_Config::singleton()->userSystem->prePostRedirect();
    CRM_Utils_System::setHttpHeader("HTTP/1.1 303 See Other", '');
    CRM_Utils_System::redirect($razorpayRedirectUrl);
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
