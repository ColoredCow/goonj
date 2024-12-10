<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\Contribution;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Email;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Api4\OptionValue;
use Civi\Api4\Relationship;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\CollectionSource;
use Civi\Traits\QrCodeable;
use Civi\Afform\Event\AfformSubmitEvent;
use Civi\Api4\Utils\CoreUtil;

/**
 *
 */
class IntitutionGoonjActivitiesService extends AutoSubscriber {
  use QrCodeable;
  use CollectionSource;
  const ENTITY_SUBTYPE_NAME = 'Institution_Goonj_Activities';
  const ENTITY_NAME = 'Collection_Camp';
  const FALLBACK_OFFICE_NAME = 'Delhi';
  private static $instituteGoonjActivitiesAddress = NULL;
  private static $institutePocAddress = NULL;
  const MATERIAL_RELATIONSHIP_TYPE_NAME = 'Material Management Team of';
  const Institution_Goonj_Activities_INTENT_FB_NAME = 'afformInstitutionGoonjActivitiesIntent';
  private static $addressAdded = FALSE;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_pre' => [
        ['assignChapterGroupToIndividual'],
        ['generateInstitutionGoonjActivitiesQr'],
      ],
    //   'civi.afform.submit' => [
    //     ['setGoonjActivitiesAddress', 10],
    //     ['setActivitiesVolunteersAddress', 20],
    //   ],
      '&hook_civicrm_custom' => [
        ['setOfficeDetails'],
      ],
      '&hook_civicrm_pre' => [
        ['createActivityForInstitutionGoonjActivityCollectionCamp'],
      ],
      '&hook_civicrm_tabset' => 'institutionGoonjActivitiesTabset',
    ];
  }

  /**
   *
   */
  private static function getChapterGroupForState($stateId) {
    $stateContactGroup = Group::get(FALSE)
      ->addSelect('id')
      ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
      ->addWhere('Chapter_Contact_Group.Contact_Catchment', 'CONTAINS', $stateId)
      ->execute()->first();

    if (!$stateContactGroup) {
      $stateContactGroup = Group::get(FALSE)
        ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
        ->addWhere('Chapter_Contact_Group.Fallback_Chapter', '=', 1)
        ->execute()->first();

    }

    return $stateContactGroup ? $stateContactGroup['id'] : NULL;
  }

  /**
   *
   */
  public static function assignChapterGroupToIndividual(string $op, string $objectName, $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'Eck_Collection_Camp' || empty($objectRef['title']) || $objectRef['title'] !== 'Institution Goonj Activities') {
      return FALSE;
    }

    $stateId = $objectRef['Institution_Goonj_Activities.State'];
    $contactId = $objectRef['Institution_Goonj_Activities.Institution_POC'];
    $organizationId = $objectRef['Institution_Goonj_Activities.Organization_Name'];

    if (!$stateId || !$contactId) {
      \Civi::log()->info("Missing Contact ID and State ID");
      return FALSE;
    }
    $groupId = self::getChapterGroupForState($stateId);

    if ($groupId) {
      self::addContactToGroup($contactId, $groupId);
      if ($organizationId) {
        self::addContactToGroup($organizationId, $groupId);
      }
    }
  }

  /**
   *
   */
  private static function addContactToGroup($contactId, $groupId) {
    try {
      GroupContact::create(FALSE)
        ->addValue('contact_id', $contactId)
        ->addValue('group_id', $groupId)
        ->addValue('status', 'Added')
        ->execute();
    }
    catch (Exception $e) {
      \Civi::log()->error("Error adding contact_id: $contactId to group_id: $groupId. Exception: " . $e->getMessage());
    }
  }

  /**
   *
   */
  private static function findStateField(array $array) {
    $institutionGoonjActivitiesStateField = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'state')
      ->addWhere('custom_group_id:name', '=', 'Institution_Goonj_Activities')
      ->execute()
      ->first();

    if (!$institutionGoonjActivitiesStateField) {
      return FALSE;
    }

    $stateFieldId = $institutionGoonjActivitiesStateField['id'];

    foreach ($array as $item) {
      if (isset($item['entity_table']) && $item['entity_table'] === 'civicrm_eck_collection_camp' &&
          isset($item['custom_field_id']) && $item['custom_field_id'] === $stateFieldId) {
        return $item;
      }
    }

    return FALSE;
  }

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
  public static function assignCoordinatorByRelationshipType($stateOfficeId, $registrationType, $collectionCampId) {
    // Define the mapping of registration categories to relationship type names.
    $relationshipTypeMap = [
      'A_Corporate_organisation' => 'Corporate Organisation Coordinator of',
      'A_School' => 'School Coordinator of',
      'A_College_University' => 'University/College Coordinator of',
    ];

    $registrationCategorySelection = $registrationType['Institution_Goonj_Activities.You_wish_to_register_as:name'];

    $registrationCategorySelection = trim($registrationCategorySelection);
    \Civi::log()->info('registrationCategorySelection', ['registrationCategorySelection' => $registrationCategorySelection, 'relationshipTypeMap' => $relationshipTypeMap]);

    if (array_key_exists($registrationCategorySelection, $relationshipTypeMap)) {
      $relationshipTypeName = $relationshipTypeMap[$registrationCategorySelection];
    }
    else {
      $relationshipTypeName = 'Other Entities Coordinator of';
    }
    \Civi::log()->info('relationshipTypeName', ['relationshipTypeName' => $relationshipTypeName, '------', 'stateOfficeId' => $stateOfficeId]);

    // Retrieve the coordinators for the selected relationship type.
    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $stateOfficeId)
      ->addWhere('relationship_type_id:name', '=', $relationshipTypeName)
      ->addWhere('is_current', '=', TRUE)
      ->execute();

    $coordinator = self::getCoordinator($stateOfficeId, $relationshipTypeName, $coordinators);
    if (!$coordinator) {
      \CRM_Core_Error::debug_log_message('No coordinator available to assign.');
      return FALSE;
    }

    // Assign the coordinator to the collection camp.
    $res = EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Institution_Goonj_Activities.Coordinating_Urban_Poc', $coordinator['contact_id_a'])
      ->addWhere('id', '=', $collectionCampId)
      ->execute();
    \Civi::log()->info('$coordinator', ['id' => $coordinator['contact_id_a'], 'res' => $res]);

    return TRUE;
  }

  /**
   *
   */
  public static function getCoordinator($stateOfficeId, $relationshipTypeName, $existingCoordinators = NULL) {
    if (!$existingCoordinators) {
      $existingCoordinators = Relationship::get(FALSE)
        ->addWhere('contact_id_b', '=', $stateOfficeId)
        ->addWhere('relationship_type_id:name', '=', $relationshipTypeName)
        ->addWhere('is_current', '=', TRUE)
        ->execute();
    }

    if ($existingCoordinators->count() === 0) {
      return self::getFallbackCoordinator($relationshipTypeName);
    }

    $coordinatorCount = $existingCoordinators->count();
    return $existingCoordinators->count() > 1
        ? $existingCoordinators->itemAt(rand(0, $coordinatorCount - 1))
        : $existingCoordinators->first();
  }

  /**
   *
   */
  public static function getFallbackCoordinator($relationshipTypeName) {
    $fallbackOffice = self::getFallbackOffice();
    if (!$fallbackOffice) {
      \CRM_Core_Error::debug_log_message('No fallback office found.');
      return FALSE;
    }

    // Retrieve fallback coordinators associated with the fallback office and relationship type.
    $fallbackCoordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $fallbackOffice['id'])
      ->addWhere('relationship_type_id:name', '=', $relationshipTypeName)
      ->addWhere('is_current', '=', TRUE)
      ->execute();

    // If no coordinators found, return false.
    if ($fallbackCoordinators->count() === 0) {
      \CRM_Core_Error::debug_log_message('No fallback coordinators found.');
      return FALSE;
    }

    // Randomly select a fallback coordinator if more than one is found.
    $randomIndex = rand(0, $fallbackCoordinators->count() - 1);
    return $fallbackCoordinators->itemAt($randomIndex);
  }

  /**
   *
   */
  public static function setOfficeDetails($op, $groupID, $entityID, &$params) {
    // \Civi::log()->info('getEntitySubtypeName', ['getEntitySubtypeName' => self::getEntitySubtypeName($entityID)]);
    if ($op !== 'create' || self::getEntitySubtypeName($entityID) !== self::ENTITY_SUBTYPE_NAME) {
      return;
    }
    if (!($stateField = self::findStateField($params))) {
      return;
    }

    $stateId = $stateField['value'];
    \Civi::log()->info('stateId', ['stateId' => $stateId]);
    $institutionCollectionCampId = $stateField['entity_id'];

    // $institutionCollectionCampData = EckEntity::get('Collection_Camp', FALSE)
    //   ->addSelect('Institution_Goonj_Activities.Will_your_collection_drive_be_open_for_general_public_as_well')
    //   ->addWhere('id', '=', $institutionCollectionCampId)
    //   ->execute()->single();
    // $isPublicDriveOpen = $institutionCollectionCampData['Institution_Goonj_Activities.Will_your_collection_drive_be_open_for_general_public_as_well'];
    if (!$stateId) {
      \CRM_Core_Error::debug_log_message('Cannot assign Goonj Office to institution collection camp: ' . $institutionCollectionCampId);
      return FALSE;
    }

    $officesFound = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addWhere('Goonj_Office_Details.Institution_Catchment', 'CONTAINS', $stateId)
      ->execute();

    $stateOffice = $officesFound->first();

    if (!$stateOffice) {
      $stateOffice = self::getFallbackOffice();
    }

    $stateOfficeId = $stateOffice['id'];
    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Institution_Goonj_Activities.Goonj_Office', $stateOfficeId)
      ->addWhere('id', '=', $institutionCollectionCampId)
      ->execute();

    $registrationType = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Institution_Goonj_Activities.You_wish_to_register_as:name')
      ->addWhere('id', '=', $entityID)
      ->execute()->single();

    return self::assignCoordinatorByRelationshipType($stateOfficeId, $registrationType, $institutionCollectionCampId);

  }

    /**
   *
   */
  public static function generateInstitutionGoonjActivitiesQr(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName !== 'Eck_Collection_Camp' || !$objectId || !self::isCurrentSubtype($objectRef)) {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';
    if (!$newStatus) {
      return;
    }

    $collectionCamp = EckEntity::get('Collection_Camp', TRUE)
      ->addSelect('Collection_Camp_Core_Details.Status')
      ->addWhere('id', '=', $objectId)
      ->execute()->first();

    $currentStatus = $collectionCamp['Collection_Camp_Core_Details.Status'];
    $collectionCampId = $collectionCamp['id'];
    \Civi::log()->info('currentStatus', ['currentStatus'=>$currentStatus, 'newStatus'=>$newStatus]);

    // Check for status change.
    if ($currentStatus !== $newStatus && $newStatus === 'authorized') {
      self::generateInstitutionGoonjActivitiesQrCode($collectionCampId);
    }
  }

    /**
   *
   */
  private static function generateInstitutionGoonjActivitiesQrCode($id) {
    $baseUrl = \CRM_Core_Config::singleton()->userFrameworkBaseURL;
    $data = "{$baseUrl}actions/institution-goonj-activities/{$id}";

    $saveOptions = [
      'customGroupName' => 'Collection_Camp_QR_Code',
      'customFieldName' => 'QR_Code',
    ];
    \Civi::log()->info('baseUrl', ['baseUrl'=>$baseUrl, 'saveOptions'=>$saveOptions]);

    self::generateQrCode($data, $id, $saveOptions);
  }

    /**
   *
   */
  private static function isViewingIntitutionGoonjActivities($tabsetName, $context) {
    if ($tabsetName !== 'civicrm/eck/entity' || empty($context) || $context['entity_type']['name'] !== self::ENTITY_NAME) {
      return FALSE;
    }

    $entityId = $context['entity_id'];

    $entity = EckEntity::get(self::ENTITY_NAME, TRUE)
      ->addWhere('id', '=', $entityId)
      ->execute()->single();

    $entitySubtypeValue = $entity['subtype'];

    $subtypeId = self::getSubtypeId();

    return (int) $entitySubtypeValue === $subtypeId;
  }

  /**
   *
   */
  public static function institutionGoonjActivitiesTabset($tabsetName, &$tabs, $context) {
    if (!self::isViewingIntitutionGoonjActivities($tabsetName, $context)) {
      return;
    }

    $tabConfigs = [
      'activities' => [
        'title' => ts('Activities'),
        'module' => 'afsearchGoonjAllActivity',
        'directive' => 'afsearch-goonj-all-activity',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'logistics' => [
        'title' => ts('Logistics'),
        'module' => 'afsearchGoonjActivitiesLogistics',
        'directive' => 'afsearch-goonj-activities-logistics',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'eventVolunteers' => [
        'title' => ts('Event Volunteers'),
        'module' => 'afsearchEventVolunteer',
        'directive' => 'afsearch-event-volunteer',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'campOutcome' => [
        'title' => ts('Outcome'),
        'module' => 'afsearchGoonjActivitiesOutcomeView',
        'directive' => 'afsearch-goonj-activities-outcome-view',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'campFeedback' => [
        'title' => ts('Volunteer Feedback'),
        'module' => 'afsearchGoonjActivityVolunteerFeedback',
        'directive' => 'afsearch-goonj-activity-volunteer-feedback',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'attendeeFeedback' => [
        'title' => ts('Attendee Feedback'),
        'module' => 'afsearchGoonjActivityAttendeeFeedbacks',
        'directive' => 'afsearch-goonj-activity-attendee-feedbacks',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'monetaryContribution' => [
        'title' => ts('Monetary Contribution'),
        'module' => 'afsearchMonetaryContribution',
        'directive' => 'afsearch-monetary-contribution',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['account_team'],
      ],
      'monetaryContributionForUrbanOps' => [
        'title' => ts('Monetary Contribution'),
        'module' => 'afsearchMonetaryContributionForUrbanOps',
        'directive' => 'afsearch-monetary-contribution-for-urban-ops',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
    ];

    foreach ($tabConfigs as $key => $config) {
      $isAdmin = \CRM_Core_Permission::check('admin');
      if ($key == 'monetaryContributionForUrbanOps' && $isAdmin) {
        continue;
      }

      if (!\CRM_Core_Permission::checkAnyPerm($config['permissions'])) {
        // Does not permission; just continue.
        continue;
      }

      $tabs[$key] = [
        'id' => $key,
        'title' => $config['title'],
        'is_active' => 1,
        'template' => $config['template'],
        'module' => $config['module'],
        'directive' => $config['directive'],
      ];

      \Civi::service('angularjs.loader')->addModules($config['module']);
    }
}

  /**
   * This hook is called after a db write on entities.
   *
   * @param string $op
   *   The type of operation being performed.
   * @param string $objectName
   *   The name of the object.
   * @param int $objectId
   *   The unique identifier for the object.
   * @param object $objectRef
   *   The reference to the object.
   */
  public static function createActivityForInstitutionGoonjActivityCollectionCamp(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName != 'Eck_Collection_Camp' || self::getEntitySubtypeName($objectId) !== self::ENTITY_SUBTYPE_NAME) {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';
    \Civi::log()->info('newStatus', ['newStatus'=>$newStatus, 'objectId'=>$objectId]);

    if (!$newStatus || !$objectId) {
      return;
    }

    $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Institution_Goonj_Activities.Institution_POC', 'title')
      ->addWhere('id', '=', $objectId)
      ->execute()->single();


    $currentStatus = $collectionCamp['Collection_Camp_Core_Details.Status'];
    \Civi::log()->info('collectionCamp', ['collectionCamp'=>$collectionCamp, 'objectId'=>$objectId, 'currentStatus'=>$currentStatus]);

    if ($currentStatus === $newStatus || $newStatus !== 'authorized') {
      return;
    }

    // // Check for status change.
    // // Access the id within the decoded data.
    $campId = $objectRef['id'];
    
    \Civi::log()->info('campId', ['campId'=>$campId]);
    if ($campId === NULL) {
      return;
    }
    \Civi::log()->info('campIdobjectRef', ['campIdobjectRef'=>$objectRef]);

    $activities = $objectRef['Institution_Goonj_Activities.How_do_you_want_to_engage_with_Goonj_'];
    $activityDate = $objectRef['Institution_Goonj_Activities.Date_for_conducting_the_activity_'];
    // $startDate = $objectRef['Goonj_Activities.Start_Date'];
    // $endDate = $objectRef['Goonj_Activities.End_Date'];
    $initiator = $objectRef['Institution_Goonj_Activities.Institution_POC'];

    foreach ($activities as $activityName) {
      // Check if the activity is 'Others'.
      \Civi::log()->info('optionValue', ['optionValue'=>$optionValue, 'activityName'=>$activityName]);
      if ($activityName == 'Other') {
        $otherActivity = $objectRef['Institution_Goonj_Activities.Other_Activity_Details'] ?? '';

        if ($otherActivity) {
          // Use the 'Other_activity' field as the title.
          $activityName = $otherActivity;
        }
        else {
          continue;
        }
      }
      \Civi::log()->info('optionValue', ['optionValue'=>$optionValue, 'activityName'=>$activityName]);
      $optionValue = OptionValue::get(TRUE)
        ->addSelect('value')
        ->addWhere('option_group_id:name', '=', 'eck_sub_types')
        ->addWhere('grouping', '=', 'Collection_Camp_Activity')
        ->addWhere('name', '=', 'Institution_Goonj_Activities')
        ->execute()->single();
        \Civi::log()->info('optionValue', ['optionValue'=>$optionValue, 'activityName'=>$activityName]);

      $results = EckEntity::create('Collection_Camp_Activity', TRUE)
        ->addValue('title', $activityName)
        ->addValue('subtype', $optionValue['value'])
        ->addValue('Collection_Camp_Activity.Collection_Camp_Id', $campId)
        ->addValue('Collection_Camp_Activity.Activity_Date', $activityDate)
        ->addValue('Collection_Camp_Activity.Organizing_Person', $initiator)
        ->execute();
      \Civi::log()->info('results', ['results'=>$results]);
    }
  }

}