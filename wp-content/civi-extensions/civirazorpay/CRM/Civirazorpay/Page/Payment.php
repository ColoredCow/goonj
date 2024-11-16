<?php

/**
 *
 */

use Civi\Api4\Contribution;
use Civi\Api4\Email;
use Civi\Api4\PaymentProcessor;

/**
 *
 */
class CRM_Civirazorpay_Page_Payment extends CRM_Core_Page {

  /**
   *
   */
  public function run() {
    $contributionId = CRM_Utils_Request::retrieve('contribution', 'Integer', $this);
    $paymentProcessorId = CRM_Utils_Request::retrieve('processor', 'Integer', $this);
    $qfKey = CRM_Utils_Request::retrieve('qfKey', 'String', $this);

    $data = $this->getDataForTemplate($contributionId, $paymentProcessorId, $qfKey);

    $this->assign($data);

    parent::run();
  }

  /**
   * Retrieve contribution details and primary email from contact.
   *
   * @param int $contributionId
   *   The contribution ID to retrieve.
   *
   * @return array
   *   Array containing amount, currency, email, and order ID.
   */
  public function getDataForTemplate($contributionId, $paymentProcessorId, $qfKey) {
    $contribution = Contribution::get(FALSE)
      ->addSelect('total_amount', 'currency', 'contact_id', 'trxn_id', 'payment_processor_id')
      ->addWhere('id', '=', $contributionId)
      ->execute()->single();

    $email = Email::get(FALSE)
      ->addWhere('is_primary', '=', TRUE)
      ->addWhere('contact_id', '=', $contribution['contact_id'])
      ->execute()->single();

    $organizationName = CRM_Core_BAO_Domain::getDomain()->name;

    $paymentProcessor = PaymentProcessor::get(FALSE)
      ->addSelect('user_name')
      ->addWhere('id', '=', $paymentProcessorId)
      ->execute()->single();

    return [
      'amount' => $contribution['total_amount'] * 100,
      'currency' => $contribution['currency'],
      'email' => $email['email'],
      'orderId' => $contribution['trxn_id'],
      'organizationName' => $organizationName,
      'apiKey' => $paymentProcessor['user_name'],
      'qfKey' => $qfKey,
    ];
  }

}
