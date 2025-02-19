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

// Change this name to the one where you want to add the Institute.
define('SOURCE_GROUP_NAME', 'instituion-group');

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

    $organization = Organization::get(FALSE)
      ->addSelect(
        'Institute_Registration.City',
        'Institute_Registration.Address',
        'Institute_Registration.Postal_Code',
        'Institute_Registration.Contact_number_of_Institution',
        'Institute_Registration.Email_of_Institute'
      )
      ->addWhere('id', '=', $contactId)
      ->execute()
      ->first();

    if (empty($organization)) {
      echo "No organization found for contact ID $contactId.\n";
      continue;
    }

    // Check if we have any data to update
    $hasData = !empty($organization['Institute_Registration.City']) 
      || !empty($organization['Institute_Registration.Address'])
      || !empty($organization['Institute_Registration.Postal_Code'])
      || !empty($organization['Institute_Registration.Contact_number_of_Institution'])
      || !empty($organization['Institute_Registration.Email_of_Institute']);

    if (!$hasData) {
      echo "No Institute Registration data found for contact ID $contactId.\n";
      continue;
    }

    try {
      // Update organization with mapped fields
      Organization::update(FALSE)
        ->addValue('address_primary.city', $organization['Institute_Registration.City'] ?? null)
        ->addValue('address_primary.street_address', $organization['Institute_Registration.Address'] ?? null)
        ->addValue('address_primary.postal_code', $organization['Institute_Registration.Postal_Code'] ?? null)
        ->addValue('phone_primary.phone', $organization['Institute_Registration.Contact_number_of_Institution'] ?? null)
        ->addValue('email_primary.email', $organization['Institute_Registration.Email_of_Institute'] ?? null)
        ->addValue('source', 'Data Migration')
        ->addWhere('id', '=', $contactId)
        ->addWhere('contact_sub_type', '=', 'Institute')
        ->execute();

      echo "Successfully updated institute details for contact ID $contactId.\n";
    } catch (Exception $e) {
      echo "Error updating contact ID $contactId: " . $e->getMessage() . "\n";
    }
  }
}

// Run the process.
echo "=== Starting Assign Process ===\n";
assignInstitutePocToInstitute();
echo "=== Assign Process Completed ===\n";
