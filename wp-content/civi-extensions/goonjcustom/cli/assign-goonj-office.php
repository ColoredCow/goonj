<?php

/**
 * @file
 * CLI Script to assign contacts to their respective state-based offices.
 */

use Civi\Api4\Contact;
use Civi\Api4\GroupContact;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

// Change this to the group name containing contacts to process
define('SOURCE_GROUP_NAME', 'Hello');

echo "Fetching contacts from group '" . SOURCE_GROUP_NAME . "'...\n";

/**
 * Fetch contacts from the specified group.
 */
function getContactsFromGroup(): array {
  $groupContacts = GroupContact::get(FALSE)
    ->addSelect('contact_id')
    ->addJoin('Contact AS contact', 'LEFT')
    ->addWhere('group_id:label', '=', SOURCE_GROUP_NAME)
    ->execute();

  return $groupContacts->getIterator()->getArrayCopy();
}

/**
 * Get the office ID for a given state ID.
 */
function getStateOfficeId(int $stateId): ?int {
  $offices = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('contact_type', '=', 'Organization')
    ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
    ->addWhere('Goonj_Office_Details.Institution_Catchment', 'CONTAINS', $stateId)
    ->execute();

  if (!$offices) {
    echo "No office found for state ID $stateId\n";
    return null;
  }

  return $offices->first()['id'];
}

/**
 * Process contacts and assign them to the appropriate state-based office.
 */
function assignContactsToOffices(): void {
  $contacts = getContactsFromGroup();

  if (empty($contacts)) {
    echo "No contacts found in source group.\n";
    return;
  }

  foreach ($contacts as $contact) {
    $contactId = $contact['contact_id'];

    // Get contact details with state and current office assignment
    $contactDetails = Contact::get(FALSE)
      ->addSelect('address_primary.state_province_id', 'Review.Goonj_Office')
      ->addWhere('id', '=', $contactId)
      ->execute()
      ->first();

    $stateId = $contactDetails['address_primary.state_province_id'] ?? null;
    $currentStateOffice = $contactDetails['Review.Goonj_Office'] ?? null;

    if (!$stateId) {
      echo "Skipping contact ID $contactId: No state assigned\n";
      continue;
    }

    $stateOfficeId = getStateOfficeId($stateId);

    if (!$stateOfficeId) {
      echo "Skipping contact ID $contactId: No office found for state $stateId\n";
      continue;
    }

    if ($currentStateOffice == $stateOfficeId) {
      echo "Contact ID $contactId already assigned to state office $stateOfficeId\n";
      continue;
    }

    // Update contact's state office field
    Contact::update(FALSE)
      ->addValue('Review.Goonj_Office', $stateOfficeId)
      ->addWhere('id', '=', $contactId)
      ->execute();

    echo "Assigned contact ID $contactId to state office ID $stateOfficeId\n";
  }
}

// Run the process
echo "=== Starting State Office Assignment Process ===\n";
assignContactsToOffices();
echo "=== State Office Assignment Process Completed ===\n";