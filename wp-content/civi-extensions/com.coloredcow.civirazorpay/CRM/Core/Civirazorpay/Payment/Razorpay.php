<?php

/**
 *
 */

use Razorpay\Api\Api;

/**
 *
 */
class CRM_Core_Civirazorpay_Payment_Razorpay extends CRM_Core_Payment {
  const CHARSET = 'utf-8';

  protected $_mode = NULL;

  public function __construct($mode, &$paymentProcessor) {
    $this->_mode = $mode;
    $this->_paymentProcessor = $paymentProcessor;
  }

  /**
   *
   */
  public function doPayment(&$params, $component = 'contribute') {
    $apiKey = $this->_paymentProcessor['user_name'];
    $apiSecret = $this->_paymentProcessor['password'];
    $api = new Api($apiKey, $apiSecret);

    $orderData = [
    // Convert to paise.
      'amount' => $params['amount'] * 100,
      'currency' => 'INR',
      'receipt' => 'RCPT-' . uniqid(),
      'payment_capture' => 1,
    ];
    $order = $api->order->create($orderData);

    // Mark contribution as pending in CiviCRM.
    $params['contribution_status_id'] = 2;

    // Return Pending (2) response for CiviCRM.
    return ['payment_status_id' => 2];
  }

  /**
   * Check if the configuration for this payment processor is valid.
   *
   * @return array An array of error messages, empty if no errors.
   */
  public function checkConfig() {
    // @todo
    return [];
  }

  /**
   *
   */
  public function handlePaymentNotification() {
    // Handle Razorpay IPN here.
  }

}
