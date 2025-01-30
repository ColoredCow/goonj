<?php

/**
 * @file
 * CLI Script to assign contacts from a group to their respective state groups.
 */

use Civi\Api4\Contact;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

define('SOURCE_GROUP_ID', 72);

echo "Fetching contacts from group ID " . SOURCE_GROUP_ID . "...\n";

/**
 * Fetch contacts from the specified group.
 *
 * @return array List of contacts with their state ID.
 */
function getContactsFromGroup(): array {
  $groupContacts = GroupContact::get(FALSE)
    ->addSelect('contact_id')
    ->addJoin('Contact AS contact', 'LEFT')
    ->addWhere('group_id', '=', SOURCE_GROUP_ID)
    ->execute();

  error_log("groupContacts: " . print_r($groupContacts, TRUE));

  return $groupContacts->getIterator()->getArrayCopy();
}

/**
 * Get the corresponding chapter group ID for a given state ID.
 *
 * @param int $stateId
 *   State ID of the contact.
 *
 * @return int|null
 *   The corresponding chapter group ID or NULL if not found.
 */
function getChapterGroupForState(int $stateId): ?int {
  $group = Group::get(FALSE)
    ->addSelect('id')
    ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
    ->addWhere('Chapter_Contact_Group.Contact_Catchment', 'CONTAINS', $stateId)
    ->execute()
    ->first();
  error_log("group: " . print_r($group, TRUE));

  if (!$group) {
    echo "No specific chapter group found for state ID $stateId. Assigning fallback group.\n";

    $fallback = Group::get(FALSE)
      ->addSelect('id')
      ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
      ->addWhere('Chapter_Contact_Group.Fallback_Chapter', '=', 1)
      ->execute()
      ->first();
    error_log("fallback: " . print_r($fallback, TRUE));

    return $fallback['id'] ?? NULL;
  }

  return $group['id'];
}

/**
 * Process contacts and assign them to the appropriate state-based group.
 */
function assignContactsToStateGroups(): void {
  // No arguments needed.
  $contacts = getContactsFromGroup();
  error_log("contacts: " . print_r($contacts, TRUE));

  if (empty($contacts)) {
    echo "No contacts found in source group.\n";
    return;
  }

  // Fix loop to correctly iterate.
  foreach ($contacts as $contact) {
    $contactId = $contact['contact_id'];
    error_log("contactId: " . print_r($contactId, TRUE));

    $contactDetails = Contact::get(FALSE)
      ->addSelect('address_primary.state_province_id')
      ->addWhere('id', '=', $contactId)
      ->execute()->first();

    error_log("contactDetails: " . print_r($contactDetails, TRUE));

    $stateId = $contactDetails['address_primary.state_province_id'] ?? NULL;
    error_log("stateId: " . print_r($stateId, TRUE));

    if (!$stateId) {
      echo "Skipping contact ID $contactId: No state assigned.\n";
      continue;
    }

    $groupId = getChapterGroupForState($stateId);
    error_log("groupId: " . print_r($groupId, TRUE));

    if (!$groupId) {
      echo "Skipping contact ID $contactId: No matching chapter group found.\n";
      continue;
    }

    $existingAssignment = GroupContact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('group_id', '=', $groupId)
      ->execute()
      ->first();

    if ($existingAssignment) {
      echo "Contact ID $contactId is already assigned to group ID $groupId. Skipping.\n";
      continue;
    }

    GroupContact::create(FALSE)
      ->addValue('contact_id', $contactId)
      ->addValue('group_id', $groupId)
      ->addValue('status', 'Added')
      ->execute();

    echo "Assigned contact ID $contactId to group ID $groupId.\n";
  }
}

// Run the process.
echo "=== Starting Contact Assignment Process ===\n";
assignContactsToStateGroups();
echo "=== Contact Assignment Process Completed ===\n";
