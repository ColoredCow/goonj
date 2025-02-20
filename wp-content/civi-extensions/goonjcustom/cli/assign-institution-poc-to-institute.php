<?php

/**
 * @file
 * CLI Script to assign Institute poc to custom group.
 */

use Civi\Api4\Organization;
use Civi\Api4\Relationship;
use Civi\Api4\GroupContact;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

// Add the names of the groups you want to process here.
define('SOURCE_GROUP_NAMES', ['group names']);

echo "Fetching Institute from group ID " . SOURCE_GROUP_NAMES . "...\n";

/**
 * Fetch institute from the specified group.
 */
function getInstituteFromGroup(): array {
  $groupContacts = GroupContact::get(FALSE)
    ->addSelect('contact_id')
    ->addJoin('Contact AS contact', 'LEFT')
    ->addWhere('group_id:label', 'IN', SOURCE_GROUP_NAMES)
    ->execute();

  return $groupContacts->getIterator()->getArrayCopy();
}

/**
 * Assigns institution POC to the corresponding institution.
 *
 * This function retrieves contacts from a specified group and assigns
 * them as POCs to their respective institutions.
 *
 * @throws \Civi\API\Exception\UnauthorizedException
 * @throws \API_Exception
 */
function assignInstitutePocToInstitute(): void {
  $contacts = getInstituteFromGroup();

  if (empty($contacts)) {
    echo "No contacts found in source group.\n";
    return;
  }

  foreach ($contacts as $contact) {
    $contactId = $contact['contact_id'];

    $relationship = Relationship::get(FALSE)
      ->addSelect('contact_id_b')
      ->addWhere('contact_id_a', '=', $contactId)
      ->execute()->first();

    if (empty($relationship)) {
      echo "No institution poc found in relationship.\n";
      continue;
    }

    $institutionPocId = $relationship['contact_id_b'];

    Organization::update(FALSE)
      ->addValue('Institute_Registration.Institution_POC', $institutionPocId)
      ->addWhere('contact_sub_type', '=', 'Institute')
      ->addWhere('id', '=', $contactId)
      ->execute();

    echo "Assigned Institute Poc ID $institutionPocId to Custom group.\n";

  }
}

// Run the process.
echo "=== Starting Assign Process ===\n";
assignInstitutePocToInstitute();
echo "=== Assign Process Completed ===\n";
