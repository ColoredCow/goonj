<?php

/**
 * @file
 * CLI Script to Import Razorpay One-Time Payments into CiviCRM using CSV data.
 *
 * Usage:
 *   php import-one-time-transactions.php /path/to/csv/file.csv.
 *
 * CSV Format:
 *   Headers: payment_id,email,phone
 */

// To run the script use this cli command
// cv scr import-one-time-transactions.php /path-to-csv-file.csv | tee save-data.txt

// cv scr ../civi-extensions/civirazorpay/cli/import-one-time-transactions.php /var/www/html/crm.goonj.org/wp-content/civi-extensions/civirazorpay/temp_file/April_MisMatched_Data.csv | tee one-time-contribution-save-data.txt

use Civi\Payment\System;
use Civi\Api4\Contribution;
use Civi\Api4\Individual;
use Civi\Api4\Email;
use Civi\Api4\Phone;
use Civi\Api4\PaymentProcessor;

require_once __DIR__ . '/../lib/razorpay/Razorpay.php';

const RP_IMPORT_PAYMENTS_LIMIT = 100;
const RP_API_MAX_RETRIES = 3;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

if (empty($argv[1])) {
  exit("Please provide the path to the CSV file as an argument.\n");
}

/**
 * Class to handle Razorpay one-time payment import into CiviCRM.
 */
class RazorpayPaymentImporter {

  private $api;
  private $totalImported = 0;
  private $retryCount = 0;
  private $isTest;
  private $processor;
  private $processorID;
  private $csvData = [];

  public function __construct($csvFilePath) {
    civicrm_initialize();

    $this->isTest = FALSE;

    $processorConfig = PaymentProcessor::get(FALSE)
      ->addWhere('payment_processor_type_id:name', '=', 'Razorpay')
      ->addWhere('is_test', '=', $this->isTest)
      ->execute()->single();

    $this->processor = System::singleton()->getByProcessor($processorConfig);
    $this->processorID = $this->processor->getID();
    $this->api = $this->processor->initializeApi();

    // Load CSV data.
    $this->loadCsvData($csvFilePath);
  }

  /**
   * Load and parse the CSV file containing payment_id, email, and phone.
   */
  private function loadCsvData($csvFilePath): void {
    if (!file_exists($csvFilePath)) {
      exit("CSV file not found: $csvFilePath\n");
    }

    $file = fopen($csvFilePath, 'r');
    // Read header row.
    $header = fgetcsv($file);

    // Validate CSV headers.
    if ($header !== ['payment_id', 'email', 'phone']) {
      fclose($file);
      exit("Invalid CSV format. Expected headers: payment_id,email,phone\n");
    }

    while (($row = fgetcsv($file)) !== FALSE) {
      if (count($row) >= 3) {
        $this->csvData[$row[0]] = [
          'email' => trim($row[1]),
          'phone' => trim($row[2]),
        ];
      }
    }
    fclose($file);

    if (empty($this->csvData)) {
      exit("No valid data found in CSV file.\n");
    }

    echo "Loaded " . count($this->csvData) . " records from CSV file.\n";
  }

  /**
   * Start the payment import process.
   */
  public function run($limit = NULL): void {
    echo "=== Importing Razorpay One-Time Payments into CiviCRM ===\n";

    $paymentIds = array_keys($this->csvData);
    $totalPayments = count($paymentIds);
    $processed = 0;

    while ($processed < $totalPayments) {
      try {
        $batch = array_slice($paymentIds, $processed, $limit);
        if (empty($batch)) {
          echo "No more payments to import. Total imported: $this->totalImported\n";
          break;
        }

        echo "Processing batch of " . count($batch) . " payments (processed: $processed/$totalPayments)\n";

        foreach ($batch as $paymentId) {
          $this->processPayment($paymentId);
          $this->totalImported++;
          $processed++;
        }

        $this->retryCount = 0;
      }
      catch (Exception $e) {
        $this->handleRetry($e, $limit);
      }
    }

    echo "=== Import Completed. Total Payments Imported: $this->totalImported ===\n";
  }

  /**
   * Process a single Razorpay payment.
   *
   * @param string $paymentId
   */
  private function processPayment(string $paymentId): void {
    echo "Processing Payment ID: $paymentId\n";

    try {
      $payment = $this->api->payment->fetch($paymentId);
      if (!$payment || $payment['status'] !== 'captured') {
        echo "Payment ID: $paymentId is not valid or not captured. Skipping.\n";
        return;
      }

      echo "Payment Status: {$payment['status']}\n";
      echo "Amount: " . ($payment['amount'] / 100) . " {$payment['currency']}\n";

      $contactID = $this->handleCustomerData($paymentId);

      if (!$contactID) {
        throw new Exception("No valid contact could be associated with payment $paymentId");
      }

      $this->createContribution($payment, $contactID);
    }
    catch (Exception $e) {
      echo "Error processing payment $paymentId: " . $e->getMessage() . "\n";
      $this->logManualIntervention("Error processing payment $paymentId", ['error' => $e->getMessage()]);
    }
  }

  /**
   * Handle customer data associated with the payment using CSV data.
   *
   * @param string $paymentId
   *
   * @return int|null
   */
  private function handleCustomerData(string $paymentId): ?int {
    if (!isset($this->csvData[$paymentId])) {
      echo "No CSV data found for payment $paymentId\n";
      return NULL;
    }

    $csvRecord = $this->csvData[$paymentId];
    $email = $csvRecord['email'] ?? NULL;
    $phone = $csvRecord['phone'] ?? NULL;

    $findContactArgs = [
      'email' => $email,
      'phone' => $phone,
    ];

    $contactID = $this->findContact($findContactArgs);

    if ($contactID) {
      echo "Contact found/created successfully. Contact ID: $contactID\n";
      return $contactID;
    }

    echo "Could not identify a unique contact for payment $paymentId. Logged for manual intervention.\n";
    return NULL;
  }

  /**
   * Find or create a CiviCRM contact based on email and phone.
   *
   * @param array $params
   *   - email: Customer email (optional).
   *   - phone: Customer phone number (optional).
   *
   * @return int|null
   */
  private function findContact(array $params): ?int {
    // Case 1: No email, No phone.
    if (empty($params['email']) && empty($params['phone'])) {
      $this->logManualIntervention('Neither email nor phone is provided.', $params);
      return NULL;
    }

    // Case 2: No email, Phone available.
    if (empty($params['email']) && !empty($params['phone'])) {
      return $this->handlePhoneSearch($params['phone']);
    }

    // Case 3: Email available, No phone.
    if (!empty($params['email']) && empty($params['phone'])) {
      return $this->handleEmailSearch($params['email']);
    }

    // Case 4: Email available, Phone available.
    if (!empty($params['email']) && !empty($params['phone'])) {
      return $this->handleEmailAndPhoneSearch($params['email'], $params['phone']);
    }

    return NULL;
  }

  /**
   * Handle search by phone.
   */
  private function handlePhoneSearch(string $phone): ?int {
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
    return $this->createContact(['phone' => $phone]);
  }

  /**
   * Handle search by email.
   */
  private function handleEmailSearch(string $email): ?int {
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
    return $this->createContact(['email' => $email]);
  }

  /**
   * Handle search by both email and phone.
   */
  private function handleEmailAndPhoneSearch(string $email, string $phone): ?int {
    $emailResults = Email::get(FALSE)
      ->addWhere('email', '=', $email)
      ->execute();

    $phoneResults = Phone::get(FALSE)
      ->addWhere('phone', '=', $phone)
      ->execute();

    $emailContactIDs = array_unique(array_column($emailResults->jsonSerialize(), 'contact_id'));
    $phoneContactIDs = array_unique(array_column($phoneResults->jsonSerialize(), 'contact_id'));

    // Find intersection of email and phone results.
    $commonContactIDs = array_intersect($emailContactIDs, $phoneContactIDs);

    if (count($commonContactIDs) === 1) {
      return reset($commonContactIDs);
    }

    if (count($commonContactIDs) > 1) {
      $this->logManualIntervention('Multiple contacts found with matching email and phone.', [
        'email' => $email,
        'phone' => $phone,
      ]);
      return NULL;
    }

    if (count($emailContactIDs) === 1) {
      return reset($emailContactIDs);
    }

    if (count($phoneContactIDs) === 1) {
      return reset($phoneContactIDs);
    }

    return $this->createContact(['email' => $email, 'phone' => $phone]);
  }

  /**
   * Create a new Individual contact in CiviCRM with optional email and phone.
   *
   * @param array $params
   *   - email: Customer email (optional).
   *   - phone: Customer phone number (optional).
   *
   * @return int|null
   */
  private function createContact(array $params): ?int {
    // Create an Individual contact.
    $contact = Individual::create(FALSE)
      ->addValue('source', 'Monetary Contribution')
      ->addValue('first_name', 'Unknown')
      ->addValue('last_name', 'Customer')
      ->execute()
      ->first();

    $contactId = $contact['id'];
    echo "Created new contact. Contact ID: $contactId\n";

    // Add email if available.
    if (!empty($params['email'])) {
      Email::create(FALSE)
        ->addValue('contact_id', $contactId)
        ->addValue('email', $params['email'])
        ->addValue('is_primary', TRUE)
        ->execute();

      echo "Added email '{$params['email']}' to contact ID: $contactId\n";
    }

    // Add phone if available.
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
   * Creates a Contribution record in CiviCRM from a Razorpay payment object.
   *
   * @param object $payment
   * @param int $contactID
   */
  private function createContribution(object $payment, int $contactID): void {
    echo "Creating Contribution for Payment ID: {$payment['id']}\n";

    // Convert from smallest currency unit.
    $amount = $payment['amount'] / 100;
    $currency = strtoupper($payment['currency'] ?? 'INR');
    $paymentDate = date('Y-m-d H:i:s', $payment['created_at'] ?? time());
    $transactionId = $payment['id'];
    $invoiceId = md5(uniqid(rand(), TRUE));

    try {
      $existingContribution = Contribution::get(FALSE)
        ->addSelect('id')
        ->addWhere('trxn_id', '=', $transactionId)
        ->addWhere('is_test', '=', $this->isTest)
        ->execute()
        ->first();

      if ($existingContribution) {
        echo "Existing Contribution found with ID: {$existingContribution['id']} for Payment ID: $transactionId. Skipping creation.\n";
        return;
      }

      $contribution = Contribution::create(FALSE)
        ->addValue('contact_id', $contactID)
        ->addValue('financial_type_id:name', 'Donation')
        ->addValue('payment_instrument_id:name', 'Credit Card')
        ->addValue('receive_date', $paymentDate)
        ->addValue('total_amount', $amount)
        ->addValue('currency', $currency)
        ->addValue('trxn_id', $transactionId)
        ->addValue('invoice_id', $invoiceId)
        ->addValue('contribution_status_id:name', 'Completed')
        ->addValue('source', 'Import One Time Contribution')
        ->addValue('payment_processor_id', $this->processorID)
        ->execute();

      echo "Contribution successfully created for Payment ID: $transactionId\n";
    }
    catch (Exception $e) {
      echo "Failed to create Contribution for Payment ID: {$payment['id']}: " . $e->getMessage() . "\n";
      $this->logManualIntervention('Failed to create contribution from payment', ['payment_id' => $payment['id']]);
    }
  }

  /**
   * Log manual intervention cases.
   */
  private function logManualIntervention(string $message, array $params): void {
    echo "Manual Intervention Required: $message\n";
    \Civi::log('razorpay')->warning($message, $params);
  }

  /**
   * Handle retry logic on failure.
   *
   * @param Exception $e
   * @param int|null $limit
   */
  private function handleRetry(Exception $e, ?int $limit): void {
    $this->retryCount++;
    echo "Error processing payments: " . $e->getMessage() . "\n";

    if ($this->retryCount >= RP_API_MAX_RETRIES) {
      echo "Maximum retries reached. Exiting...\n";
      exit(1);
    }

    echo "Retrying... ($this->retryCount/" . RP_API_MAX_RETRIES . ")\n";
    sleep(2);
  }

}

try {
  $importer = new RazorpayPaymentImporter($argv[1]);
  $importer->run(RP_IMPORT_PAYMENTS_LIMIT);
}
catch (\Exception $e) {
  echo "Error: " . $e->getMessage() . "\n\n" . $e->getTraceAsString() . "\n";
}
