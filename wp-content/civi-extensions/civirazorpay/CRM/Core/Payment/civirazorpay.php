<?php
class CiviRazorpay extends CRM_Core_Payment {
      /**
   * Constructor
   *
   * @param string $mode
   *   (deprecated) The mode of operation: live or test.
   * @param array $paymentProcessor
   */
  public function __construct($mode, &$paymentProcessor) {
    parent::__construct($mode, $paymentProcessor);
    $this->_paymentProcessor = $paymentProcessor;
    error_log("CiviRazorpay initialized with payment processor: " . print_r($paymentProcessor, true));
}


    /**
   * This function reports any configuration errors.
   *
   * @return string the error message if any
   */
  public function checkConfig() {
    if (empty($this->_paymentProcessor)) {
        error_log("Payment processor configuration is missing.");
        return E::ts('Payment processor configuration is missing.');
    }

    if (empty($this->_paymentProcessor['user_name'])) {
        return E::ts('The "Bill To ID" is not set in Administer > CiviContribute > Payment Processor.');
    }

    return null; // No errors
}


public function doDirectPayment(&$params) {
    // Razorpay API keys
    $apiKey = $this->_paymentProcessor['user_name'];
    $apiSecret = $this->_paymentProcessor['password'];
    $api = new \Razorpay\Api\Api($apiKey, $apiSecret);

    // Create a Razorpay order
    $orderData = [
        'amount' => $params['amount'] * 100, // Convert to paise
        'currency' => 'INR',
        'receipt' => 'RCPT-' . uniqid(),
        'payment_capture' => 1
    ];
    $order = $api->order->create($orderData);

    // Store Razorpay Order ID as transaction ID in CiviCRM
    $params['trxn_id'] = $order->id;

    // Pass order details to Smarty template
    $smarty = \CRM_Core_Smarty::singleton();
    $smarty->assign('razorpayKey', $apiKey);
    $smarty->assign('orderId', $order->id);
    $smarty->assign('amount', $params['amount'] * 100); // Amount in paise
    $smarty->assign('currency', 'INR');

    // Render the Razorpay checkout template
    echo $smarty->fetch('CRM/Civirazorpay/razorpay_checkout.tpl');
    exit;
}

public function handlePaymentNotification() {
    $razorpayPaymentId = $_GET['payment_id'];
    $razorpayOrderId = $_GET['order_id'];

    $apiKey = $this->_paymentProcessor['user_name'];
    $apiSecret = $this->_paymentProcessor['password'];
    $api = new \Razorpay\Api\Api($apiKey, $apiSecret);

    try {
        $payment = $api->payment->fetch($razorpayPaymentId);
        
        if ($payment->status == 'captured') {
            // Retrieve contribution based on Razorpay order ID and set status to Completed
            $contributionID = $this->getContributionIDByTransactionID($razorpayOrderId);
            civicrm_api3('Contribution', 'create', [
                'id' => $contributionID,
                'contribution_status_id' => 1 // Completed
            ]);

            // Redirect to a thank-you page
            header("Location: /civicrm/contribute/success?contribution_id=" . $contributionID);
            exit;
        } else {
            CRM_Core_Error::debug_log_message('Razorpay payment failed');
            header("Location: /civicrm/contribute/fail");
            exit;
        }
    } catch (\Exception $e) {
        CRM_Core_Error::debug_log_message('Error in Razorpay IPN: ' . $e->getMessage());
    }
}

}
?>