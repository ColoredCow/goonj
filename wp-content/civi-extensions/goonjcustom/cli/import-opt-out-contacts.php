<?php

/**
 * CLI Script to Opt Out Contacts and Add to Group via CSV in CiviCRM.
 *
 * Usage:
 *   cv php:script opt-out-contacts.php
 */

use Civi\Api4\Email;
use Civi\Api4\Contact;
use Civi\Api4\GroupContact;

if (php_sapi_name() != 'cli') {
    exit("This script can only be run from the command line.\n");
}

// Configuration
define('CSV_FILE_PATH', '/Users/tarunjoshi/Downloads/Opted out List - Pardot (Contact listing) - civicrm_contribution (4).csv'); // Replace with your CSV file path
define('GROUP_ID', 1); // Replace with the ID of the group to add contacts to

/**
 * Reads email addresses from the provided CSV file.
 *
 * @param string $filePath Path to the CSV file.
 * @return array List of email addresses.
 * @throws Exception If the file is not readable or the 'email' column is missing.
 */
// In the readContactsFromCsv function, you can check for 'Do Not Email' column value
function readContactsFromCsv(string $filePath): array {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        throw new Exception("CSV file not found or not readable: $filePath");
    }

    $contacts = [];
    if (($handle = fopen($filePath, 'r')) !== false) {
        $header = fgetcsv($handle, 1000, ',');
        if (in_array('email', $header)) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                error_log("Raw CSV Data: " . print_r($data, TRUE)); // Log each row from CSV

                $row = array_combine($header, $data);

                // Check if the 'Do Not Email' column indicates opt-out
                if (isset($row['Do Not Email (Opt out contact)']) && 
                    strtolower($row['Do Not Email (Opt out contact)']) == 'yes') {
                    // Add contact to list if they should be opted out
                    $contacts[] = $row['email'];
                }
            }
        } else {
            throw new Exception("Error: 'email' column not found in CSV.");
        }
        fclose($handle);
    }

    return $contacts;
}


/**
 * Finds a contact by email, opts them out, and adds them to the specified group.
 *
 * @param string $email The email address of the contact.
 * @return void
 */
function optOutContactByEmail(string $email): void {
    try {
        // Log the email being processed for debugging
        echo "Processing email: $email\n";

        // Find contact by email with case-insensitive query
        $result = Email::get()
            ->addSelect('contact_id')
            ->addWhere('LOWER(email)', '=', strtolower($email)) // Case-insensitive search
            ->execute();
            error_log("result: " . print_r($result, TRUE));

        if (count($result) > 0) {
            $contactId = $result[0]['contact_id'];

            // Opt out the contact
            Contact::update()
                ->addWhere('id', '=', $contactId)
                ->setValue('is_opt_out', 1)
                ->execute();

            // Add the contact to the group
            GroupContact::create()
                ->addValue('contact_id', $contactId)
                ->addValue('group_id', GROUP_ID)
                ->addValue('status', 'Added')
                ->execute();

            echo "Successfully opted out contact with email $email (ID $contactId) and added to group.\n";
        } else {
            echo "Contact with email $email not found.\n";
        }
    } catch (Exception $e) {
        echo "Error processing email $email: " . $e->getMessage() . "\n";
    }
}


/**
 * Main function to process the CSV and update contacts.
 */
function main(): void {
    try {
        echo "=== Starting Opt-Out Process ===\n";
        $emails = readContactsFromCsv(CSV_FILE_PATH);
        error_log("emails: " . print_r($emails, TRUE)); // Log all emails read from CSV

        if (empty($emails)) {
            echo "No emails to process.\n";
            return;
        }

        foreach ($emails as $email) {
            optOutContactByEmail($email);
        }
        echo "=== Opt-Out Process Completed ===\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}


// Run the main function
main();
