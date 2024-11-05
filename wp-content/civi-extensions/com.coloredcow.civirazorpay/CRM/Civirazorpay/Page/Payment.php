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

    $apiKey = $this->_paymentProcessor['user_name'];

    $data = [
      'apiKey' => $apiKey,
      'amount' => $amount,
      'currency' => $currency,
      'orderId' => $orderId,
      'qfKey' => $qfKey,
      'email' => $this->getUserEmail($qfKey),
    ];

    $this->assign($data);

    parent::run();
  }

  /**
   *
   */
  public function getUserEmail($qfKey) {
    return 'abhip099@gmail.com';
  }

}
