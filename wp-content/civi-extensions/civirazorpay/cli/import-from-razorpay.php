<?php

/**
 * @file
 * CLI Script to Import Razorpay Subscriptions into CiviCRM.
 *
 * Usage:
 *   php import-from-razorpay.php.
 */

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

  public function __construct() {
    civicrm_initialize();

    $processorConfig = PaymentProcessor::get(FALSE)
      ->addWhere('payment_processor_type_id:name', '=', 'Razorpay')
      ->addWhere('is_test', '=', TRUE)
      ->execute()->single();

    $processor = System::singleton()->getByProcessor($processorConfig);
    $this->api = $processor->initializeApi();
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
      $this->handleCustomerData($subscription);
      // $this->mapSubscriptionToCiviCRM($subscription);
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
  private function handleCustomerData(array $subscription): void {
    $customerId = $subscription['customer_id'] ?? NULL;

    if ($customerId) {
      $customer = $this->api->customer->fetch($customerId);

      $contactID = $this->findContact([
        'name' => $customer->name ?? 'Unknown Customer',
        'email' => $customer->email ?? NULL,
        'phone' => $customer->contact ?? NULL,
      ]);

      if ($contactID) {
        echo "Contact found/created successfully. Contact ID: $contactID\n";
      }
      else {
        echo "Could not identify a unique contact. Logged for manual intervention.\n";
      }
    }
    else {
      echo "No customer data available for subscription {$subscription['id']}\n";
    }
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
    // Step 1: Create an Individual contact.
    $contact = Individual::create(FALSE)
      ->addValue('source', 'Razorpay subscription import')
      ->addValue('display_name', $params['name'] ?? 'Unknown Customer')
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
   * Log manual intervention cases.
   */
  private function logManualIntervention($message, $params) {
    echo "Manual Intervention Required: $message\n";
    \Civi::log('razorpay')->warning($message, $params);
  }

  /**
   * Map subscription data to CiviCRM ContributionRecur.
   *
   * @param array $subscription
   */
  private function mapSubscriptionToCiviCRM(array $subscription): void {
    echo "Mapping Subscription {$subscription['id']} to CiviCRM ContributionRecur...\n";

    // Placeholder for actual mapping logic
    // Example:
    // ContributionRecur::create([
    //     'contact_id' => $contactId,
    //     'amount' => $subscription['quantity'] * 100,
    //     'currency' => 'INR',
    //     'start_date' => date('Y-m-d H:i:s', $subscription['start_at']),
    //     'processor_id' => $subscription['id'],
    //     'contribution_status_id:name' => 'In Progress',
    // ])->execute();
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
