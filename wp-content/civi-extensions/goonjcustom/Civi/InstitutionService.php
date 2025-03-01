<?php

namespace Civi;

use Civi\Afform\Event\AfformSubmitEvent;
use Civi\Api4\ActivityContact;
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
        ['addRelationshipToContact'],
      ],
    ];
  }

  /**
   *
   */
  public static function addRelationshipToContact(string $op, string $objectName, $objectId, &$objectRef) {
    if ($op !== 'edit' || $objectName !== 'AfformSubmission') {
      return;
    }

    $activityData = $objectRef['data']['Activity1'][0]['fields'] ?? [];
    $institutionId = $activityData['source_contact_id'] ?? NULL;
    $institutionPocId = $activityData['Institution_Material_Contribution.Institution_POC'] ?? NULL;

    if (empty($institutionId) || empty($institutionPocId)) {
      return;
    }

    $existingRelationship = Relationship::get(FALSE)
      ->addWhere('contact_id_a', '=', $institutionId)
      ->addWhere('contact_id_b', '=', $institutionPocId)
      ->addWhere('is_active', '=', TRUE)
      ->addClause('OR', ['relationship_type_id:name', '=', 'Institution POC of'], ['relationship_type_id:name', '=', 'Primary Institution POC of'])
      ->execute()->first();
    
    if ($existingRelationship) {
      return;
    }
    
    Relationship::create(FALSE)
      ->addValue('contact_id_a', $institutionId)
      ->addValue('contact_id_b', $institutionPocId)
      ->addValue('relationship_type_id', 24)
      ->addValue('relationship_type_id:label', 'Institution POC is')
      ->addValue('is_permission_a_b', 1)
      ->addValue('is_permission_b_a', 1)
      ->execute();
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
      $city = $addressJoins[0]['city'] ?? [];

      $stateProvinceId = !empty($addressJoins[0]['state_province_id'])
          ? $addressJoins[0]['state_province_id']
          : NULL;

      self::$instituteAddress = [
        'location_type_id' => 3,
        'state_province_id' => $stateProvinceId,
        'city' => $city,
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
        \Civi::log()->warning('Skipping contact due to empty fields', ['contact' => $contact]);
        continue;
      }

      $contactId = $contact['fields']['Institute_Registration.Institution_POC'] ?? NULL;
      $stateProvinceId = self::$instituteAddress['state_province_id'] ?? NULL;
      $city = self::$instituteAddress['city'] ?? NULL;

      if (!$stateProvinceId && !$contactId) {
        return FALSE;
      }

      if ($contactId && $stateProvinceId) {

        $addresses = Address::get(FALSE)
          ->addSelect('state_province_id', 'city')
          ->addWhere('contact_id', '=', $contactId)
          ->execute()->single();

        if ($addresses && $addresses['state_province_id']) {
          return;
        }

        $updateResults = Address::update(FALSE)
          ->addValue('city', $city)
          ->addValue('state_province_id', $stateProvinceId)
          ->addWhere('contact_id', '=', $contactId)
          ->execute();

        \Civi::log()->info('Institution POC address updated', [
          'contact_id' => $contactId,
          'state_province_id' => $stateProvinceId,
          'update_results' => $updateResults,
        ]);
      }
      else {
        \Civi::log()->warning('Skipped Institution POC address update', [
          'contact_id' => $contactId,
          'state_province_id' => $stateProvinceId,
          'reason' => 'Missing contact ID or state province ID',
        ]);
      }
    }
  }

  /**
   *
   */


public static function assignChapterGroupToContacts(string $op, string $objectName, $objectId, &$objectRef) {

  if ($op !== 'edit' || $objectName !== 'AfformSubmission') {
    return FALSE;
  }

  try {
      $stateProvinceId = $objectRef['data']['Organization1'][0]['joins']['Address'][0]['state_province_id'] ?? null;
      
      if (!$stateProvinceId) {
          return TRUE;
      }

      $groupId = self::getChapterGroupForState($stateProvinceId);
      if (!$groupId) {
          return TRUE;
      }

      $individualContactId = $objectRef['data']['Individual1'][0]['id'] ?? null;
      $organizationContactId = $objectRef['data']['Organization1'][0]['id'] ?? null;

      if ($individualContactId) {
          self::addContactToGroup($individualContactId, $groupId);
      }
      if ($organizationContactId) {
          self::addContactToGroup($organizationContactId, $groupId);
      }

  } catch (Exception $e) {
      // Do nothing.
  }

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
    $groupContacts = GroupContact::get(FALSE)
      ->addSelect('contact_id', 'group_id')
      ->addWhere('group_id.id', '=', $groupId)
      ->addWhere('contact_id', '=', $contactId)
      ->execute();

    if ($groupContacts->count() > 0) {
      return;
    }

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
        ->addSelect(
                'Institute_Registration.Type_of_Institution:label',
                'Category_of_Institution.Education_Institute:label'
            )
        ->addWhere('id', '=', $contactId)
        ->execute()
        ->single();
    }

    if (!$organization) {
      return;
    }

    $typeOfInstitution = $organization['Institute_Registration.Type_of_Institution:label'];
    $categoryOfInstitution = $organization['Category_of_Institution.Education_Institute:label'];

    // Define the default type-to-relationship mapping.
    $typeToRelationshipMap = [
      'Corporate'    => 'Corporate Coordinator of',
      'Foundation'   => 'Default Coordinator of',
      'Association' => 'Default Coordinator of',
      'Other'       => 'Default Coordinator of',
    ];

    if ($typeOfInstitution === 'Educational Institute') {
      if ($categoryOfInstitution === 'School') {
        return 'School Coordinator of';
      }
      elseif ($categoryOfInstitution === 'College/University') {
        return 'College Coordinator of';
      }
      return 'Default Coordinator of';
    }

    $firstWord = strtok($typeOfInstitution, ' ');
    // Return the mapped relationship type, or default if not found.
    return $typeToRelationshipMap[$firstWord] ?? 'Default Coordinator of';
  }

  /**
   *
   */
  public static function updateOrganizationStatus($contact) {
    $contactId = $contact['id'];

    $activityContacts = ActivityContact::get(TRUE)
      ->addSelect('activity_id', 'activity_id.created_date')
      ->addWhere('contact_id', '=', $contactId)
      ->addOrderBy('activity_id.created_date', 'DESC')
      ->execute();

    if ($activityContacts->count() === 0) {
      return;
    }

    $latestActivity = $activityContacts->last();
    $latestCreatedDate = $latestActivity['activity_id.created_date'] ?? NULL;

    if (!$latestCreatedDate) {
      return;
    }

    try {
      $latestActivityDate = new \DateTime($latestCreatedDate);
      $today = new \DateTime();

      $interval = $today->diff($latestActivityDate);

      if ($interval->y >= 1 || ($interval->y == 0 && $interval->m >= 12)) {
        Contact::update(FALSE)
          ->addValue('Review.Status:label', 'Inactive')
          ->addWhere('id', '=', $contactId)
          ->execute();
      }
      else {
        Contact::update(FALSE)
          ->addValue('Review.Status:label', 'Active')
          ->addWhere('id', '=', $contactId)
          ->execute();
      }
    }
    catch (\Exception $e) {
      error_log("Error processing activity date for contact ID: " . $contactId . ". Error: " . $e->getMessage());
    }
  }

}
