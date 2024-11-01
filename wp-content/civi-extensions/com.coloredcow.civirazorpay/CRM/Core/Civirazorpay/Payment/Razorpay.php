<?php

use Razorpay\Api\Api;

class CRM_Core_Civirazorpay_Payment_Razorpay extends CRM_Core_Payment {
    const CHARSET = 'utf-8';

    protected $_mode = null;

    public function __construct($mode, &$paymentProcessor) {
        $this->_mode = $mode;
        $this->_paymentProcessor = $paymentProcessor;
    }

    public function doDirectPayment(&$params) {
        // Load Razorpay API keys from configuration
        $apiKey = $this->_paymentProcessor['user_name'];
        $apiSecret = $this->_paymentProcessor['password'];
        $api = new Api($apiKey, $apiSecret);

        // Create a Razorpay order
        $orderData = [
            'amount' => $params['amount'] * 100, // Convert to paise
            'currency' => 'INR',
            'receipt' => 'RCPT-' . uniqid(),
            'payment_capture' => 1
        ];
        $order = $api->order->create($orderData);

        // Save order ID to pass it to the frontend
        $params['trxn_id'] = $order->id;

        // Pass the order details to the Razorpay checkout template
        $smarty = \CRM_Core_Smarty::singleton();
        $smarty->assign('razorpayKey', $apiKey);
        $smarty->assign('orderId', $order->id);
        $smarty->assign('amount', $params['amount'] * 100);
        $smarty->assign('currency', 'INR');

        // Render the checkout template
        echo $smarty->fetch('CRM/Civirazorpay/Templates/razorpay_checkout.tpl');
        exit;
    }

    public function handlePaymentNotification() {
        // Handle Razorpay IPN here
    }
}
