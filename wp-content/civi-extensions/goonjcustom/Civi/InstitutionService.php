<?php

namespace Civi;

use Civi\Afform\Event\AfformSubmitEvent;
use Civi\Api4\Address;
use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Api4\Organization;
use Civi\Api4\Relationship;
use Civi\Api4\Utils\CoreUtil;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\CollectionSource;

/**
 *
 */
class InstitutionService extends AutoSubscriber {
  use CollectionSource;
  const FALLBACK_OFFICE_NAME = 'Delhi';
  const ENTITY_SUBTYPE_NAME = 'Institute';
  const Institution_INTENT_FB_NAME = 'afformInstitutionRegistration';
  private static $organizationId = NULL;
  private static $instituteAddress = NULL;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      'civi.afform.submit' => [
        ['setInstituteAddress', 9],
        ['setInstitutionPocAddress', 8],
      ],
      '&hook_civicrm_post' => [
        ['organizationCreated'],
        ['setOfficeDetails'],
      ],
      '&hook_civicrm_pre' => [
        ['assignChapterGroupToContacts'],
      ],
    ];
  }

  /**
   *
   */
  public static function setInstituteAddress(AfformSubmitEvent $event) {
    $afform = $event->getAfform();
    $formName = $afform['name'];

    if ($formName !== self::Institution_INTENT_FB_NAME) {
      return;
    }

    $entityType = $event->getEntityType();

    if ($entityType !== 'Organization') {
      return;
    }

    $records = $event->records;
    foreach ($records as $record) {
      $fields = $record['fields'];

      $addressJoins = $record['joins']['Address'] ?? [];

      $stateProvinceId = !empty($addressJoins[0]['state_province_id'])
          ? $addressJoins[0]['state_province_id']
          : NULL;

      self::$instituteAddress = [
        'location_type_id' => 3,
        'state_province_id' => $stateProvinceId,
        'country_id' => 1101,
      ];
    }

  }

  /**
   *
   */
  public static function setInstitutionPocAddress(AfformSubmitEvent $event) {
    $afform = $event->getAfform();
    $formName = $afform['name'];

    if ($formName !== self::Institution_INTENT_FB_NAME) {
      return;
    }

    $entityType = $event->getEntityType();

    if (!CoreUtil::isContact($entityType)) {
      return;
    }

    foreach ($event->records as $index => $contact) {
      if (empty($contact['fields'])) {
        continue;
      }

      $contactId = $contact['fields']['id'];

      $stateProvinceId = self::$instituteAddress['state_province_id'];

      $updateResults = Address::update(FALSE)
        ->addValue('state_province_id', $stateProvinceId)
        ->addWhere('contact_id', '=', $test)
        ->execute();
    }

    \Civi::log()->info('Address update results', $updateResults);

  }

  /**
   *
   */
  public static function assignChapterGroupToContacts(string $op, string $objectName, $objectId, &$objectRef) {
    if ($op !== 'edit' || $objectName !== 'AfformSubmission') {
      return FALSE;
    }

    if (($objectRef['data']['Organization1'][0]['fields']['Institute_Registration.Contact_Source'] ?? '') !== 'Institute Registration') {
      return FALSE;
    }

    $stateProvinceId = $objectRef['data']['Organization1'][0]['joins']['Address'][0]['state_province_id'] ?? NULL;
    if (!$stateProvinceId) {
      return FALSE;
    }

    $groupId = self::getChapterGroupForState($stateProvinceId);

    if (!$groupId) {
      return FALSE;
    }
    self::addContactToGroup($objectRef['data']['Individual1'][0]['id'] ?? NULL, $groupId);
    self::addContactToGroup($objectRef['data']['Organization1'][0]['id'] ?? NULL, $groupId);

    return TRUE;
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

    if ($stateOfficeId & self::$organizationId) {
      Organization::update(FALSE)
        ->addValue('Review.Goonj_Office', $stateOfficeId)
        ->addWhere('id', '=', self::$organizationId)
        ->execute();
    }

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

    if ($coordinatorId && self::$organizationId) {
      Organization::update('Organization', FALSE)
        ->addValue('Review.Coordinating_POC', $coordinatorId)
        ->addWhere('id', '=', self::$organizationId)
        ->execute();
    }

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
    if ($contactId) {
      $organization = Organization::get(FALSE)
        ->addSelect('Institute_Registration.Type_of_Institution:label')
        ->addWhere('id', '=', $contactId)
        ->execute()->single();
    }

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
