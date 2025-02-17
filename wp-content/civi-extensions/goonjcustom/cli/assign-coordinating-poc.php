<?php

/**
 * @file
 * CLI Script to assign Coordinating POCs to institutions.
 */

use Civi\Api4\Contact;
use Civi\Api4\GroupContact;
use Civi\Api4\Relationship;

if (php_sapi_name() != 'cli') {
  exit("This script can only be run from the command line.\n");
}

// Change this to your source group name
define('SOURCE_GROUP_NAME', 'Institution');

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
 * Get relationship type name based on institution type and category.
 */
function getRelationshipType(array $contactData): string {
    $type = $contactData['Institute_Registration.Type_of_Institution:label'] ?? '';
    $category = $contactData['Category_of_Institution.Education_Institute:label'] ?? '';

    if ($type === 'Educational Institute') {
        if ($category === 'School') {
            return 'School Coordinator of';
        }
        if ($category === 'College/University') {
            return 'College Coordinator of';
        }
        return 'Default Coordinator of';
    }

    $typeToRelationshipMap = [
        'Corporate'    => 'Corporate Coordinator of',
        'Foundation'   => 'Default Coordinator of',
        'Association' => 'Default Coordinator of',
        'Other'       => 'Default Coordinator of',
    ];

    return $typeToRelationshipMap[$type] ?? 'Default Coordinator of';
}

/**
 * Find coordinator for the state office.
 */
function findCoordinator(int $stateOfficeId, string $relationshipType): ?int {
    try {
        $relationships = Relationship::get(FALSE)
            ->addSelect('contact_id_a')
            ->addWhere('contact_id_b', '=', $stateOfficeId)
            ->addWhere('relationship_type_id:name', '=', $relationshipType)
            ->addWhere('is_active', '=', TRUE)
            ->addOrderBy('start_date', 'DESC')
            ->execute();

        if ($relationships->count() > 0) {
            return $relationships->first()['contact_id_a'];
        }

        // Fallback to default coordinator if specific not found
        if ($relationshipType !== 'Default Coordinator of') {
            return findCoordinator($stateOfficeId, 'Default Coordinator of');
        }

        return null;
    } catch (\Exception $e) {
        echo "Error finding coordinator: " . $e->getMessage() . "\n";
        return null;
    }
}

/**
 * Process institutions and assign POCs.
 */
function assignCoordinators(): void {
    $contacts = getContactsFromGroup();

    if (empty($contacts)) {
        echo "No contacts found in source group.\n";
        return;
    }

    foreach ($contacts as $contact) {
        $contactId = $contact['contact_id'];
        echo "\nProcessing contact ID: $contactId\n";

        try {
            // Get institution details
            $contactData = Contact::get(FALSE)
                ->addSelect(
                    'address_primary.state_province_id',
                    'Institute_Registration.Type_of_Institution:label',
                    'Category_of_Institution.Education_Institute:label'
                )
                ->addWhere('id', '=', $contactId)
                ->execute()
                ->first();

            // Get state ID from address
            $stateId = $contactData['address_primary.state_province_id'] ?? null;
            if (!$stateId) {
                echo "Skipping contact ID $contactId: No state assigned\n";
                continue;
            }

            // Get state office ID from state ID
            $stateOfficeId = getStateOfficeId($stateId);
            if (!$stateOfficeId) {
                echo "Skipping $contactId: No office found for state ID $stateId\n";
                continue;
            }

            // Determine relationship type
            $relationshipType = getRelationshipType($contactData);
            echo "Relationship type: $relationshipType\n";

            // Find coordinator
            $coordinatorId = findCoordinator($stateOfficeId, $relationshipType);
            if (!$coordinatorId) {
                echo "No coordinator found for $contactId (Type: $relationshipType)\n";
                continue;
            }

            // Update coordinating POC
            Contact::update(FALSE)
                ->addValue('Review.Coordinating_POC', $coordinatorId)
                ->addWhere('id', '=', $contactId)
                ->execute();

            echo "Assigned coordinator ID $coordinatorId to contact ID $contactId\n";

        } catch (\Exception $e) {
            echo "Error processing $contactId: " . $e->getMessage() . "\n";
        }
    }
}

// Run the process
echo "\n=== Starting Coordinator Assignment Process ===\n";
assignCoordinators();
echo "\n=== Coordinator Assignment Process Completed ===\n";