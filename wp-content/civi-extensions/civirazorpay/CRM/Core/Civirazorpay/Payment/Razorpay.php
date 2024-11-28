<?php

require_once __DIR__ . '/../../../../lib/razorpay/Razorpay.php';

use Civi\Api4\Contribution;
use Civi\Api4\ContributionRecur;
use Civi\Payment\Exception\PaymentProcessorException;
use Civi\Payment\PropertyBag;
use Razorpay\Api\Api;

/**
 * Class CRM_Core_Civirazorpay_Payment_Razorpay
 * Handles Razorpay integration as a payment processor in CiviCRM.
 */
class CRM_Core_Civirazorpay_Payment_Razorpay extends CRM_Core_Payment {
  const CHARSET = 'utf-8';

  // Define constants for contribution statuses.
  const CONTRIB_STATUS_COMPLETED = 1;
  const CONTRIB_STATUS_PENDING = 2;
  const CONTRIB_STATUS_CANCELLED = 3;
  const CONTRIB_STATUS_FAILED = 4;

  protected $_mode = NULL;

  /**
   * Constructor.
   */
  public function __construct($mode, &$paymentProcessor) {
    $this->_mode = $mode;
    $this->_paymentProcessor = $paymentProcessor;
  }

  /**
   *
   */
  public function supportsRecurring() {
    return TRUE;
  }

  /**
   * Does this processor support cancelling recurring contributions through code.
   *
   * If the processor returns true it must be possible to take action from within CiviCRM
   * that will result in no further payments being processed.
   *
   * @return bool
   */
  protected function supportsCancelRecurring() {
    return TRUE;
  }

  /**
   * Does the processor support the user having a choice as to whether to cancel the recurring with the processor?
   *
   * If this returns TRUE then there will be an option to send a cancellation request in the cancellation form.
   *
   * This would normally be false for processors where CiviCRM maintains the schedule.
   *
   * @return bool
   */
  protected function supportsCancelRecurringNotifyOptional() {
    return TRUE;
  }

  /**
   *
   */
  public function checkConfig() {
    // @todo
    return [];
  }

  /**
   *
   */
  private function initializeApi() {
    $apiKey = $this->_paymentProcessor['user_name'];
    $apiSecret = $this->_paymentProcessor['password'];
    $api = new Api($apiKey, $apiSecret);

    return $api;
  }

  /**
   * Process payment by creating an order or subscription in Razorpay.
   *
   * @param array $params
   *   Payment parameters.
   * @param string $component
   *
   * @return array
   */
  public function doPayment(&$params, $component = 'contribute') {
    $api = $this->initializeApi();

    if (!empty($params['is_recur'])) {
      try {
        $freqUnitMap = [
          'day' => 'daily',
          'week' => 'weekly',
          'month' => 'monthly',
          'year' => 'yearly',
        ];

        $mappedFrequencyUnit = $freqUnitMap[$params['frequency_unit']] ?? NULL;

        if (!$mappedFrequencyUnit) {
          throw new PaymentProcessorException('Unsupported frequency unit: ' . $params['frequency_unit']);
        }

        $newPlan = $api->plan->create([
          'period' => $mappedFrequencyUnit,
          'interval' => $params['frequency_interval'],
          'item' => [
            'name' => "Plan for {$params['amount']} INR every {$params['frequency_interval']} {$params['frequency_unit']}",
            'amount' => $params['amount'] * 100,
            'currency' => 'INR',
            'description' => "Recurring payment of {$params['amount']} INR",
          ],
        ]);

        \Civi::log()->info("Razorpay subscription plan created: $newPlan->id");

        $installmentCount = $this->prepareInstallmentCount($params);

        $subscription = $api->subscription->create([
          'plan_id' => $newPlan->id,
          'customer_notify' => 0,
          'total_count' => $installmentCount,
          'quantity' => 1,
          'notes' => [
            'contribution_id' => $params['contributionID'],
          ],
        ]);

        ContributionRecur::update(FALSE)
          ->addValue('processor_id', $subscription->id)
          ->addWhere('id', '=', $params['contributionRecurID'])
          ->execute();

        $redirectUrl = CRM_Utils_System::url(
            'civicrm/razorpay/payment',
            [
              'contribution' => $params['contributionRecurID'],
              'processor' => $this->_paymentProcessor['id'],
              'qfKey' => $params['qfKey'],
              'isRecur' => 1,
            ],
            TRUE,
            NULL,
            FALSE
        );

        CRM_Utils_System::redirect($redirectUrl);
      }
      catch (\Exception $e) {
        \Civi::log()->error('PaymentProcessorException', ['exception' => $e]);
        throw new PaymentProcessorException('Error creating Razorpay subscription: ' . $e->getMessage());
      }
    }
    else {
      try {
        $order = $api->order->create([
        // Amount in paise.
          'amount' => $params['amount'] * 100,
          'currency' => 'INR',
          'receipt' => 'RCPT-' . uniqid(),
        // Auto-capture payment.
          'payment_capture' => 1,
        ]);

        civicrm_api3('Contribution', 'create', [
          'id' => $params['contributionID'],
          'trxn_id' => $order->id,
          'contribution_status_id' => self::CONTRIB_STATUS_PENDING,
        ]);

        $redirectUrl = CRM_Utils_System::url(
          'civicrm/razorpay/payment',
          [
            'contribution' => $params['contributionID'],
            'processor' => $this->_paymentProcessor['id'],
            'qfKey' => $params['qfKey'],
            'isRecur' => 0,
          ],
          TRUE,
          NULL,
          FALSE
        );

        CRM_Utils_System::redirect($redirectUrl);
      }
      catch (\Exception $e) {
        throw new PaymentProcessorException('Error creating Razorpay order: ' . $e->getMessage());
      }
    }
  }

  /**
   *
   */
  public function handlePaymentNotification() {
    $paymentProcessorID = isset($_GET['processor_id']) ? (int) $_GET['processor_id'] : 0;

    if (!$paymentProcessorID) {
      throw new CRM_Core_Exception("Missing or invalid payment processor ID");
    }

    try {
      $this->_paymentProcessor = civicrm_api3('payment_processor', 'getsingle', [
        'id' => $paymentProcessorID,
      ]);
    }
    catch (CiviCRM_API3_Exception $e) {
      \Civi::log()->error('Error fetching payment processor details: ', [
        'error' => $e,
      ]);
      throw new CRM_Core_Exception('Could not fetch payment processor details');
    }

    $rawData = file_get_contents("php://input");
    $event = json_decode($rawData, TRUE);

    $this->processPaymentNotification($event);
  }

  /**
   * Update CiviCRM based on outcome of the transaction processing.
   *
   * @param array $params
   *
   * @throws CRM_Core_Exception
   * @throws CiviCRM_API3_Exception
   */
  public function processPaymentNotification(array $event): void {
    if (isset($event['event'])) {
      switch ($event['event']) {
        case 'payment.captured':
          $this->processOneTimePayment($event);
          break;

        case 'subscription.activated':
          $this->processSubscriptionActivated($event);
          break;

        case 'subscription.charged':
          $this->processSubscriptionCharged($event);
          break;

        case 'subscription.completed':
          $this->processSubscriptionCompleted($event);
          break;

        default:
          \Civi::log()->warning('Unhandled webhook event: ' . $event['event']);
          break;
      }
    }
    else {
      \Civi::log()->error('Invalid Razorpay webhook payload', [
        'payload' => $event,
      ]);
    }

  }

  /**
   *
   */
  private function processOneTimePayment($params) {
    $razorpayOrderId = $params['payload']['payment']['entity']['order_id'] ?? NULL;
    $razorpayPaymentId = $params['payload']['payment']['entity']['id'] ?? NULL;
    $amount = $params['payload']['payment']['entity']['amount'] / 100;
    $last4CardDigits = $params['payload']['payment']['entity']['card']['last4'] ?? NULL;

    $contribution = $this->getContributionByOrderId($razorpayOrderId);

    if ($contribution) {
      $contributionID = $contribution['id'];
      $contactID = $contribution['contact_id'];

      civicrm_api3('Payment', 'create', [
        'contribution_id' => $contributionID,
        'total_amount' => $amount,
        'payment_instrument_id' => $this->_paymentProcessor['payment_instrument_id'],
        'trxn_id' => $razorpayPaymentId,
        'credit_card_pan' => $last4CardDigits,
      ]);

      civicrm_api3('Contribution', 'create', [
        'id' => $contributionID,
        'contribution_status_id' => self::CONTRIB_STATUS_COMPLETED,
      ]);
    }

    CRM_Utils_System::civiExit();
  }

  /**
   * Handle `subscription.activated` event.
   */
  private function processSubscriptionActivated(array $event) {
    $subscriptionId = $event['payload']['subscription']['entity']['id'] ?? NULL;

    if (!$subscriptionId) {
      \Civi::log()->error("Missing subscription ID in activated event");
      return;
    }

    // Update the ContributionRecur record.
    try {
      ContributionRecur::update(FALSE)
        ->addWhere('processor_id', '=', $subscriptionId)
      // Set to "In Progress" or equivalent.
        ->addValue('contribution_status_id', 2)
        ->execute();

      \Civi::log()->info("Subscription activated: {$subscriptionId}");
    }
    catch (Exception $e) {
      \Civi::log()->error("Failed to update ContributionRecur for subscription: {$subscriptionId}", [
        'error' => $e->getMessage(),
      ]);
    }
  }

  /**
   *
   */
  private function processSubscriptionCharged(array $event) {
    $subscriptionId = $event['payload']['subscription']['entity']['id'] ?? NULL;
    $paymentId = $event['payload']['payment']['entity']['id'] ?? NULL;
    // Convert from paise to INR.
    $amount = $event['payload']['payment']['entity']['amount'] / 100;

    if (!$subscriptionId || !$paymentId) {
      \Civi::log()->error("Missing subscription or payment ID in charged event");
      return;
    }

    try {
      $recurringContribution = $this->getContributionRecurBySubId($subscriptionId);

      if (!$recurringContribution) {
        \Civi::log()->error("No ContributionRecur found for subscription: {$subscriptionId}");
        return;
      }

      $contactId = $recurringContribution['contact_id'];
      $financialTypeId = $recurringContribution['financial_type_id'];

      $pendingContribution = $this->getPendingContributionByRecurId($recurringContribution['id']);

      if (!$pendingContribution) {
        $contributionToUpdate = civicrm_api3('Contribution', 'create', [
          'contact_id' => $contactId,
          'financial_type_id' => $financialTypeId,
          'total_amount' => $amount,
          'contribution_recur_id' => $recurringContribution['id'],
          'contribution_status_id' => self::CONTRIB_STATUS_COMPLETED,
          'trxn_id' => $paymentId,
          'receive_date' => date('Y-m-d H:i:s'),
          'is_test' => $recurringContribution['is_test'],
        ]);
      }
      else {
        $contributionToUpdate = $pendingContribution;
      }

      civicrm_api3('Payment', 'create', [
        'contribution_id' => $contributionToUpdate['id'],
        'total_amount' => $amount,
        'payment_instrument_id' => $recurringContribution['payment_instrument_id'] ?? NULL,
        'trxn_id' => $paymentId,
      ]);

      \Civi::log()->info("Recurring payment processed: {$paymentId} for subscription: {$subscriptionId}");
    }
    catch (Exception $e) {
      \Civi::log()->error("Failed to process recurring payment for subscription: {$subscriptionId}", [
        'error' => $e->getMessage(),
      ]);
    }
  }

  /**
   *
   */
  private function processSubscriptionCompleted($event) {
    $subscriptionId = $event['payload']['subscription']['entity']['id'];

    ContributionRecur::update(FALSE)
      ->addValue('contribution_status_id', self::CONTRIB_STATUS_COMPLETED)
      ->addWhere('processor_id', '=', $subscriptionId)
      ->execute();

    \Civi::log()->info("Subscription completed: $subscriptionId");
  }

  /**
   *
   */
  private function getContributionByOrderId($razorpayOrderId) {
    $isTestMode = $this->_mode === 'test';

    $contribution = Contribution::get(FALSE)
      ->addWhere('trxn_id', '=', $razorpayOrderId)
      ->addWhere('is_test', '=', $isTestMode ? TRUE : FALSE)
      ->setLimit(1)
      ->execute()
      ->first();

    return $contribution;
  }

  /**
   *
   */
  private function getPendingContributionByRecurId($recurId) {
    $isTestMode = $this->_mode === 'test';

    $contribution = Contribution::get(FALSE)
      ->addWhere('contribution_recur_id', '=', $recurId)
      ->addWhere('contribution_status_id', '=', self::CONTRIB_STATUS_PENDING)
      ->addWhere('is_test', '=', $isTestMode ? TRUE : FALSE)
      ->execute()
      ->first();

    return $contribution;
  }

  /**
   *
   */
  private function getContributionRecurBySubId($subscriptionId) {
    $isTestMode = $this->_mode === 'test';

    $recurringContribution = ContributionRecur::get(FALSE)
      ->addWhere('processor_id', '=', $subscriptionId)
      ->addWhere('is_test', '=', $isTestMode ? TRUE : FALSE)
      ->execute()
      ->first();

    return $recurringContribution;
  }

  /**
   * Cancel a recurring contribution in Razorpay.
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return array|null[]
   *
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function doCancelRecurring(PropertyBag $propertyBag) {
    if (!$propertyBag->has('isNotifyProcessorOnCancelRecur')) {
      $propertyBag->setIsNotifyProcessorOnCancelRecur(TRUE);
    }
    $notifyProcessor = $propertyBag->getIsNotifyProcessorOnCancelRecur();

    if (!$notifyProcessor) {
      return ['message' => ts('Successfully cancelled the subscription in CiviCRM ONLY.')];
    }

    if (!$propertyBag->has('recurProcessorID')) {
      $errorMessage = ts('The recurring contribution cannot be cancelled (No reference (processor_id) found).');
      \Civi::log()->error($errorMessage);
      throw new PaymentProcessorException($errorMessage);
    }

    $subscriptionId = $propertyBag->getRecurProcessorID();

    // Use Razorpay API to cancel the subscription.
    try {
      $api = $this->initializeApi();
      $subscription = $api->subscription->fetch($subscriptionId)->cancel();
    }
    catch (Exception $e) {
      $errorMessage = ts('Could not cancel Razorpay subscription: %1', [1 => $e->getMessage()]);
      \Civi::log()->error($errorMessage);
      throw new PaymentProcessorException($errorMessage);
    }

    // Update the ContributionRecur record in CiviCRM to reflect cancellation.
    try {
      ContributionRecur::update(FALSE)
        ->addWhere('processor_id', '=', $subscriptionId)
      // Cancelled.
        ->addValue('contribution_status_id', self::CONTRIB_STATUS_CANCELLED)
        ->addValue('cancel_date', date('Y-m-d H:i:s'))
        ->execute();
    }
    catch (Exception $e) {
      \Civi::log()->error('Failed to update ContributionRecur cancellation in CiviCRM.', [
        'subscription_id' => $subscriptionId,
        'error' => $e->getMessage(),
      ]);
    }

    return ['message' => ts('Successfully cancelled the subscription at Razorpay.')];
  }

  /**
   *
   */
  private function prepareInstallmentCount($params) {
    return $params['installments'] ?? 36;
  }

  /**
   * Get help text information (help, description, etc.) about this payment,
   * to display to the user.
   *
   * @param string $context
   *   Context of the text.
   *   Only explicitly supported contexts are handled without error.
   *   Currently supported:
   *   - contributionPageRecurringHelp (params: is_recur_installments, is_email_receipt)
   *   - contributionPageContinueText (params: amount, is_payment_to_existing)
   *   - cancelRecurDetailText:
   *     params:
   *       mode, amount, currency, frequency_interval, frequency_unit,
   *       installments, {membershipType|only if mode=auto_renew},
   *       selfService (bool) - TRUE if user doesn't have "edit contributions" permission.
   *         ie. they are accessing via a "self-service" link from an email receipt or similar.
   *   - cancelRecurNotSupportedText
   *
   * @param array $params
   *   Parameters for the field, context specific.
   *
   * @return string
   */
  public function getText($context, $params) {
    $text = parent::getText($context, $params);

    switch ($context) {
      case 'contributionPageRecurringHelp':
        if ($params['is_recur_installments']) {
          return ts('You can specify the number of installments for your contribution, or you can leave the number of installments blank to default to 36. In either case, you can choose to cancel at any time.');
        }
    }
    return $text;
  }

}
