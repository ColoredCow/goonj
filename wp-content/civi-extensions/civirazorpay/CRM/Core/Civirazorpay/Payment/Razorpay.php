<?php

require_once __DIR__ . '/../../../../lib/razorpay/Razorpay.php';

use Civi\Api4\Contribution;
use Civi\Api4\ContributionRecur;
use Civi\Payment\Exception\PaymentProcessorException;
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
   * Process payment by creating an order or subscription in Razorpay.
   *
   * @param array $params
   *   Payment parameters.
   * @param string $component
   *
   * @return array
   */
  public function doPayment(&$params, $component = 'contribute') {
    $apiKey = $this->_paymentProcessor['user_name'];
    $apiSecret = $this->_paymentProcessor['password'];
    $api = new Api($apiKey, $apiSecret);

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

        $subscription = $api->subscription->create([
          'plan_id' => $newPlan->id,
          'customer_notify' => 1,
        // Hardcoded to 12 cycles (e.g., one year)
          'total_count' => 12,
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
    \Civi::log()->info(__METHOD__, [
      'event' => $event,
    ]);
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
   *
   */
  private function processSubscriptionCharged($event) {
    $subscriptionId = $event['payload']['subscription']['entity']['id'];
    $paymentId = $event['payload']['payment']['entity']['id'];
    $amount = $event['payload']['payment']['entity']['amount'] / 100;
    $transactionDate = date('Y-m-d H:i:s', $event['payload']['payment']['entity']['created_at']);
    $last4CardDigits = $event['payload']['payment']['entity']['card']['last4'] ?? NULL;

    \Civi::log()->info(__METHOD__, [
      'subscriptionId' => $subscriptionId,
    ]);

    $isTestMode = $this->_mode === 'test';

    $recurringContribution = $this->getContributionRecurBySubId($subscriptionId);

    if ($recurringContribution) {
      $contactId = $recurringContribution['contact_id'];
      $financialTypeId = $recurringContribution['financial_type_id'];

      try {
        // Create a new contribution record for this charge.
        $newContribution = civicrm_api3('Contribution', 'create', [
          'contact_id' => $contactId,
          'financial_type_id' => $financialTypeId,
          'total_amount' => $amount,
          'contribution_recur_id' => $recurringContribution['id'],
        // Completed.
          'contribution_status_id' => 1,
          'trxn_id' => $paymentId,
          'receive_date' => $transactionDate,
        ]);

        // Create a Payment entity for this transaction.
        civicrm_api3('Payment', 'create', [
          'contribution_id' => $newContribution['id'],
          'total_amount' => $amount,
          'payment_instrument_id' => $this->_paymentProcessor['payment_instrument_id'],
          'trxn_id' => $paymentId,
          'credit_card_pan' => $last4CardDigits,
        ]);

        \Civi::log()->info("Recurring payment processed: $paymentId for subscription $subscriptionId", [
          'contribution_id' => $newContribution['id'],
          'amount' => $amount,
        ]);
      }
      catch (\Exception $e) {
        \Civi::log()->error("Failed to create Contribution or Payment for recurring payment: $paymentId", [
          'error' => $e->getMessage(),
          'subscription_id' => $subscriptionId,
        ]);
      }
    }
    else {
      \Civi::log()->error("Failed to find ContributionRecur record for subscription: $subscriptionId");
    }
  }

  /**
   *
   */
  private function processSubscriptionActivated($event) {
    $subscriptionId = $event['payload']['subscription']['entity']['id'];
    $startDate = date('Y-m-d H:i:s', $event['payload']['subscription']['entity']['start_at']);
    $endDate = date('Y-m-d H:i:s', $event['payload']['subscription']['entity']['end_at']);
    // 2 = Active, 3 = Cancelled/Pending
    $status = $event['payload']['subscription']['entity']['status'] === 'active' ? 2 : 3;

    // Update the ContributionRecur record in CiviCRM.
    try {
      ContributionRecur::update(FALSE)
        ->addValue('processor_id', $subscriptionId)
        ->addValue('contribution_status_id', $status)
        ->addValue('start_date', $startDate)
        ->addValue('end_date', $endDate)
        ->addWhere('processor_id', '=', $subscriptionId)
        ->execute();

      \Civi::log()->info("Subscription activated: $subscriptionId", [
        'start_date' => $startDate,
        'end_date' => $endDate,
      ]);
    }
    catch (\Exception $e) {
      \Civi::log()->error("Failed to update ContributionRecur for subscription activation: $subscriptionId", [
        'error' => $e->getMessage(),
      ]);
    }
  }

  /**
   *
   */
  private function processSubscriptionCompleted($event) {
    $subscriptionId = $event['payload']['subscription']['entity']['id'];

    // Update the ContributionRecur record to mark the subscription as complete.
    ContributionRecur::update(FALSE)
    // Completed/Ended status.
      ->addValue('contribution_status_id', 5)
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
  private function getContributionRecurBySubId($razorpaySubId) {
    $isTestMode = $this->_mode === 'test';

    $contributionRecur = ContributionRecur::get(FALSE)
      ->addWhere('trxn_id', '=', $razorpaySubId)
      ->addWhere('is_test', '=', $isTestMode ? TRUE : FALSE)
      ->setLimit(1)
      ->execute()
      ->first();

    return $contributionRecur;
  }

  /**
   *
   */
  public function checkConfig() {
    // @todo
    return [];
  }

}
