<?php

/**
 *
 */

use Civi\Api4\Contribution;
use Civi\Api4\ContributionRecur;
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
    $isRecur = CRM_Utils_Request::retrieve('isRecur', 'Integer', $this);

    $paymentProcessorId = CRM_Utils_Request::retrieve('processor', 'Integer', $this);
    $qfKey = CRM_Utils_Request::retrieve('qfKey', 'String', $this);
    $component = CRM_Utils_Request::retrieve('component', 'String', $this);

    $data = $this->getDataForTemplate($contributionId, $paymentProcessorId, $qfKey, (bool) $isRecur, $component);

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
  public function getDataForTemplate($id, $paymentProcessorId, $qfKey, $isRecur, $component) {
    $entityClass = $isRecur ? ContributionRecur::class : Contribution::class;

    $fields = $isRecur
        ? ['amount', 'currency', 'contact_id', 'processor_id']
        : ['total_amount', 'currency', 'contact_id', 'trxn_id', 'payment_processor_id'];

    $contribution = $entityClass::get(FALSE)
      ->addSelect(...$fields)
      ->addWhere('id', '=', $id)
      ->execute()
      ->single();

    $email = $this->getPrimaryEmail($contribution['contact_id']);
    $organizationName = CRM_Core_BAO_Domain::getDomain()->name;
    $paymentProcessor = $this->getPaymentProcessor($paymentProcessorId);

    $redirectURLBase = $this->getRedirectURLBase($component);

    return [
      'isRecur' => $isRecur,
      'amount' => ($isRecur ? $contribution['amount'] : $contribution['total_amount']) * 100,
      'currency' => $contribution['currency'],
      'email' => $email,
      'orderId' => $isRecur ? $contribution['processor_id'] : $contribution['trxn_id'],
      'organizationName' => $organizationName,
      'apiKey' => $paymentProcessor['user_name'],
      'qfKey' => $qfKey,
      'redirectURLBase' => $redirectURLBase,
    ];
  }

  /**
   *
   */
  private function getRedirectURLBase($component) {
    switch ($component) {
      case 'contribute':
        $redirectURLBase = '/civicrm/contribute/transact';
        break;

      case 'event':
        $redirectURLBase = '/civicrm/event/register';
        break;
    }

    return $redirectURLBase;
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
