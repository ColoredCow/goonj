<?php

/**
 *
 */
class CRM_Civirazorpay_Page_Payment extends CRM_Core_Page {

  /**
   *
   */
  public function run() {
    $orderId = CRM_Utils_Request::retrieve('order_id', 'String', $this);
    $amount = CRM_Utils_Request::retrieve('amount', 'Integer', $this);
    $currency = CRM_Utils_Request::retrieve('currency', 'String', $this);
    $qfKey = CRM_Utils_Request::retrieve('qfKey', 'String', $this);

    // Ensure Razorpay key is available.
    $apiKey = civicrm_api3('PaymentProcessor', 'getvalue', [
      'return' => "user_name",
      'id' => $this->_paymentProcessor['id'],
      'is_test' => $this->_mode === 'test' ? 1 : 0,
    ]);

    // Inject Razorpay Checkout Script.
    echo '<script src="https://checkout.razorpay.com/v1/checkout.js"></script>';

    // HTML and JavaScript to auto-trigger Razorpay checkout.
    echo '
    <script>
      var options = {
        "key": "' . $apiKey . '",
        "amount": "' . $amount . '",
        "currency": "' . $currency . '",
        "name": "Your Organization",
        "description": "Contribution Payment",
        "order_id": "' . $orderId . '",
        "handler": function (response) {
          // Redirect to Thank You page with payment details
          window.location.href = "/civicrm/contribute/transact/?_qf_ThankYou_display=1&qfKey=' . $qfKey . '&payment_id=" + response.razorpay_payment_id + "&order_id=" + response.razorpay_order_id;
        },
        "prefill": {
          "email": "' . $this->getUserEmail($qfKey) . '"
        },
        "theme": {
          "color": "#528FF0"
        }
      };

      var rzp = new Razorpay(options);
      rzp.open();
    </script>
    ';

    // Prevent the rest of CiviCRM from rendering.
    CRM_Utils_System::civiExit();
  }

  /**
   *
   */
  public function getUserEmail($qfKey) {
    return 'abhip099@gmail.com';
  }

}
