<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\Organization;
use Civi\Api4\Relationship;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class InstitutionService extends AutoSubscriber {

  const FALLBACK_OFFICE_NAME = 'Delhi';
  const RELATIONSHIP_TYPE_NAME = [
  // For Corporate.
    'Corporate Coordinator of',
  // For family foundation.
    'Family Foundation Coordinator of',
  // For Education Institute.
    'Education Coordinator of',
  // For Government Entity.
    'Government Coordinator of',
  // For Hospital.
    'Hospital Coordinator of',
  // For NGO.
    'NGO Coordinator of',
  // For Others.
    'Default Coordinator of',
  ];

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => [
        ['setOfficeDetails'],
      ],
    ];
  }

  /**
   * This hook is called after the database write on a custom table.
   *
   * @param string $op
   *   The type of operation being performed.
   * @param string $objectName
   *   The custom group ID.
   * @param int $contactId
   *   The contactId of the row in the custom table.
   * @param object $objectRef
   *   The parameters that were sent into the calling function.
   */
  public static function setOfficeDetails(string $op, string $objectName, int $contactId, &$objectRef) {

    if ($op !== 'create' || $objectName !== 'Address') {
      return FALSE;
    }

    $stateId = $objectRef->state_province_id;

    $contactId = $objectRef->contact_id;

    if (!$stateId) {
      return;
    }

    if (!$stateId) {
      \CRM_Core_Error::debug_log_message('Cannot assign Goonj Office to institution id: ' . $contactId);
      return FALSE;
    }

    $officesFound = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addWhere('Goonj_Office_Details.Institution_Catchment', 'CONTAINS', $stateId)
      ->execute();

    $stateOffice = $officesFound->first();

    error_log("stateOffice: " . print_r($stateOffice, TRUE));

    // If no state office is found, assign the fallback state office.
    if (!$stateOffice) {
      $stateOffice = self::getFallbackOffice();
    }

    $stateOfficeId = $stateOffice['id'];

    Organization::update(TRUE)
      ->addValue('Review.Goonj_Office', $stateOfficeId)
      ->addWhere('id', '=', $contactId)
      ->execute();
    // Get the relationship type name based on the institution type.
    $relationshipTypeName = self::getRelationshipTypeName($contactId);

    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $stateOfficeId)
      ->addWhere('relationship_type_id:name', '=', $relationshipTypeName)
      ->addWhere('is_current', '=', TRUE)
      ->execute();

    $coordinatorCount = $coordinators->count();

    if ($coordinatorCount === 0) {
      $coordinator = self::getFallbackCoordinator($contactId);
    }
    elseif ($coordinatorCount > 1) {
      $randomIndex = rand(0, $coordinatorCount - 1);
      $coordinator = $coordinators->itemAt($randomIndex);
    }
    else {
      $coordinator = $coordinators->first();
    }

    if (!$coordinator) {
      \CRM_Core_Error::debug_log_message('No coordinator available to assign.');
      return FALSE;
    }

    $coordinatorId = $coordinator['contact_id_a'];

    Organization::update(TRUE)
      ->addValue('Review.Coordinating_POC', $coordinatorId)
      ->addWhere('id', '=', $contactId)
      ->execute();

    return TRUE;

  }

  /**
   * This hook is called after the database write on a custom table.
   *
   * @param string $op
   *   The type of operation being performed.
   * @param string $objectName
   *   The custom group ID.
   * @param int $contactId
   *   The contactId of the row in the custom table.
   * @param object $objectRef
   *   The parameters that were sent into the calling function.
   */

  /**
   *
   */

  /**
   *
   */
  private static function getFallbackOffice() {
    $fallbackOffices = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('organization_name', 'CONTAINS', self::FALLBACK_OFFICE_NAME)
      ->execute();

    return $fallbackOffices->first();
  }

  /**
   *
   */
  private static function getFallbackCoordinator($contactId) {
    $fallbackOffice = self::getFallbackOffice();

    // Get the relationship type name based on the institution type.
    $relationshipTypeName = self::getRelationshipTypeName($contactId);

    $fallbackCoordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $fallbackOffice['id'])
      ->addWhere('relationship_type_id:name', '=', $relationshipTypeName)
      ->addWhere('is_current', '=', TRUE)
      ->execute();

    $coordinatorCount = $fallbackCoordinators->count();

    $randomIndex = rand(0, $coordinatorCount - 1);
    $coordinator = $fallbackCoordinators->itemAt($randomIndex);

    return $coordinator;
  }

  /**
   *
   */
  private static function getRelationshipTypeName($contactId) {
    $organizations = Organization::get(TRUE)
      ->addSelect('Institute_Registration.Type_of_Institution:label')
      ->addWhere('id', '=', $contactId)
      ->execute()->single();

    $typeOfInstitution = $organizations['Institute_Registration.Type_of_Institution:label'];

    $typeToRelationshipMap = [
      'Corporate'    => 'Corporate Coordinator of',
      'Family'       => 'Family Foundation Coordinator of',
      'Education'    => 'Education Coordinator of',
      'Government'   => 'Government Coordinator of',
      'Hospital'     => 'Hospital Coordinator of',
      'NGO'          => 'NGO Coordinator of',
      'Others'       => 'Default Coordinator of',
    ];

    $firstWord = strtok($typeOfInstitution, ' ');

    // Return the corresponding relationship type, or default if not found.
    return $typeToRelationshipMap[$firstWord] ?? 'Default Coordinator of';
  }

  /**
   *
   */

}
