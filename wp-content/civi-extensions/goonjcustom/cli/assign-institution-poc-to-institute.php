<?php

/**
 * @file
 * CLI Script to assign Institute poc to Institution.
 */

use Civi\Api4\Contact;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

// Change this ID to the one where you want to add the Institute.
define('SOURCE_GROUP_NAME', 'test');

echo "Fetching Institute from group ID " . SOURCE_GROUP_NAME . "...\n";

/**
 * Fetch institute from the specified group.
 */
function getInstituteFromGroup(): array {
  $groupContacts = GroupContact::get(FALSE)
    ->addSelect('contact_id')
    ->addJoin('Contact AS contact', 'LEFT')
    ->addWhere('group_id:label', '=', SOURCE_GROUP_NAME)
    ->execute();

  return $groupContacts->getIterator()->getArrayCopy();
}

/**
 *
 */
function assignInstitutePocToInstitute(): void {
  $contacts = getInstituteFromGroup();

  

  if (empty($contacts)) {
    echo "No contacts found in source group.\n";
    return;
  }

  foreach ($contacts as $contact) {
    $contactId = $contact['contact_id'];

  }
}

// Run the process.
echo "=== Starting Assign Process ===\n";
assignContactsToStateGroups();
echo "=== Assign Process Completed ===\n";
