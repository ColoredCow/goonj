<?php

namespace Civi;

require_once __DIR__ . '/../../../../wp-content/civi-extensions/goonjcustom/vendor/autoload.php';

use Civi\Afform\Event\AfformSubmitEvent;
use Civi\Api4\Activity;
use Civi\Api4\Address;
use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Email;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Api4\OptionValue;
use Civi\Api4\Relationship;
use Civi\Api4\StateProvince;
use Civi\Api4\Utils\CoreUtil;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\CollectionSource;
use Civi\Traits\QrCodeable;

/**
 *
 */
class CollectionCampService extends AutoSubscriber {
  use QrCodeable;
  use CollectionSource;

  const FALLBACK_OFFICE_NAME = 'Delhi';
  const RELATIONSHIP_TYPE_NAME = 'Collection Camp Coordinator of';
  const COLLECTION_CAMP_INTENT_FB_NAME = 'afformCollectionCampIntentDetails';
  const ENTITY_NAME = 'Collection_Camp';
  const ENTITY_SUBTYPE_NAME = 'Collection_Camp';
  const MATERIAL_RELATIONSHIP_TYPE_NAME = 'Material Management Team of';

  private static $individualId = NULL;
  private static $collectionCampAddress = NULL;
  private static $fromAddress = NULL;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => [
        ['individualCreated'],
        ['assignChapterGroupToIndividual'],
        ['reGenerateCollectionCampQr'],
        ['updateCampStatusOnOutcomeFilled'],
        ['assignChapterGroupToIndividualForContribution'],
      ],
      '&hook_civicrm_pre' => [
        ['generateCollectionCampQr'],
        ['linkCollectionCampToContact'],
        ['generateCollectionCampCode'],
        ['createActivityForCollectionCamp'],
        ['updateCampStatusAfterAuth'],
      ],
      '&hook_civicrm_custom' => [
        ['setOfficeDetails'],
        ['linkInductionWithCollectionCamp'],
        ['mailNotificationToMmt'],
      ],
      '&hook_civicrm_fieldOptions' => 'setIndianStateOptions',
      'civi.afform.submit' => [
        ['setCollectionCampAddress', 9],
        ['setEventVolunteersAddress', 8],
      ],
      '&hook_civicrm_tabset' => 'collectionCampTabset',
      '&hook_civicrm_buildForm' => [
        ['autofillMonetaryFormSource'],
      ],
    ];
  }

  /**
   *
   */
  public static function collectionCampTabset($tabsetName, &$tabs, $context) {
    if (!self::isViewingCollectionCamp($tabsetName, $context)) {
      return;
    }

    $tabConfigs = [
      // 'activities' => [
      //   'title' => ts('Activities'),
      //   'module' => 'afsearchCollectionCampActivity',
      //   'directive' => 'afsearch-collection-camp-activity',
      //   'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
      //   'permissions' => ['goonj_chapter_admin'],
      // ],
      'logistics' => [
        'title' => ts('Logistics'),
        'module' => 'afsearchCollectionCampLogistics',
        'directive' => 'afsearch-collection-camp-logistics',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin'],
      ],
      'eventVolunteers' => [
        'title' => ts('Event Volunteers'),
        'module' => 'afsearchEventVolunteer',
        'directive' => 'afsearch-event-volunteer',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin'],
      ],
      'vehicleDispatch' => [
        'title' => ts('Dispatch'),
        'module' => 'afsearchCampVehicleDispatchData',
        'directive' => 'afsearch-camp-vehicle-dispatch-data',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin'],
      ],
      'materialAuthorization' => [
        'title' => ts('Material Authorization'),
        'module' => 'afsearchAcknowledgementForLogisticsData',
        'directive' => 'afsearch-acknowledgement-for-logistics-data',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin'],
      ],
      'materialContribution' => [
        'title' => ts('Material Contribution'),
        'module' => 'afsearchCollectionCampMaterialContributions',
        'directive' => 'afsearch-collection-camp-material-contributions',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin'],
      ],
      'campOutcome' => [
        'title' => ts('Camp Outcome'),
        'module' => 'afsearchCampOutcome',
        'directive' => 'afsearch-camp-outcome',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin'],
      ],
      'campFeedback' => [
        'title' => ts('Volunteer Feedback'),
        'module' => 'afsearchVolunteerFeedback',
        'directive' => 'afsearch-volunteer-feedback',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin'],
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
        'permissions' => ['goonj_chapter_admin'],
      ],
    ];

    foreach ($tabConfigs as $key => $config) {
      // Skip if the current user does not have the required permissions.
      $hasPermission = \CRM_Core_Permission::check($config['permissions']);
      if (!$hasPermission) {
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
   *
   */
  private static function isViewingCollectionCamp($tabsetName, $context) {
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
  public static function setCollectionCampAddress(AfformSubmitEvent $event) {
    $afform = $event->getAfform();
    $formName = $afform['name'];

    if ($formName !== self::COLLECTION_CAMP_INTENT_FB_NAME) {
      return;
    }

    $entityType = $event->getEntityType();

    if ($entityType !== 'Eck_Collection_Camp') {
      return;
    }

    $records = $event->records;

    foreach ($records as $record) {
      $fields = $record['fields'];

      self::$collectionCampAddress = [
        'location_type_id' => 3,
        'state_province_id' => $fields['Collection_Camp_Intent_Details.State'],
      // India.
        'country_id' => 1101,
        'street_address' => $fields['Collection_Camp_Intent_Details.Location_Area_of_camp'],
        'city' => $fields['Collection_Camp_Intent_Details.City'],
        'postal_code' => $fields['Collection_Camp_Intent_Details.Pin_Code'],
        'is_primary' => 1,
      ];
    }
  }

  /**
   *
   */
  public static function setEventVolunteersAddress(AfformSubmitEvent $event) {
    $afform = $event->getAfform();
    $formName = $afform['name'];

    if ($formName !== self::COLLECTION_CAMP_INTENT_FB_NAME) {
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

      $event->records[$index]['joins']['Address'][] = self::$collectionCampAddress;
    }

  }

  /**
   *
   */
  public static function individualCreated(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'Individual') {
      return FALSE;
    }

    self::$individualId = $objectId;
  }

  /**
   *
   */
  public static function assignChapterGroupToIndividual(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'Address') {
      return FALSE;
    }

    if (self::$individualId !== $objectRef->contact_id || !$objectRef->is_primary) {
      return FALSE;
    }

    $groupId = self::getChapterGroupForState($objectRef->state_province_id);

    if ($groupId) {
      GroupContact::create(FALSE)
        ->addValue('contact_id', self::$individualId)
        ->addValue('group_id', $groupId)
        ->addValue('status', 'Added')
        ->execute();
    }
  }

  /**
   *
   */
  public static function assignChapterGroupToIndividualForContribution(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'Contribution') {
      return FALSE;
    }

    if (self::$individualId !== $objectRef->contact_id || !$objectRef->contact_id) {
      return FALSE;
    }

    $contactId = $objectRef->contact_id;

    $address = Address::get(FALSE)
      ->addSelect('state_province_id')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('is_primary', '=', 1)
      ->execute()->first();

    $stateId = $address['state_province_id'] ?? NULL;

    if ($stateId) {
      $groupId = self::getChapterGroupForState($stateId);

      if ($groupId) {
        GroupContact::create(FALSE)
          ->addValue('contact_id', self::$individualId)
          ->addValue('group_id', $groupId)
          ->addValue('status', 'Added')
          ->execute();
      }
    }
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
  public static function generateCollectionCampCode(string $op, string $objectName, $objectId, &$objectRef) {
    $statusDetails = self::checkCampStatusAndIds($objectName, $objectId, $objectRef);

    if (!$statusDetails) {
      return;
    }

    $newStatus = $statusDetails['newStatus'];
    $currentStatus = $statusDetails['currentStatus'];

    if ($currentStatus !== $newStatus) {
      if ($newStatus === 'authorized') {
        $subtypeId = $objectRef['subtype'] ?? NULL;
        if ($subtypeId === NULL) {
          return;
        }

        $campId = $objectRef['id'] ?? NULL;
        if ($campId === NULL) {
          return;
        }

        // Fetch the collection camp details.
        $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
          ->addWhere('id', '=', $campId)
          ->execute()->single();

        $collectionCampsCreatedDate = $collectionCamp['created_date'] ?? NULL;

        // Get the year.
        $year = date('Y', strtotime($collectionCampsCreatedDate));

        // Fetch the state ID.
        $stateId = self::getStateIdForSubtype($objectRef, $subtypeId);

        if (!$stateId) {
          return;
        }

        // Fetch the state abbreviation.
        $stateProvince = StateProvince::get(FALSE)
          ->addWhere('id', '=', $stateId)
          ->execute()->single();

        if (empty($stateProvince)) {
          return;
        }

        $stateAbbreviation = $stateProvince['abbreviation'] ?? NULL;
        if (!$stateAbbreviation) {
          return;
        }

        // Fetch the Goonj-specific state code.
        $config = self::getConfig();
        $stateCode = $config['state_codes'][$stateAbbreviation] ?? 'UNKNOWN';

        // Get the current event title.
        $currentTitle = $objectRef['title'] ?? 'Collection Camp';

        // Fetch the event code.
        $eventCode = $config['event_codes'][$currentTitle] ?? 'UNKNOWN';

        // Modify the title to include the year, state code, event code, and camp Id.
        $newTitle = $year . '/' . $stateCode . '/' . $eventCode . '/' . $campId;
        $objectRef['title'] = $newTitle;

        // Save the updated title back to the Collection Camp entity.
        EckEntity::update('Collection_Camp')
          ->addWhere('id', '=', $campId)
          ->addValue('title', $newTitle)
          ->execute();
      }
    }
  }

  /**
   *
   */
  private static function getConfig() {
    // Get the path to the CiviCRM extensions directory.
    $extensionsDir = \CRM_Core_Config::singleton()->extensionsDir;

    // Relative path to the extension's config directory.
    $extensionPath = $extensionsDir . 'goonjcustom/config/';

    // Include and return the configuration files.
    return [
      'state_codes' => include $extensionPath . 'constants.php',
      'event_codes' => include $extensionPath . 'eventCode.php',
    ];
  }

  /**
   *
   */
  public static function getStateIdForSubtype(array $objectRef, int $subtypeId): ?int {
    $optionValue = OptionValue::get(TRUE)
      ->addSelect('value')
      ->addWhere('option_group_id:name', '=', 'eck_sub_types')
      ->addWhere('grouping', '=', 'Collection_Camp')
      ->addWhere('name', '=', 'Dropping_Center')
      ->execute()->single();

    // Subtype for 'Dropping Centre'.
    if ($subtypeId == $optionValue['value']) {
      return $objectRef['Dropping_Centre.State'] ?? NULL;
    }
    return $objectRef['Collection_Camp_Intent_Details.State'] ?? NULL;
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
  public static function linkCollectionCampToContact(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName != 'Eck_Collection_Camp' || !$objectId) {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';

    if (!$newStatus) {
      return;
    }

    $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_Core_Details.Contact_Id', 'title')
      ->addWhere('id', '=', $objectId)
      ->execute();

    $currentCollectionCamp = $collectionCamps->first();
    $currentStatus = $currentCollectionCamp['Collection_Camp_Core_Details.Status'];
    $contactId = $currentCollectionCamp['Collection_Camp_Core_Details.Contact_Id'];
    $collectionCampTitle = $currentCollectionCamp['title'];
    $collectionCampId = $currentCollectionCamp['id'];

    // Check for status change.
    if ($currentStatus !== $newStatus) {
      if ($newStatus === 'authorized') {
        self::createCollectionCampOrganizeActivity($contactId, $collectionCampTitle, $collectionCampId);
      }
    }
  }

  /**
   * Log an activity in CiviCRM.
   */
  private static function createCollectionCampOrganizeActivity($contactId, $collectionCampTitle, $collectionCampId) {
    try {
      $results = Activity::create(FALSE)
        ->addValue('subject', $collectionCampTitle)
        ->addValue('activity_type_id:name', 'Organize Collection Camp')
        ->addValue('status_id:name', 'Authorized')
        ->addValue('activity_date_time', date('Y-m-d H:i:s'))
        ->addValue('source_contact_id', $contactId)
        ->addValue('target_contact_id', $contactId)
        ->addValue('Collection_Camp_Data.Collection_Camp_ID', $collectionCampId)
        ->execute();

    }
    catch (\CiviCRM_API4_Exception $ex) {
      \Civi::log()->debug("Exception while creating Organize Collection Camp activity: " . $ex->getMessage());
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
  public static function generateCollectionCampQr(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName !== 'Eck_Collection_Camp' || !$objectId || !self::isCurrentSubtype($objectRef)) {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';

    if (!$newStatus) {
      return;
    }

    $collectionCamps = EckEntity::get('Collection_Camp', TRUE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_Core_Details.Contact_Id')
      ->addWhere('id', '=', $objectId)
      ->execute();

    $currentCollectionCamp = $collectionCamps->first();
    $currentStatus = $currentCollectionCamp['Collection_Camp_Core_Details.Status'];
    $collectionCampId = $currentCollectionCamp['id'];

    // Check for status change.
    if ($currentStatus !== $newStatus) {
      if ($newStatus === 'authorized') {
        self::generateCollectionCampQrCode($collectionCampId);

      }
    }
  }

  /**
   *
   */
  private static function generateCollectionCampQrCode($id) {
    $baseUrl = \CRM_Core_Config::singleton()->userFrameworkBaseURL;
    $data = "{$baseUrl}actions/collection-camp/{$id}";

    $saveOptions = [
      'customGroupName' => 'Collection_Camp_QR_Code',
      'customFieldName' => 'QR_Code',
    ];

    self::generateQrCode($data, $id, $saveOptions);

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
  public static function reGenerateCollectionCampQr(string $op, string $objectName, int $objectId, &$objectRef) {
    // Check if the object name is 'Eck_Collection_Camp'.
    if ($objectName !== 'Eck_Collection_Camp' || !$objectRef->id) {
      return;
    }

    try {
      $collectionCampId = $objectRef->id;
      $collectionCamp = EckEntity::get('Collection_Camp', TRUE)
        ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_QR_Code.QR_Code')
        ->addWhere('id', '=', $collectionCampId)
        ->execute()->single();

      $status = $collectionCamp['Collection_Camp_Core_Details.Status'];
      $collectionCampQr = $collectionCamp['Collection_Camp_QR_Code.QR_Code'];

      if ($status !== 'authorized' || $collectionCampQr !== NULL) {
        return;
      }

      self::generateCollectionCampQrCode($collectionCampId);

    }
    catch (\Exception $e) {
      // @ignoreException
    }

  }

  /**
   * This hook is called after the database write on a custom table.
   *
   * @param string $op
   *   The type of operation being performed.
   * @param string $objectName
   *   The custom group ID.
   * @param int $objectId
   *   The entityID of the row in the custom table.
   * @param object $objectRef
   *   The parameters that were sent into the calling function.
   */
  public static function setOfficeDetails($op, $groupID, $entityID, &$params) {
    if ($op !== 'create' || self::getEntitySubtypeName($entityID) !== self::ENTITY_SUBTYPE_NAME) {
      return;
    }

    if (!($stateField = self::findStateField($params))) {
      return;
    }

    $stateId = $stateField['value'];
    $collectionCampId = $stateField['entity_id'];

    $collectionCampData = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Intent_Details.Will_your_collection_drive_be_open_for_general_public')
      ->addWhere('id', '=', $collectionCampId)
      ->execute()->single();

    $isPublicDriveOpen = $collectionCampData['Collection_Camp_Intent_Details.Will_your_collection_drive_be_open_for_general_public'];

    if (!$stateId) {
      \CRM_Core_Error::debug_log_message('Cannot assign Goonj Office to collection camp: ' . $collectionCampData['id']);
      \CRM_Core_Error::debug_log_message('No state provided on the intent for collection camp: ' . $collectionCampData['id']);
      return FALSE;
    }

    $officesFound = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addWhere('Goonj_Office_Details.Collection_Camp_Catchment', 'CONTAINS', $stateId)
      ->execute();

    $stateOffice = $officesFound->first();

    // If no state office is found, assign the fallback state office.
    if (!$stateOffice) {
      $stateOffice = self::getFallbackOffice();
    }

    $stateOfficeId = $stateOffice['id'];

    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Collection_Camp_Intent_Details.Goonj_Office', $stateOfficeId)
      ->addValue('Collection_Camp_Intent_Details.Camp_Type', $isPublicDriveOpen)
      ->addWhere('id', '=', $collectionCampId)
      ->execute();

    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $stateOfficeId)
      ->addWhere('relationship_type_id:name', '=', self::RELATIONSHIP_TYPE_NAME)
      ->addWhere('is_current', '=', TRUE)
      ->execute();

    $coordinatorCount = $coordinators->count();

    if ($coordinatorCount === 0) {
      $coordinator = self::getFallbackCoordinator();
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

    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Collection_Camp_Intent_Details.Coordinating_Urban_POC', $coordinatorId)
      ->addWhere('id', '=', $collectionCampId)
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
   * @param int $objectId
   *   The entityID of the row in the custom table.
   * @param object $objectRef
   *   The parameters that were sent into the calling function.
   */
  public static function linkInductionWithCollectionCamp($op, $groupID, $entityID, &$params) {
    if ($op !== 'create') {
      return;
    }

    if (!($contactId = self::findCollectionCampInitiatorContact($params))) {
      return;
    }

    $collectionCampId = $contactId['entity_id'];

    $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Contact_Id', 'custom.*')
      ->addWhere('id', '=', $collectionCampId)
      ->execute()->single();

    $contactId = $collectionCamp['Collection_Camp_Core_Details.Contact_Id'];

    $optionValue = OptionValue::get(FALSE)
      ->addWhere('option_group_id:name', '=', 'activity_type')
      ->addWhere('label', '=', 'Induction')
      ->execute()->single();

    $activityTypeId = $optionValue['value'];

    $induction = Activity::get(FALSE)
      ->addSelect('id')
      ->addWhere('target_contact_id', '=', $contactId)
      ->addWhere('activity_type_id', '=', $activityTypeId)
      ->addOrderBy('created_date', 'DESC')
      ->setLimit(1)
      ->execute()->single();

    $inductionId = $induction['id'];

    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Collection_Camp_Intent_Details.Initiator_Induction_Id', $inductionId)
      ->addWhere('id', '=', $collectionCampId)
      ->execute();
  }

  /**
   *
   */
  private static function findStateField(array $array) {
    $collectionCampStateField = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'state')
      ->addWhere('custom_group_id:name', '=', 'Collection_Camp_Intent_Details')
      ->execute()
      ->first();

    if (!$collectionCampStateField) {
      return FALSE;
    }

    $stateFieldId = $collectionCampStateField['id'];

    foreach ($array as $item) {
      if ($item['entity_table'] === 'civicrm_eck_collection_camp' &&
            $item['custom_field_id'] === $stateFieldId) {
        return $item;
      }
    }

    return FALSE;
  }

  /**
   *
   */
  private static function findCollectionCampInitiatorContact(array $array) {
    $filteredItems = array_filter($array, fn($item) => $item['entity_table'] === 'civicrm_eck_collection_camp');

    if (empty($filteredItems)) {
      return FALSE;
    }

    $collectionCampContactId = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'Contact_Id')
      ->addWhere('custom_group_id:name', '=', 'Collection_Camp_Core_Details')
      ->execute()
      ->first();

    if (!$collectionCampContactId) {
      return FALSE;
    }

    $contactFieldId = $collectionCampContactId['id'];

    $contactItemIndex = array_search(TRUE, array_map(fn($item) =>
        $item['entity_table'] === 'civicrm_eck_collection_camp' &&
        $item['custom_field_id'] == $contactFieldId,
        $filteredItems
    ));

    return $contactItemIndex !== FALSE ? $filteredItems[$contactItemIndex] : FALSE;
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
  private static function getFallbackCoordinator() {
    $fallbackOffice = self::getFallbackOffice();
    $fallbackCoordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $fallbackOffice['id'])
      ->addWhere('relationship_type_id:name', '=', self::RELATIONSHIP_TYPE_NAME)
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
  public static function setIndianStateOptions(string $entity, string $field, array &$options, array $params) {
    if ($entity !== 'Eck_Collection_Camp') {
      return;
    }

    $intentStateFields = CustomField::get(FALSE)
      ->addWhere('custom_group_id:name', '=', 'Collection_Camp_Intent_Details')
      ->addWhere('name', '=', 'State')
      ->execute();

    $stateField = $intentStateFields->first();

    $statefieldId = $stateField['id'];

    if ($field !== "custom_$statefieldId") {
      return;
    }

    $indianStates = StateProvince::get(FALSE)
      ->addWhere('country_id.iso_code', '=', 'IN')
      ->addOrderBy('name', 'ASC')
      ->execute();

    $stateOptions = [];
    foreach ($indianStates as $state) {
      if ($state['is_active']) {
        $stateOptions[$state['id']] = $state['name'];
      }
    }

    $options = $stateOptions;

  }

  /**
   * This hook is called after the database write on a custom table.
   *
   * @param string $op
   *   The type of operation being performed.
   * @param string $objectName
   *   The custom group ID.
   * @param int $objectId
   *   The entityID of the row in the custom table.
   * @param object $objectRef
   *   The parameters that were sent into the calling function.
   */
  public static function mailNotificationToMmt($op, $groupID, $entityID, &$params) {
    if ($op !== 'create') {
      return;
    }
    if (!($goonjField = self::findOfficeId($params))) {
      return;
    }

    $goonjFieldId = $goonjField['value'];
    $vehicleDispatchId = $goonjField['entity_id'];

    $collectionSourceVehicleDispatch = EckEntity::get('Collection_Source_Vehicle_Dispatch', FALSE)
      ->addSelect('Camp_Vehicle_Dispatch.Collection_Camp')
      ->addWhere('id', '=', $vehicleDispatchId)
      ->execute()->first();

    $collectionCampId = $collectionSourceVehicleDispatch['Camp_Vehicle_Dispatch.Collection_Camp'];

    if (self::getEntitySubtypeName($collectionCampId) !== self::ENTITY_SUBTYPE_NAME) {
      return;
    }

    $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Intent_Details.Location_Area_of_camp', 'title')
      ->addWhere('id', '=', $collectionCampId)
      ->execute()->single();

    $campCode = $collectionCamp['title'];
    $campAddress = $collectionCamp['Collection_Camp_Intent_Details.Location_Area_of_camp'];

    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $goonjFieldId)
      ->addWhere('relationship_type_id:name', '=', self::MATERIAL_RELATIONSHIP_TYPE_NAME)
      ->addWhere('is_current', '=', TRUE)
      ->execute()->first();

    $mmtId = $coordinators['contact_id_a'];

    if (empty($mmtId)) {
      return;
    }

    $email = Email::get(FALSE)
      ->addSelect('email')
      ->addWhere('contact_id', '=', $mmtId)
      ->execute()->single();

    $mmtEmail = $email['email'];

    $fromEmail = OptionValue::get(FALSE)
      ->addSelect('label')
      ->addWhere('option_group_id:name', '=', 'from_email_address')
      ->addWhere('is_default', '=', TRUE)
      ->execute()->single();

    // Email to material management team member.
    $mailParams = [
      'subject' => 'Material Acknowledgement for Camp: ' . $campCode . ' at ' . $campAddress,
      'from' => $fromEmail['label'],
      'toEmail' => $mmtEmail,
      'replyTo' => $fromEmail['label'],
      'html' => self::goonjcustom_material_management_email_html($collectionCampId, $campCode, $campAddress, $vehicleDispatchId),
        // 'messageTemplateID' => 76, // Uncomment if using a message template
    ];
    \CRM_Utils_Mail::send($mailParams);

  }

  /**
   *
   */
  public static function goonjcustom_material_management_email_html($collectionCampId, $campCode, $campAddress, $vehicleDispatchId) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $materialdispatchUrl = $homeUrl . 'acknowledgement-form-for-logistics/#?Eck_Collection_Source_Vehicle_Dispatch1=' . $vehicleDispatchId . '&Camp_Vehicle_Dispatch.Collection_Camp=' . $collectionCampId . '&id=' . $vehicleDispatchId . '&Eck_Collection_Camp1=' . $collectionCampId;
    $html = "
    <p>Dear MMT team,</p>
    <p>This is to inform you that a vehicle has been sent from camp <strong>$campCode</strong> at <strong>$campAddress</strong>.</p>
    <p>Kindly acknowledge the details by clicking on this form <a href=\"$materialdispatchUrl\"> Link </a> when it is received at the center.</p>
    <p>Warm regards,<br>Urban Relations Team</p>";

    return $html;
  }

  /**
   *
   */
  private static function findOfficeId(array $array) {
    $filteredItems = array_filter($array, fn($item) => $item['entity_table'] === 'civicrm_eck_collection_source_vehicle_dispatch');

    if (empty($filteredItems)) {
      return FALSE;
    }

    $goonjOfficeId = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', 'Camp_Vehicle_Dispatch')
      ->addWhere('name', '=', 'To_which_PU_Center_material_is_being_sent')
      ->execute()
      ->first();

    if (!$goonjOfficeId) {
      return FALSE;
    }

    $goonjOfficeFieldId = $goonjOfficeId['id'];

    $goonjOfficeIndex = array_search(TRUE, array_map(fn($item) =>
        $item['entity_table'] === 'civicrm_eck_collection_source_vehicle_dispatch' &&
        $item['custom_field_id'] == $goonjOfficeFieldId,
        $filteredItems
    ));

    return $goonjOfficeIndex !== FALSE ? $filteredItems[$goonjOfficeIndex] : FALSE;
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
  public static function createActivityForCollectionCamp(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName != 'Eck_Collection_Camp') {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';

    if (!$newStatus || !$objectId) {
      return;
    }

    $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_Core_Details.Contact_Id', 'title')
      ->addWhere('id', '=', $objectId)
      ->execute()->single();

    $currentStatus = $collectionCamp['Collection_Camp_Core_Details.Status'];

    if ($currentStatus === $newStatus || $newStatus !== 'authorized') {
      return;
    }

    // Check for status change.
    // Access the id within the decoded data.
    $campId = $objectRef['id'];

    if ($campId === NULL) {
      return;
    }

    $activities = $objectRef['Collection_Camp_Intent_Details.Here_are_some_activities_to_pick_from_but_feel_free_to_invent_yo'];
    $startDate = $objectRef['Collection_Camp_Intent_Details.Start_Date'];
    $endDate = $objectRef['Collection_Camp_Intent_Details.End_Date'];
    $initiator = $objectRef['Collection_Camp_Core_Details.Contact_Id'];

    foreach ($activities as $activityName) {
      // Check if the activity is 'Others'.
      if ($activityName == 'Others') {
        $otherActivity = $objectRef['Collection_Camp_Intent_Details.Other_activity'] ?? '';
        if ($otherActivity) {
          // Use the 'Other_activity' field as the title.
          $activityName = $otherActivity;
        }
        else {
          continue;
        }
      }

      $optionValue = OptionValue::get(TRUE)
        ->addSelect('value')
        ->addWhere('option_group_id:name', '=', 'eck_sub_types')
        ->addWhere('grouping', '=', 'Collection_Camp_Activity')
        ->addWhere('name', '=', 'Collection_Camp')
        ->execute()->single();

      $results = EckEntity::create('Collection_Camp_Activity', TRUE)
        ->addValue('title', $activityName)
        ->addValue('subtype', $optionValue['value'])
        ->addValue('Collection_Camp_Activity.Collection_Camp_Id', $campId)
        ->addValue('Collection_Camp_Activity.Start_Date', $startDate)
        ->addValue('Collection_Camp_Activity.End_Date', $endDate)
        ->addValue('Collection_Camp_Activity.Organizing_Person', $initiator)
        ->execute();

    }
  }

  /**
   *
   */
  public static function sendLogisticsEmail($collectionCamp) {
    try {
      $campId = $collectionCamp['id'];
      $campCode = $collectionCamp['title'];
      $campOffice = $collectionCamp['Collection_Camp_Intent_Details.Goonj_Office'];
      $campAddress = $collectionCamp['Collection_Camp_Intent_Details.Location_Area_of_camp'];
      $campAttendedById = $collectionCamp['Logistics_Coordination.Camp_to_be_attended_by'];
      $logisticEmailSent = $collectionCamp['Logistics_Coordination.Email_Sent'];

      $startDate = new \DateTime($collectionCamp['Collection_Camp_Intent_Details.Start_Date']);

      $today = new \DateTimeImmutable();
      $endOfToday = $today->setTime(23, 59, 59);

      if (!$logisticEmailSent && $startDate <= $endOfToday) {
        $campAttendedBy = Contact::get(FALSE)
          ->addSelect('email.email', 'display_name')
          ->addJoin('Email AS email', 'LEFT')
          ->addWhere('id', '=', $campAttendedById)
          ->execute()->single();

        $attendeeEmail = $campAttendedBy['email.email'];
        $attendeeName = $campAttendedBy['display_name'];

        if (!$attendeeEmail) {
          throw new \Exception('Attendee email missing');
        }

        $mailParams = [
          'subject' => 'Collection Camp Notification: ' . $campCode . ' at ' . $campAddress,
          'from' => self::getFromAddress(),
          'toEmail' => $attendeeEmail,
          'replyTo' => self::getFromAddress(),
          'html' => self::getLogisticsEmailHtml($attendeeName, $campId, $campAttendedById, $campOffice, $campCode, $campAddress),
        ];

        $emailSendResult = \CRM_Utils_Mail::send($mailParams);

        if ($emailSendResult) {
          \Civi::log()->info("Logistics email sent for collection camp: $campId");
          EckEntity::update('Collection_Camp', FALSE)
            ->addValue('Logistics_Coordination.Email_Sent', 1)
            ->addWhere('id', '=', $campId)
            ->execute();
        }
      }
    }
    catch (\Exception $e) {
      \Civi::log()->error("Error in sendLogisticsEmail for $campId " . $e->getMessage());
    }

  }

  /**
   *
   */
  private static function getLogisticsEmailHtml($contactName, $collectionCampId, $campAttendedById, $collectionCampGoonjOffice, $campCode, $campAddress) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    // Construct the full URLs for the forms.
    $campVehicleDispatchFormUrl = $homeUrl . 'camp-vehicle-dispatch-form/#?Camp_Vehicle_Dispatch.Collection_Camp=' . $collectionCampId . '&Camp_Vehicle_Dispatch.Filled_by=' . $campAttendedById . '&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent=' . $collectionCampGoonjOffice . '&Eck_Collection_Camp1=' . $collectionCampId;

    $campOutcomeFormUrl = $homeUrl . '/camp-outcome-form/#?Eck_Collection_Camp1=' . $collectionCampId . '&Camp_Outcome.Filled_By=' . $campAttendedById;

    $html = "
    <p>Dear $contactName,</p>
    <p>Thank you for attending the camp <strong>$campCode</strong> at <strong>$campAddress</strong>. There are two forms that require your attention during and after the camp:</p>
    <ol>
        <li><a href=\"$campVehicleDispatchFormUrl\">Dispatch Form</a><br>
        Please complete this form from the camp location once the vehicle is being loaded and ready for dispatch to the Goonj's processing center.</li>
        <li><a href=\"$campOutcomeFormUrl\">Camp Outcome Form</a><br>
        This feedback form should be filled out after the camp/drive ends, once you have an overview of the event's outcomes.</li>
    </ol>
    <p>We appreciate your cooperation.</p>
    <p>Warm Regards,<br>Urban Relations Team</p>";

    return $html;
  }

  /**
   *
   */
  private static function getFromAddress() {
    if (!self::$fromAddress) {
      [$defaultFromName, $defaultFromEmail] = \CRM_Core_BAO_Domain::getNameAndEmail();
      self::$fromAddress = "\"$defaultFromName\" <$defaultFromEmail>";
    }
    return self::$fromAddress;
  }

  /**
   *
   */
  public static function updateContributorCount($collectionCamp) {
    $activities = Activity::get(FALSE)
      ->addSelect('id')
      ->addWhere('Material_Contribution.Collection_Camp', '=', $collectionCamp['id'])
      ->execute();

    $contributorCount = count($activities);

    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Camp_Outcome.Number_of_Contributors', $contributorCount)
      ->addWhere('id', '=', $collectionCamp['id'])
      ->execute();
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
  public static function updateCampStatusAfterAuth(string $op, string $objectName, $objectId, &$objectRef) {
    $statusDetails = self::checkCampStatusAndIds($objectName, $objectId, $objectRef);

    if (!$statusDetails) {
      return;
    }

    $newStatus = $statusDetails['newStatus'];
    $currentStatus = $statusDetails['currentStatus'];

    if ($currentStatus !== $newStatus) {
      if ($newStatus === 'authorized') {
        $campId = $objectRef['id'] ?? NULL;
        if ($campId === NULL) {
          return;
        }

        $results = EckEntity::update('Collection_Camp', TRUE)
          ->addValue('Collection_Camp_Intent_Details.Camp_Status', 'planned')
          ->addWhere('id', '=', $campId)
          ->execute();
      }
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
  public static function updateCampStatusOnOutcomeFilled(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($objectName !== 'AfformSubmission') {
      return;
    }

    $afformName = $objectRef->afform_name;

    if ($afformName !== 'afformCampOutcomeForm') {
      return;
    }

    $jsonData = $objectRef->data;
    $dataArray = json_decode($jsonData, TRUE);

    $collectionCampId = $dataArray['Eck_Collection_Camp1'][0]['fields']['id'];

    if (!$collectionCampId) {
      return;
    }

    try {
      EckEntity::update('Collection_Camp', FALSE)
        ->addWhere('id', '=', $collectionCampId)
        ->addValue('Collection_Camp_Intent_Details.Camp_Status', 'completed')
        ->execute();

    }
    catch (\Exception $e) {
      \Civi::log()->error("Exception occurred while updating camp status for campId: $collectionCampId. Error: " . $e->getMessage());
    }
  }

  /**
   * Check the status of a Collection Camp and return status details.
   *
   * @param string $objectName
   *   The name of the object being processed.
   * @param int $objectId
   *   The ID of the object being processed.
   * @param array &$objectRef
   *   A reference to the object data.
   *
   * @return array|null
   *   An array containing the new and current status if valid, or NULL if invalid.
   */
  public static function checkCampStatusAndIds(string $objectName, $objectId, &$objectRef) {
    if ($objectName != 'Eck_Collection_Camp') {
      return NULL;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';

    if (!$newStatus || !$objectId) {
      return NULL;
    }

    $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Status')
      ->addWhere('id', '=', $objectId)
      ->execute()->single();

    $currentStatus = $collectionCamp['Collection_Camp_Core_Details.Status'] ?? '';

    return [
      'newStatus' => $newStatus,
      'currentStatus' => $currentStatus,
    ];
  }

  /**
   * Implements hook_civicrm_buildForm().
   *
   * Auto-fills custom fields in the form based on the provided parameters.
   *
   * @param string $formName
   *   The name of the form being built.
   * @param object $form
   *   The form object.
   */
  public function autofillMonetaryFormSource($formName, &$form) {
    $campSource = NULL;
    $puSource = NULL;

    // Fetching custom field for collection source.
    $sourceField = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
      ->addWhere('name', '=', 'Source')
      ->execute()->single();

    $sourceFieldId = 'custom_' . $sourceField['id'] . '_-1';

    // Fetching custom field for goonj offfice.
    $puSourceField = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
      ->addWhere('name', '=', 'PU_Source')
      ->execute()->single();

    $puSourceFieldId = 'custom_' . $puSourceField['id'] . '_-1';

    // Determine the parameter to use based on the form and query parameters.
    if ($formName === 'CRM_Contribute_Form_Contribution') {
      if (isset($_GET[$sourceFieldId])) {
        $campSource = $_GET[$sourceFieldId];
        $_SESSION['camp_source'] = $campSource;
        // Ensure only one session value is active.
        unset($_SESSION['pu_source']);
      }
      elseif (isset($_GET[$puSourceFieldId])) {
        $puSource = $_GET[$puSourceFieldId];
        $_SESSION['pu_source'] = $puSource;
        // Ensure only one session value is active.
        unset($_SESSION['camp_source']);
      }
    }
    else {
      // Retrieve from session if not provided in query parameters.
      $campSource = $_SESSION['camp_source'] ?? NULL;
      $puSource = $_SESSION['pu_source'] ?? NULL;
    }

    // Autofill logic for the custom fields.
    if ($formName === 'CRM_Custom_Form_CustomDataByType') {
      $autoFillData = [];
      if (!empty($campSource)) {
        $autoFillData[$sourceFieldId] = $campSource;
      }
      elseif (!empty($puSource)) {
        $autoFillData[$puSourceFieldId] = $puSource;
      }

      // Set default values for the specified fields.
      foreach ($autoFillData as $fieldName => $value) {
        if (isset($form->_elements) && is_array($form->_elements)) {
          foreach ($form->_elements as $element) {
            if (isset($element->_attributes['name']) && $element->_attributes['name'] === $fieldName) {
              $form->setDefaults([$fieldName => $value]);
            }
          }
        }
      }
    }
  }

}
