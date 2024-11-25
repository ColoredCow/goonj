<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\Organization;
use Civi\Api4\Relationship;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\CollectionSource;

/**
 *
 */
class InstitutionService extends AutoSubscriber {
  use CollectionSource;
  const FALLBACK_OFFICE_NAME = 'Delhi';
  const ENTITY_SUBTYPE_NAME = 'Institute';

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_custom' => [
        ['setOfficeDetails'],
      ],
    ];
  }

  /**
   *
   */
  private static function findStateField(array $array) {
    $institutionCollectionCampStateField = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'state')
      ->addWhere('custom_group_id:name', '=', 'Institute_Registration')
      ->execute()
      ->first();

    if (!$institutionCollectionCampStateField) {
      return FALSE;
    }

    $stateFieldId = $institutionCollectionCampStateField['id'];

    foreach ($array as $item) {
      if ($item['entity_table'] === 'civicrm_contact' &&
            $item['custom_field_id'] === $stateFieldId) {
        return $item;
      }
    }

    return FALSE;
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
  public static function setOfficeDetails($op, $groupID, $entityID, &$params) {

    \Civi::log()->debug('1');

    if ($op !== 'create' || self::getOrgSubtypeName($entityID) !== self::ENTITY_SUBTYPE_NAME) {
      return;
    }

    \Civi::log()->debug('2');

    if (!($stateField = self::findStateField($params))) {
      return;
    }

    \Civi::log()->debug('3');

    $stateId = $stateField['value'];
    $contactId = $stateField['entity_id'];

    \Civi::log()->debug('4');

    if (!$contactId) {
      \CRM_Core_Error::debug_log_message('contactId not found: ' . $contactId);
      return;
    }

    \Civi::log()->debug('5');

    Organization::update('Organization', FALSE)
      ->addValue('Review.Status', 1)
      ->addValue('Review.Initiated_by', 1)
      ->addWhere('id', '=', $contactId)
      ->execute();

    \Civi::log()->debug('6');

    if (!$stateId) {
      \CRM_Core_Error::debug_log_message('Cannot assign Goonj Office to institution id: ' . $contactId);
      return;
    }

    \Civi::log()->debug('7');

    $officesFound = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addWhere('Goonj_Office_Details.Institution_Catchment', 'CONTAINS', $stateId)
      ->execute();

    \Civi::log()->debug('8');

    $stateOffice = $officesFound->first();

    \Civi::log()->debug('9');

    // If no state office is found, assign the fallback state office.
    if (!$stateOffice) {
      $stateOffice = self::getFallbackOffice();
    }

    \Civi::log()->debug('10');

    $stateOfficeId = $stateOffice['id'];

    $updateGoonjOffice = Organization::update(FALSE)
      ->addValue('Review.Goonj_Office', $stateOfficeId)
      ->addWhere('id', '=', $contactId)
      ->execute();

    \Civi::log()->debug('11');

    // Get the relationship type name based on the institution type.
    $relationshipTypeName = self::getRelationshipTypeName($contactId);
    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $stateOfficeId)
      ->addWhere('relationship_type_id:name', '=', $relationshipTypeName)
      ->addWhere('is_current', '=', TRUE)
      ->execute();

    \Civi::log()->debug('12');

    $coordinatorCount = $coordinators->count();

    if ($coordinatorCount === 0) {
      $coordinator = self::getFallbackCoordinator($contactId);
      \Civi::log()->debug('13');
    }
    elseif ($coordinatorCount > 1) {
      $randomIndex = rand(0, $coordinatorCount - 1);
      $coordinator = $coordinators->itemAt($randomIndex);
      \Civi::log()->debug('14');
    }
    else {
      $coordinator = $coordinators->first();
      \Civi::log()->debug('15');
    }

    if (!$coordinator) {
      \CRM_Core_Error::debug_log_message('No coordinator available to assign.');
      return FALSE;
    }

    \Civi::log()->debug('16');

    $coordinatorId = $coordinator['contact_id_a'];

    Organization::update('Organization', FALSE)
      ->addValue('Review.Coordinating_POC', $coordinatorId)
      ->addWhere('id', '=', $contactId)
      ->execute();

    \Civi::log()->debug('17');

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
    $organization = Organization::get(FALSE)
      ->addSelect('Institute_Registration.Type_of_Institution:label')
      ->addWhere('id', '=', $contactId)
      ->execute()->single();

    if (!$organization) {
      return;
    }

    $typeOfInstitution = $organization['Institute_Registration.Type_of_Institution:label'];

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

}
