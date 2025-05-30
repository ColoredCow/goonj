<?php

/**
 * @file
 * CLI Script to assign Coordinating POCs to institutions.
 */

use Civi\Api4\Contact;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Api4\Relationship;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

// Add the names of the groups you want to process here.
define('SOURCE_GROUP_NAMES', ['group names']);

echo "Fetching contacts from groups: " . implode(', ', SOURCE_GROUP_NAMES) . "...\n";

/**
 * Fetch institutions from the specified group.
 */
function getContactsFromGroups(): array {
  $groupContacts = GroupContact::get(FALSE)
    ->addSelect('contact_id')
    ->addJoin('Contact AS contact', 'LEFT')
    ->addWhere('group_id:label', 'IN', SOURCE_GROUP_NAMES)
    ->execute();

  return $groupContacts->getIterator()->getArrayCopy();
}

/**
 *
 */
function getStateOfficeId(int $stateId): ?int {
  $offices = Contact::get(FALSE)
    ->addSelect('id')
    ->addWhere('contact_type', '=', 'Organization')
    ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
    ->addWhere('Goonj_Office_Details.Institution_Catchment', 'CONTAINS', $stateId)
    ->execute()->first();

  if ($offices) {
    return $offices['id'];
  }

  echo "No office found for state ID $stateId. Using fallback coordinator.\n";

  // Fetch the fallback coordinator group.
  $fallbackGroups = Group::get(FALSE)
    ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
    ->addWhere('Chapter_Contact_Group.Fallback_Chapter', '=', 1)
    ->execute();

  $fallbackCoordinator = $fallbackGroups->first();

  return $fallbackCoordinator ? $fallbackCoordinator['id'] : NULL;
}

/**
 * Get relationship type name based on institution type and category.
 */
function getRelationshipType(array $contactData): string {
  $type = $contactData['Institute_Registration.Type_of_Institution:name'] ?? '';
  $category = $contactData['Category_Of_Institution.Education_Institute:name'] ?? '';

  $typeToRelationshipMap = [
    'Corporate'    => 'Corporate Coordinator of',
    'Foundation'   => 'Default Coordinator of',
    'Association' => 'Default Coordinator of',
    'Other'       => 'Default Coordinator of',
    'Educational_Institute' => [
      'School' => 'School Coordinator of',
      'Collage_University' => 'College/University Coordinator of',
      'Other' => 'Default Coordinator of',
    ],
  ];

  if (is_array($typeToRelationshipMap[$type]) && $category) {
    return $typeToRelationshipMap[$type][$category] ?? 'Default Coordinator of';
  }

  return $typeToRelationshipMap[$type] ?? 'Default Coordinator of';
}

/**
 * Find coordinator for the state office.
 */
function findCoordinator(int $goonjOfficeId, string $relationshipType): ?int {
  try {
    $relationships = Relationship::get(FALSE)
      ->addSelect('contact_id_a')
      ->addWhere('contact_id_b', '=', $goonjOfficeId)
      ->addWhere('relationship_type_id:name', '=', $relationshipType)
      ->addWhere('is_active', '=', TRUE)
      ->addOrderBy('start_date', 'DESC')
      ->execute();

    if ($relationships->count() > 0) {
      return $relationships->first()['contact_id_a'];
    }

    // Fallback to default coordinator if specific not found.
    if ($relationshipType !== 'Default Coordinator of') {
      return findCoordinator($goonjOfficeId, 'Default Coordinator of');
    }

    return NULL;
  }
  catch (\Exception $e) {
    echo "Error finding coordinator: " . $e->getMessage() . "\n";
    return NULL;
  }
}

/**
 * Process institutions and assign POCs.
 */
function assignCoordinators(): void {
  $institutions = getContactsFromGroups();

  if (empty($institutions)) {
    echo "No institutions found in source group.\n";
    return;
  }

  foreach ($institutions as $institution) {
    $contactId = $institution['contact_id'];
    echo "\nProcessing institution ID: $contactId\n";

    try {
      // Get institution details.
      $contactData = Contact::get(FALSE)
        ->addSelect(
                'address_primary.state_province_id',
                'Institute_Registration.Type_of_Institution:name',
                'Category_Of_Institution.Education_Institute:name'
            )
        ->addWhere('id', '=', $contactId)
        ->execute()
        ->first();

      // Get state ID from address.
      $stateId = $contactData['address_primary.state_province_id'] ?? NULL;
      if (!$stateId) {
        echo "Skipping contact ID $contactId: No state assigned\n";
        continue;
      }

      // Get state office ID from state ID.
      $goonjOfficeId = getStateOfficeId($stateId);
      echo "goonjOfficeId $goonjOfficeId";

      if (!$goonjOfficeId) {
        echo "Skipping $contactId: No office found for state ID $stateId\n";
        continue;
      }

      // update goonj office
      Contact::update(FALSE)
        ->addValue('Review.Goonj_Office', $goonjOfficeId)
        ->addWhere('id', '=', $contactId)
        ->execute();

      echo "Assigned $goonjOfficeId to contact id $contactId\n";

      // Determine relationship type.
      $relationshipType = getRelationshipType($contactData);
      echo "Relationship type: $relationshipType\n";

      // Find coordinator.
      $coordinatorId = findCoordinator($goonjOfficeId, $relationshipType);
      if (!$coordinatorId) {
        echo "No coordinator found for $contactId (Type: $relationshipType)\n";
        continue;
      }

      // Update coordinating POC.
      Contact::update(FALSE)
        ->addValue('Review.Coordinating_POC', $coordinatorId)
        ->addWhere('id', '=', $contactId)
        ->execute();

      echo "Assigned coordinator ID $coordinatorId to contact ID $contactId\n";

    }
    catch (\Exception $e) {
      echo "Error processing $contactId: " . $e->getMessage() . "\n";
    }
  }
}

// Run the process.
echo "\n=== Starting Coordinator Assignment Process ===\n";
assignCoordinators();
echo "\n=== Coordinator Assignment Process Completed ===\n";
