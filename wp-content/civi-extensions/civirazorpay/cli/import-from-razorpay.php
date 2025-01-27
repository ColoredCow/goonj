<?php

/**
 * @file
 * CLI Script to Import Razorpay Subscriptions into CiviCRM.
 *
 * Usage:
 *   php import-from-razorpay.php.
 */

use Civi\Api4\Contribution;
use Civi\Api4\ContributionRecur;
use Civi\Api4\Individual;
use Civi\Payment\System;
use Civi\Api4\Email;
use Civi\Api4\Phone;
use Civi\Api4\PaymentProcessor;

require_once __DIR__ . '/../lib/razorpay/Razorpay.php';

const RP_IMPORT_SUBSCRIPTIONS_LIMIT = 100;
const RP_API_MAX_RETRIES = 3;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

/**
 *
 */
class RazorpaySubscriptionImporter {

  private $api;
  private $skip = 0;
  private $totalImported = 0;
  private $retryCount = 0;
  private $isTest;
  private $processor;
  private $processorID;

  public function __construct() {
    civicrm_initialize();

    $this->isTest = FALSE;

    $processorConfig = PaymentProcessor::get(FALSE)
      ->addWhere('payment_processor_type_id:name', '=', 'Razorpay')
      ->addWhere('is_test', '=', $this->isTest)
      ->execute()->single();

    $this->processor = System::singleton()->getByProcessor($processorConfig);
    $this->processorID = $this->processor->getID();
    $this->api = $this->processor->initializeApi();
  }

  /**
   * Start the subscription import process.
   */
  public function run($limit = NULL): void {
    echo "=== Importing Razorpay Subscriptions into CiviCRM ===\n";

    while (TRUE) {
      try {
        echo "Fetching subscriptions (skip: $this->skip, count: " . $limit . ")\n";

        $subscriptions = $this->fetchSubscriptions($limit);

        if (empty($subscriptions)) {
          echo "No more subscriptions to import. Total imported: $this->totalImported\n";
          break;
        }

        foreach ($subscriptions as $subscription) {
          $this->processSubscription($subscription);
          $this->totalImported++;
        }

        $this->skip += $limit;
        $this->retryCount = 0;

      }
      catch (Exception $e) {
        $this->handleRetry($e);
      }
    }
    echo "=== Import Completed. Total Subscriptions Imported: $this->totalImported ===\n";
  }

  /**
   * Fetch subscriptions from Razorpay API.
   *
   * @return array
   */
  private function fetchSubscriptions($limit): array {
    $options = [
      'count' => $limit,
      'skip' => $this->skip,
    // Only fetch active subscriptions.
      'status' => 'active',
    ];

    $response = $this->api->subscription->all($options);
    $responseArray = $response->toArray();

    return $responseArray['items'] ?? [];
  }

  /**
   * Handle an individual Razorpay subscription.
   *
   * @param array $subscription
   */
  private function processSubscription(array $subscription): void {
    echo "Processing Subscription ID: {$subscription['id']}\n";
    echo "Status: {$subscription['status']}\n";
    echo "Customer ID: {$subscription['customer_id']}\n";

    try {
      $contactID = $this->handleCustomerData($subscription);

      if (!$contactID) {
        throw new Exception("No valid contact could be associated with subscription {$subscription['id']}");
      }

      $this->handleContributionRecur($subscription, $contactID);
    }
    catch (Exception $e) {
      echo "Error processing subscription {$subscription['id']}: " . $e->getMessage() . "\n";
    }
  }

  /**
   * Handle customer data associated with the subscription.
   *
   * @param array $subscription
   */
  private function handleCustomerData(array $subscription) {
    $customerId = $subscription['customer_id'] ?? NULL;
    $notes = $subscription['notes'] ?? NULL;
    $mobile = $notes['mobile'] ?? NULL;
    $email = $notes['email'] ?? NULL;
    $name = $notes['name'] ?? NULL;

    $findContactArgs = [];

    if ($name || $mobile) {
      $findContactArgs = [
        'name' => $name ?? 'Unknown Customer',
        'email' => $email ?? NULL,
        'phone' => $mobile ?? NULL,
      ];
    }
    elseif ($customerId) {
      $customer = $this->api->customer->fetch($customerId);
      $findContactArgs = [
        'name' => $customer->name ?? 'Unknown Customer',
        'email' => $customer->email ?? NULL,
        'phone' => $customer->contact ?? NULL,
      ];
    }

    if (empty($findContactArgs)) {
      echo "No customer data available for subscription {$subscription['id']}\n";
      return NULL;
    }

    $contactID = $this->findContact($findContactArgs);

    if ($contactID) {
      echo "Contact found/created successfully. Contact ID: $contactID\n";
      return $contactID;
    }
    echo "Could not identify a unique contact. Logged for manual intervention.\n";

    return NULL;
  }

  /**
   * Find or create a CiviCRM contact based on email and phone.
   *
   * @param array $params
   *   - name: Customer name (optional, used when creating a new contact).
   *   - email: Customer email (optional).
   *   - phone: Customer phone number (optional).
   *
   * @return int|null
   *   Contact ID if found or created, null if manual intervention is required.
   */
  public function findContact($params) {
    // Case 1: No email, No phone.
    if (empty($params['email']) && empty($params['phone'])) {
      $this->logManualIntervention('Neither email nor phone is provided.', $params);
      return NULL;
    }

    // Case 2: No email, Phone available.
    if (empty($params['email']) && !empty($params['phone'])) {
      return $this->handlePhoneSearch($params['phone'], $params['name']);
    }

    // Case 3: Email available, No phone.
    if (!empty($params['email']) && empty($params['phone'])) {
      return $this->handleEmailSearch($params['email'], $params['name']);
    }

    // Case 4: Email available, Phone available.
    if (!empty($params['email']) && !empty($params['phone'])) {
      return $this->handleEmailAndPhoneSearch($params['email'], $params['phone'], $params['name']);
    }

    return NULL;
  }

  /**
   * Handle search by phone.
   */
  private function handlePhoneSearch($phone, $name) {
    $phoneResults = Phone::get(FALSE)
      ->addWhere('phone', '=', $phone)
      ->execute();

    if ($phoneResults->count() === 1) {
      $contact = $phoneResults->first();
      return $contact['contact_id'];
    }

    if ($phoneResults->count() > 1) {
      $this->logManualIntervention('Multiple contacts found with the same phone number.', ['phone' => $phone]);
      return NULL;
    }

    // Create a new contact if no match.
    return $this->createContact(['name' => $name, 'phone' => $phone]);
  }

  /**
   * Handle search by email.
   */
  private function handleEmailSearch($email, $name) {
    $emailResults = Email::get(FALSE)
      ->addWhere('email', '=', $email)
      ->execute();

    if ($emailResults->count() === 1) {
      $contact = $emailResults->first();
      return $contact['contact_id'];
    }

    if ($emailResults->count() > 1) {
      $this->logManualIntervention('Multiple contacts found with the same email.', ['email' => $email]);
      return NULL;
    }

    // Create a new contact if no match.
    return $this->createContact(['name' => $name, 'email' => $email]);
  }

  /**
   * Handle search by both email and phone.
   */
  private function handleEmailAndPhoneSearch($email, $phone, $name) {
    $emailResults = Email::get(FALSE)
      ->addWhere('email', '=', $email)
      ->execute();

    $phoneResults = Phone::get(FALSE)
      ->addWhere('phone', '=', $phone)
      ->execute();

    $emailContactIDs = array_column($emailResults->jsonSerialize(), 'contact_id');
    $phoneContactIDs = array_column($phoneResults->jsonSerialize(), 'contact_id');

    // Find intersection of email and phone results.
    $commonContactIDs = array_intersect($emailContactIDs, $phoneContactIDs);

    if (count($commonContactIDs) === 1) {
      // Return the single matching contact.
      return reset($commonContactIDs);
    }

    if (count($commonContactIDs) > 1) {
      $this->logManualIntervention('Multiple contacts found with matching email and phone.', [
        'email' => $email,
        'phone' => $phone,
      ]);
      return NULL;
    }

    // Create a new contact if no match.
    return $this->createContact(['name' => $name, 'email' => $email, 'phone' => $phone]);
  }

  /**
   * Create a new Individual contact in CiviCRM with optional email and phone.
   *
   * @param array $params
   *   - name: Customer name (optional, defaults to 'Unknown Customer').
   *   - email: Customer email (optional).
   *   - phone: Customer phone number (optional).
   *
   * @return int|null
   *   Returns the created contact ID or null on failure.
   */
  private function createContact($params) {
    $fullName = $params['name'] ?? '';
    $nameParts = $this->splitName($fullName);

    // Step 1: Create an Individual contact.
    $contact = Individual::create(FALSE)
      ->addValue('source', 'Razorpay subscription import')
      ->addValue('first_name', $nameParts['firstName'])
      ->addValue('middle_name', $nameParts['middleName'])
      ->addValue('last_name', $nameParts['lastName'])
      ->execute()
      ->first();

    $contactId = $contact['id'];
    echo "Created new contact. Contact ID: $contactId\n";

    // Step 2: Add email if available.
    if (!empty($params['email'])) {
      Email::create(FALSE)
        ->addValue('contact_id', $contactId)
        ->addValue('email', $params['email'])
        ->addValue('is_primary', TRUE)
        ->execute();

      echo "Added email '{$params['email']}' to contact ID: $contactId\n";
    }

    // Step 3: Add phone if available.
    if (!empty($params['phone'])) {
      Phone::create(FALSE)
        ->addValue('contact_id', $contactId)
        ->addValue('phone', $params['phone'])
        ->addValue('is_primary', TRUE)
        ->execute();

      echo "Added phone '{$params['phone']}' to contact ID: $contactId\n";
    }

    return $contactId;

  }

  /**
   * Split a full name into first, middle, and last names.
   *
   * @param string $fullName
   *   The full name to be split.
   *
   * @return array
   *   An array with keys 'firstName', 'middleName', 'lastName'.
   */
  private function splitName(string $fullName): array {
    $nameParts = preg_split('/\s+/', trim($fullName));

    $firstName = '';
    $middleName = '';
    $lastName = '';

    $count = count($nameParts);

    if ($count === 1) {
      $firstName = $nameParts[0];
    }
    elseif ($count === 2) {
      $firstName = $nameParts[0];
      $lastName = $nameParts[1];
    }
    elseif ($count === 3) {
      $firstName = $nameParts[0];
      $middleName = $nameParts[1];
      $lastName = $nameParts[2];
    }
    elseif ($count > 3) {
      $firstName = $nameParts[0];
      $lastName = array_pop($nameParts);
      $middleName = implode(' ', $nameParts);
    }

    return [
      'firstName' => $firstName,
      'middleName' => $middleName,
      'lastName' => $lastName,
    ];
  }

  /**
   * Log manual intervention cases.
   */
  private function logManualIntervention($message, $params) {
    echo "Manual Intervention Required: $message\n";
    \Civi::log('razorpay')->warning($message, $params);
  }

  /**
   * Map and create ContributionRecur in CiviCRM from a Razorpay subscription.
   *
   * @param array $subscription
   * @param int $contactID
   */
  private function handleContributionRecur(array $subscription, int $contactID): void {
    echo "Creating ContributionRecur for Subscription ID: {$subscription['id']}\n";

    // Mapping Razorpay data to CiviCRM fields.
    $amount = $subscription['quantity'] ?? 0;
    $currency = strtoupper($subscription['currency'] ?? 'INR');
    $frequencyUnitMap = [
      'day' => 'day',
      'week' => 'week',
      'month' => 'month',
      'year' => 'year',
    ];

    $frequencyUnit = $frequencyUnitMap[$subscription['payment_method']] ?? 'month';
    $frequencyInterval = 1;
    $installments = $subscription['total_count'] ?? NULL;
    $startDate = date('Y-m-d H:i:s', $subscription['start_at'] ?? time());

    // Generate unique invoice ID and transaction ID.
    $invoiceID = md5($subscription['id']);

    // Validate required fields.
    if (!$amount || !$currency || !$frequencyUnit) {
      throw new Exception("Invalid subscription data. Required fields are missing.");
    }

    try {
      $contributionRecur = ContributionRecur::create(FALSE)
        ->addValue('contact_id', $contactID)
        ->addValue('amount', $amount)
        ->addValue('currency', $currency)
        ->addValue('frequency_unit', $frequencyUnit)
        ->addValue('frequency_interval', $frequencyInterval)
        ->addValue('installments', $installments)
        ->addValue('start_date', $startDate)
        ->addValue('create_date', date('Y-m-d H:i:s'))
        ->addValue('modified_date', date('Y-m-d H:i:s'))
        ->addValue('processor_id', $subscription['id'])
        ->addValue('is_test', $this->isTest)
        ->addValue('contribution_status_id:name', 'In Progress')
        ->addValue('financial_type_id:name', 'Donation')
        ->addValue('payment_instrument_id:name', 'Credit Card')
        ->addValue('trxn_id', $invoiceID)
        ->addValue('invoice_id', $invoiceID)
        ->addValue('payment_processor_id', $this->processorID)
        ->execute();

      $contributionRecurID = $contributionRecur->first()['id'];

      if ($contributionRecurID) {
        $this->handleSubscriptionPayments($subscription['id'], $contributionRecurID, $contactID);
      }

      echo "ContributionRecur successfully created with ID: " . $contributionRecurID . "\n";

    }
    catch (Exception $e) {
      echo "Failed to create ContributionRecur: " . $e->getMessage() . "\n";
      $this->logManualIntervention('Failed to create ContributionRecur', $subscription);
    }
  }

  /**
   * Fetches and processes all payments (invoices) associated with a subscription.
   *
   * @param string $subscriptionId
   * @param int $contributionRecurID
   * @param int $contactID
   */
  private function handleSubscriptionPayments(string $subscriptionId, int $contributionRecurID, int $contactID): void {
    echo "Fetching invoices for Subscription ID: $subscriptionId\n";

    try {
      // Fetch all invoices related to the subscription.
      $response = $this->api->invoice->all(['subscription_id' => $subscriptionId]);
      $invoices = $response->toArray()['items'] ?? [];

      if (empty($invoices)) {
        echo "No invoices found for subscription: $subscriptionId\n";
        return;
      }

      foreach ($invoices as $invoice) {
        // Check if the invoice has a payment ID.
        if (!empty($invoice['payment_id'])) {
          echo "Processing payment for Invoice ID: {$invoice['id']}\n";
          $payment = $this->api->payment->fetch($invoice['payment_id']);
          $this->createContributionFromPayment($payment, $contributionRecurID, $contactID);
        }
        else {
          echo "Invoice ID: {$invoice['id']} has no associated payment.\n";
        }
      }
    }
    catch (Exception $e) {
      echo "Error fetching invoices for subscription $subscriptionId: " . $e->getMessage() . "\n";
      $this->logManualIntervention('Failed to fetch invoices for subscription', ['subscription_id' => $subscriptionId]);
    }
  }

  /**
   * Creates a Contribution record in CiviCRM from a Razorpay payment object.
   *
   * @param object $payment
   * @param int $contributionRecurID
   * @param int $contactID
   */
  private function createContributionFromPayment(object $payment, int $contributionRecurID, int $contactID): void {
    echo "Creating Contribution for Payment ID: {$payment['id']}\n";

    // Convert from smallest currency unit.
    $amount = $payment['amount'] / 100;
    $currency = strtoupper($payment['currency'] ?? 'INR');
    $paymentDate = date('Y-m-d H:i:s', $payment['created_at'] ?? time());
    $transactionId = $payment['id'];
    $invoiceId = md5(uniqid(rand(), TRUE));

    try {
      $contribution = Contribution::create(FALSE)
        ->addValue('contact_id', $contactID)
        ->addValue('financial_type_id:name', 'Donation')
        ->addValue('contribution_recur_id', $contributionRecurID)
        ->addValue('payment_instrument_id:name', 'Credit Card')
        ->addValue('receive_date', $paymentDate)
        ->addValue('total_amount', $amount)
        ->addValue('currency', $currency)
        ->addValue('trxn_id', $transactionId)
        ->addValue('invoice_id', $invoiceId)
        ->addValue('contribution_status_id:name', 'Completed')
        ->addValue('source', 'Imported from Razorpay')
        ->execute();

      echo "Contribution successfully created for Payment ID: $transactionId\n";
    }
    catch (Exception $e) {
      echo "Failed to create Contribution for Payment ID: {$payment['id']}: " . $e->getMessage() . "\n";
      $this->logManualIntervention('Failed to create contribution from payment', ['payment_id' => $payment['id']]);
    }
  }

  /**
   * Handle retry logic on failure.
   *
   * @param Exception $e
   */
  private function handleRetry(Exception $e): void {
    $this->retryCount++;
    echo "Error fetching subscriptions: " . $e->getMessage() . "\n";

    if ($this->retryCount >= RP_API_MAX_RETRIES) {
      echo "Maximum retries reached. Exiting...\n";
      exit(1);
    }

    echo "Retrying... ($this->retryCount/" . RP_API_MAX_RETRIES . ")\n";
    sleep(2);

    $this->run(RP_IMPORT_SUBSCRIPTIONS_LIMIT);
  }

}


try {
  $importer = new RazorpaySubscriptionImporter();
  $importer->run(RP_IMPORT_SUBSCRIPTIONS_LIMIT);
}
catch (\Exception $e) {
  print "Error: " . $e->getMessage() . "\n\n" . $e->getTraceAsString();
}
