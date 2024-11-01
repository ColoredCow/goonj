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
    // Load Razorpay API keys from configuration.
    $apiKey = $this->_paymentProcessor['user_name'];
    $apiSecret = $this->_paymentProcessor['password'];
    $api = new Api($apiKey, $apiSecret);

    // Create a Razorpay order.
    $orderData = [
    // Convert to paise.
      'amount' => $params['amount'] * 100,
      'currency' => 'INR',
      'receipt' => 'RCPT-' . uniqid(),
      'payment_capture' => 1,
    ];
    $order = $api->order->create($orderData);

    // Save the order ID to pass it to the frontend.
    $params['trxn_id'] = $order->id;

    // Pass the order details to the Razorpay checkout template.
    $smarty = \CRM_Core_Smarty::singleton();
    $smarty->assign('razorpayKey', $apiKey);
    $smarty->assign('orderId', $order->id);
    $smarty->assign('amount', $params['amount'] * 100);
    $smarty->assign('currency', 'INR');

    // Render the checkout template.
    echo $smarty->fetch('razorpay_checkout.tpl');
    exit;
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
