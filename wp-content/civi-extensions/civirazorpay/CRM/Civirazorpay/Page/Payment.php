<?php

/**
 *
 */

use Civi\Api4;
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
    $contributionRecurId = CRM_Utils_Request::retrieve('contributionRecur', 'Integer', $this);
    $paymentProcessorId = CRM_Utils_Request::retrieve('processor', 'Integer', $this);
    $qfKey = CRM_Utils_Request::retrieve('qfKey', 'String', $this);

    $data = [];
    if ($contributionId) {
      // One-time payment.
      $data = $this->getDataForTemplate($contributionId, $paymentProcessorId, $qfKey, FALSE);
    }
    elseif ($contributionRecurId) {
      // Recurring payment.
      $data = $this->getDataForTemplate($contributionRecurId, $paymentProcessorId, $qfKey, TRUE);
    }
    else {
      throw new CRM_Core_Exception('Missing required parameter: contribution or contributionRecur');
    }

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
  public function getDataForTemplate($id, $paymentProcessorId, $qfKey, $isRecur) {
    $entity = $isRecur ? 'ContributionRecur' : 'Contribution';

    $fields = $isRecur
        ? ['amount', 'currency', 'contact_id', 'processor_id']
        : ['total_amount', 'currency', 'contact_id', 'trxn_id', 'payment_processor_id'];

    $contribution = Api4::$entity::get(FALSE)
      ->addSelect(...$fields)
      ->addWhere('id', '=', $id)
      ->execute()
      ->single();

    $email = $this->getPrimaryEmail($contribution['contact_id']);
    $organizationName = CRM_Core_BAO_Domain::getDomain()->name;
    $paymentProcessor = $this->getPaymentProcessor($paymentProcessorId);

    return [
      'amount' => ($isRecur ? $contribution['amount'] : $contribution['total_amount']) * 100,
      'currency' => $contribution['currency'],
      'email' => $email,
      'orderId' => $isRecur ? $contribution['processor_id'] : $contribution['trxn_id'],
      'organizationName' => $organizationName,
      'apiKey' => $paymentProcessor['user_name'],
      'qfKey' => $qfKey,
    ];
  }

  /**
   * Fetch the primary email for a contact.
   *
   * @param int $contactId
   *   Contact ID.
   *
   * @return string
   *   Primary email address.
   */
  private function getPrimaryEmail($contactId) {
    return Email::get(FALSE)
      ->addWhere('is_primary', '=', TRUE)
      ->addWhere('contact_id', '=', $contactId)
      ->execute()
      ->single()['email'];
  }

  /**
   * Fetch payment processor details.
   *
   * @param int $paymentProcessorId
   *   Payment processor ID.
   *
   * @return array
   *   Payment processor details.
   */
  private function getPaymentProcessor($paymentProcessorId) {
    return PaymentProcessor::get(FALSE)
      ->addSelect('user_name')
      ->addWhere('id', '=', $paymentProcessorId)
      ->execute()
      ->single();
  }

}
