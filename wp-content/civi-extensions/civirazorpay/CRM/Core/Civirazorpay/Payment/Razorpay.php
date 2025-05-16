<?php

require_once __DIR__ . '/../../../../lib/razorpay/Razorpay.php';

use Civi\Api4\Campaign;
use Civi\Api4\StateProvince;
use Civi\Api4\Country;
use Civi\Api4\Contribution;
use Civi\Api4\ContributionRecur;
use Civi\Payment\Exception\PaymentProcessorException;
use Civi\Payment\PropertyBag;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Civi\Api4\CustomField;

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
  public function initializeApi() {
    $apiKey = $this->_paymentProcessor['user_name'];
    $apiSecret = $this->_paymentProcessor['password'];
    $api = new Api($apiKey, $apiSecret);

    return $api;
  }

  /**
   * Validates the webhook signature using Razorpay's PHP SDK.
   *
   * @param string $webhookBody
   *   The raw webhook body received from Razorpay.
   * @param string|null $webhookSignature
   *   The signature sent in the `X-Razorpay-Signature` header.
   * @param string $webhookSecret
   *   The webhook secret key configured in Razorpay.
   *
   * @throws CRM_Core_Exception
   *   Throws an exception if the signature is invalid or missing.
   */
  private function validateWebhook($webhookBody, $webhookSignature, $webhookSecret) {
    if (empty($webhookSignature)) {
      throw new CRM_Core_Exception("Missing Razorpay webhook signature.");
    }

    $api = $this->initializeApi();
    $api->utility->verifyWebhookSignature($webhookBody, $webhookSignature, $webhookSecret);
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

        $contributions = Contribution::get(FALSE)
          ->addSelect('campaign_id.title', 'Contribution_Details.PAN_Card_Number')
          ->addWhere('id', '=', $params['contributionID'])
          ->execute()->first();

        $country = Country::get(FALSE)
          ->addSelect('name')
          ->addWhere('id', '=', $params['country-Primary'])
          ->execute()->first();

        $stateProvinces = StateProvince::get(FALSE)
          ->addSelect('name')
          ->addWhere('id', '=', $params['state_province-Primary'])
          ->execute()->first();

        $subscription = $api->subscription->create([
          'plan_id' => $newPlan->id,
          'customer_notify' => 0,
          'total_count' => $installmentCount,
          'quantity' => 1,
          'notes' => [
            'mobile' => $params['phone-Primary-2'] ?? '',
            'purpose' => $contributions['campaign_id.title'] ?? '',
            'name' => $params['first_name'] ?? '',
            'identity_type' => $contributions['Contribution_Details.PAN_Card_Number'] ?? '',
            'donor_email' => $params['email'] ?? '',
            'address' => $params['street_address-Primary'] ?? '',
            'pin' => $params['postal_code-Primary'] ?? '',
            'country' => $country['name'] ?? '',
            'state' => $stateProvinces['name'] ?? '',
            'city' => $params['city-Primary'] ?? '',
            'contribution_recur_id' => $params['contributionRecurID'] ?? '',
            'contact_id' => $params['contactID'] ?? '',
            'source' => 'CiviCRM Recurring Contribution',
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
              'component' => $component,
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

        $contributions = Contribution::get(FALSE)
          ->addSelect('campaign_id.title', 'Contribution_Details.PAN_Card_Number')
          ->addWhere('id', '=', $params['contributionID'])
          ->execute()->first();

        $country = Country::get(FALSE)
          ->addSelect('name')
          ->addWhere('id', '=', $params['country-Primary'])
          ->execute()->first();

        $stateProvinces = StateProvince::get(FALSE)
          ->addSelect('name')
          ->addWhere('id', '=', $params['state_province-Primary'])
          ->execute()->first();

        $order = $api->order->create([
        // Amount in paise.
          'amount' => $params['amount'] * 100,
          'currency' => 'INR',
          'receipt' => 'RCPT-' . uniqid(),
        // Auto-capture payment.
          'payment_capture' => 1,
          'notes' => [
            'mobile' => $params['phone-Primary-2'] ?? '',
            'purpose' => $contributions['campaign_id.title'] ?? '',
            'name' => $params['first_name'] ?? '',
            'identity_type' => $contributions['Contribution_Details.PAN_Card_Number'] ?? '',
            'donor_email' => $params['email'] ?? '',
            'address' => $params['street_address-Primary'] ?? '',
            'pin' => $params['postal_code-Primary'] ?? '',
            'country' => $country['name'] ?? '',
            'state' => $stateProvinces['name'] ?? '',
            'city' => $params['city-Primary'] ?? '',
            'contribution_id' => $params['contributionID'] ?? '',
            'contact_id' => $params['contactID'] ?? '',
            'source' => 'CiviCRM One-Time Contribution',
          ],
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
            'component' => $component,
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
   * @return string
   */
  public function getWebhookSecret(): string {
    return trim($this->_paymentProcessor['signature'] ?? '');
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

    $webhookSecret = $this->getWebhookSecret();

    if (!empty($webhookSecret)) {
      $sigHeader = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'];
      try {
        $this->validateWebhook($rawData, $sigHeader, $webhookSecret);
      }
      catch (SignatureVerificationError $e) {
        \Civi::log('razorpay')->error($this->getLogPrefix() . 'webhook signature validation error: ' . $e->getMessage());
        http_response_code(400);
        exit();
      }
    }

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

        case 'subscription.cancelled':
          $this->processSubscriptionCancelled($event);
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
    $panCard = $event['payload']['subscription']['entity']['notes']['identity_type'] ?? NULL;
    $campaignTitle = $event['payload']['subscription']['entity']['notes']['purpose'] ?? NULL;

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

      $sourceField = CustomField::get(FALSE)
        ->addSelect('id')
        ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
        ->addWhere('name', '=', 'PAN_Card_Number')
        ->execute()->single();

      $sourceFieldId = 'custom_' . $sourceField['id'];

      $campaign = Campaign::get(FALSE)
        ->addSelect('id')
        ->addWhere('title', '=', $campaignTitle)
        ->execute()->first();

      $campaignId = $campaign['id'] ?? NULL;

      \Civi::log()->info('Record payment for subscription id', [
        'subscriptionId' => $subscriptionId,
        'paymentId' => $paymentId,
        'pendingContribution' => $pendingContribution,
      ]);

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
          'payment_instrument_id' => $recurringContribution['payment_instrument_id'] ?? NULL,
          $sourceFieldId  => $panCard,
          'campaign_id' => $campaignId,
        ]);
      }
      else {
        $contributionToUpdate = $pendingContribution;

        \Civi::log()->info('Pending contribution exists, recording payment', [
          'contributionToUpdateId' => $contributionToUpdate['id'],
          'amount' => $amount,
          'payment_instrument_id' => $recurringContribution['payment_instrument_id'],
          'paymentId' => $paymentId,
        ]);

        civicrm_api3('Payment', 'create', [
          'contribution_id' => $contributionToUpdate['id'],
          'total_amount' => $amount,
          'payment_instrument_id' => $recurringContribution['payment_instrument_id'] ?? NULL,
          'trxn_id' => $paymentId,
        ]);
      }

      $contributionDate = Contribution::get(FALSE)
        ->addSelect('receipt_date')
        ->addWhere('id', '=', $contributionToUpdate['id'])
        ->execute()->first();

      $receiptDate = $contributionDate['receipt_date'];

      if (!$receiptDate) {
        $this->sendReceipt($contributionToUpdate['id']);
      }

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
  private function sendReceipt($contributionId) {
    try {
      civicrm_api3('Contribution', 'sendconfirmation', [
        'id' => $contributionId,
      ]);
      \Civi::log()->info("Receipt sent for contribution ID: {$contributionId}");
    }
    catch (Exception $e) {
      \Civi::log()->error("Failed to send receipt for contribution ID: {$contributionId}", [
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
   * Handle subscription cancellation from Razorpay webhook.
   *
   * @param array $event
   */
  private function processSubscriptionCancelled(array $event): void {
    $subscriptionData = $event['payload']['subscription']['entity'] ?? [];

    if (empty($subscriptionData['id']) || empty($subscriptionData['status']) || $subscriptionData['status'] !== 'cancelled') {
      \Civi::log()->error('Invalid or incomplete Razorpay subscription cancellation event', [
        'event' => $event,
      ]);
      return;
    }

    $subscriptionId = $subscriptionData['id'];

    $endedAtTimestamp = $subscriptionData['ended_at'] ?? NULL;
    $cancelDate = date('Y-m-d H:i:s', $endedAtTimestamp);

    try {
      $updateResult = ContributionRecur::update(FALSE)
        ->addWhere('processor_id', '=', $subscriptionId)
        ->addWhere('is_test', 'IN', [TRUE, FALSE])
        ->addValue('contribution_status_id:name', 'Cancelled')
        ->addValue('cancel_date', $cancelDate)
        ->addValue('cancel_reason', ts('Cancelled from Razorpay Dashboard'))
        ->execute()->first();

      if (!$updateResult) {
        \Civi::log('razorpay')->info('Could not cancel recurring contribution', ['razorpaySubscriptionID' => $subscriptionId]);
      }
      else {
        $contributionRecurId = $updateResult['id'];

        \Civi::log('razorpay')->info('Recurring contribution cancelled', [
          'contributionRecurID' => $contributionRecurId,
          'razorpaySubscriptionID' => $subscriptionId,
        ]);
      }

    }
    catch (\CiviCRM_API3_Exception $e) {
      \Civi::log()->error('Error updating CiviCRM ContributionRecur record on Razorpay subscription cancellation', [
        'error' => $e->getMessage(),
        'subscription_id' => $subscriptionId,
        'contribution_recur_id' => $contributionRecurId,
      ]);
    }
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
   *   - cancelRecurNotSupportedText.
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
          return ts('Please specify the number of times you want your recurring contribution to renew. You can choose to cancel at any time.');
        }
    }
    return $text;
  }

  /**
   * @return string
   */
  public function getLogPrefix(): string {
    return 'Razorpay(' . $this->getID() . '): ';
  }

}
