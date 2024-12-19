<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
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
  private static $organizationId = NULL;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => [
        ['organizationCreated'],
        ['setOfficeDetails'],
      ],
      '&hook_civicrm_pre' => 'assignChapterGroupToIndividual',
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
   *
   */
  private static function getChapterGroupForState($stateId) {
    $stateContactGroups = Group::get(FALSE)
      ->addSelect('id')
      ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
      ->addWhere('Chapter_Contact_Group.Contact_Catchment', 'CONTAINS', $stateId)
      ->execute();
    $stateContactGroup = $stateContactGroups->first();

    if (!$stateContactGroup) {
      \CRM_Core_Error::debug_log_message('No chapter contact group found for state ID: ' . $stateId);

      $fallbackGroups = Group::get(FALSE)
        ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
        ->addWhere('Chapter_Contact_Group.Fallback_Chapter', '=', 1)
        ->execute();
      $stateContactGroup = $fallbackGroups->first();

      \Civi::log()->info('Assigning fallback chapter contact group: ' . $stateContactGroup['title']);
    }

    return $stateContactGroup ? $stateContactGroup['id'] : NULL;
  }

  /**
   *
   */
  public static function assignChapterGroupToIndividual(string $op, string $objectName, $objectId, &$objectRef) {
    $assignments = [
      'Institution Collection Camp' => [
        'stateField' => 'Institution_Collection_Camp_Intent.State',
        'contactField' => 'Institution_Collection_Camp_Intent.Institution_POC',
        'organizationField' => 'Institution_Collection_Camp_Intent.Organization_Name',
      ],
      'Institution Dropping Center' => [
        'stateField' => 'Institution_Dropping_Center_Intent.State',
        'contactField' => 'Institution_Dropping_Center_Intent.Institution_POC',
        'organizationField' => 'Institution_Dropping_Center_Intent.Organization_Name',
      ],
    ];

    if ($objectName !== 'Eck_Collection_Camp' || empty($objectRef['title']) || !isset($assignments[$objectRef['title']])) {
      return FALSE;
    }

    $assignment = $assignments[$objectRef['title']];

    $stateId = $objectRef[$assignment['stateField']] ?? NULL;
    $contactId = $objectRef[$assignment['contactField']] ?? NULL;
    $organizationId = $objectRef[$assignment['organizationField']] ?? NULL;

    if (!$stateId || !$contactId) {
      \Civi::log()->info("Missing Contact ID or State ID for " . $objectRef['title']);
      return FALSE;
    }

    // Get the group and add contacts.
    $groupId = self::getChapterGroupForState($stateId);

    if ($groupId) {
      self::addContactToGroup($contactId, $groupId);
      if ($organizationId) {
        self::addContactToGroup($organizationId, $groupId);
      }
    }

    return TRUE;
  }

  /**
   *
   */
  private static function addContactToGroup($contactId, $groupId) {
    if ($contactId && $groupId) {
      GroupContact::create(FALSE)
        ->addValue('contact_id', $contactId)
        ->addValue('group_id', $groupId)
        ->addValue('status', 'Added')
        ->execute();
      \Civi::log()->info("Added contact_id: $contactId to group_id: $groupId");
    }
    else {
      \Civi::log()->info("Failed to add contact to group. Invalid contact_id or group_id");
    }
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
  public static function organizationCreated(string $op, string $objectName, int $objectId, &$objectRef) {

    if ($op !== 'create' || $objectName !== 'Organization') {
      return FALSE;
    }

    $subTypes = $objectRef->contact_sub_type;

    if (empty($subTypes)) {
      return FALSE;
    }

    // The ASCII control character \x01 represents the "Start of Header".
    // It is used to separate values internally by CiviCRM for multiple subtypes.
    $subtypes = explode("\x01", $subTypes);
    $subtypes = array_filter($subtypes);

    if (!in_array('Institute', $subtypes)) {
      return FALSE;
    }

    self::$organizationId = $objectId;
  }

  /**
   *
   */
  public static function setOfficeDetails(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'Address' || self::$organizationId !== $objectRef->contact_id || !$objectRef->is_primary) {
      return FALSE;
    }

    $stateId = $objectRef->state_province_id;

    $officesFound = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addWhere('Goonj_Office_Details.Institution_Catchment', 'CONTAINS', $stateId)
      ->execute();

    $stateOffice = $officesFound->first();

    // If no state office is found, assign the fallback state office.
    if (!$stateOffice) {
      $stateOffice = self::getFallbackOffice();
    }

    $stateOfficeId = $stateOffice['id'];

    Organization::update(FALSE)
      ->addValue('Review.Goonj_Office', $stateOfficeId)
      ->addWhere('id', '=', self::$organizationId)
      ->execute();

    if (!$stateId) {
      return FALSE;
    }

    // Get the relationship type name based on the institution type.
    $relationshipTypeName = self::getRelationshipTypeName(self::$organizationId);
    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $stateOfficeId)
      ->addWhere('relationship_type_id:name', '=', $relationshipTypeName)
      ->addWhere('is_current', '=', TRUE)
      ->execute();

    $coordinatorCount = $coordinators->count();

    if ($coordinatorCount === 0) {
      $coordinator = self::getFallbackCoordinator(self::$organizationId);
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

    Organization::update('Organization', FALSE)
      ->addValue('Review.Coordinating_POC', $coordinatorId)
      ->addWhere('id', '=', self::$organizationId)
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
      'Foundation'   => 'Foundation Coordinator of',
      'Education'    => 'Education Institute Coordinator of',
      'Associations' => 'Associations Coordinator of',
      'Others'       => 'Default Coordinator of',
    ];

    $firstWord = strtok($typeOfInstitution, ' ');
    // Return the corresponding relationship type, or default if not found.
    return $typeToRelationshipMap[$firstWord] ?? 'Default Coordinator of';
  }

}
