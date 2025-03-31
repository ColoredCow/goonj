<?php

namespace Civi;

require_once __DIR__ . '/../../../../wp-content/civi-extensions/goonjcustom/vendor/autoload.php';

use Civi\Afform\Event\AfformSubmitEvent;
use Civi\Api4\Activity;
use Civi\Api4\Address;
use Civi\Api4\Contact;
use Civi\Api4\Contribution;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Email;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Api4\OptionValue;
use Civi\Api4\Relationship;
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
  const COLLECTION_CAMP_INTENT_FB_NAME = [
    'afformAdminCollectionCampIntentDetails',
    'afformCollectionCampIntentDetails'
  ];
  const ENTITY_NAME = 'Collection_Camp';
  const ENTITY_SUBTYPE_NAME = 'Collection_Camp';
  const MATERIAL_RELATIONSHIP_TYPE_NAME = 'Material Management Team of';
  const DEFAULT_FINANCIAL_TYPE_ID = 1;
  const ACCOUNTS_TEAM_EMAIL = '"Goonj Accounts Team" <accounts@goonj.org>';

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
      ['updateCampaignForCollectionSourceContribution'],
      ['generateInvoiceIdForContribution'],
      ['generateInvoiceNumber'],
      ],
      '&hook_civicrm_pre' => [
        ['generateCollectionCampQr'],
        ['linkCollectionCampToContact'],
        ['createActivityForCollectionCamp'],
        ['updateCampStatusAfterAuth'],
      ],
      '&hook_civicrm_custom' => [
      ['setOfficeDetails'],
      ['linkInductionWithCollectionCamp'],
      ['mailNotificationToMmt'],
      ],
      'civi.afform.submit' => [
      ['setCollectionCampAddress', 9],
      ['setEventVolunteersAddress', 8],
      ],
      '&hook_civicrm_tabset' => 'collectionCampTabset',
      '&hook_civicrm_buildForm' => [
      ['autofillMonetaryFormSource'],
      ['autofillFinancialType'],
      ['autofillReceiptFrom'],
      ],
      '&hook_civicrm_alterMailParams' => [
      ['alterReceiptMail'],
      ['handleOfflineReceipt'],
      ],
      '&hook_civicrm_validateForm' => 'validateCheckNumber',

    ];
  }

  /**
   *
   */
  public static function collectionCampTabset($tabsetName, &$tabs, $context) {
    if (!self::isViewingCollectionCamp($tabsetName, $context)) {
      return;
    }

    $restrictedRoles = ['account_team', 'ho_account', 'mmt'];

    $isAdmin = \CRM_Core_Permission::check('admin');

    $hasRestrictedRole = !$isAdmin && \CRM_Core_Permission::checkAnyPerm($restrictedRoles);

    if ($hasRestrictedRole) {
      unset($tabs['view']);
      unset($tabs['edit']);
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
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin'],
      ],
      'eventVolunteers' => [
        'title' => ts('Event Volunteers'),
        'module' => 'afsearchEventVolunteer',
        'directive' => 'afsearch-event-volunteer',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin'],
      ],
      'vehicleDispatch' => [
        'title' => ts('Dispatch'),
        'module' => 'afsearchCampVehicleDispatchData',
        'directive' => 'afsearch-camp-vehicle-dispatch-data',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin'],
      ],
      'materialAuthorization' => [
        'title' => ts('Material Authorization'),
        'module' => 'afsearchAcknowledgementForLogisticsData',
        'directive' => 'afsearch-acknowledgement-for-logistics-data',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin'],
      ],
      'materialContribution' => [
        'title' => ts('Material Contribution'),
        'module' => 'afsearchCollectionCampMaterialContributions',
        'directive' => 'afsearch-collection-camp-material-contributions',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'mmt', 'urban_ops_admin'],
      ],
      'campOutcome' => [
        'title' => ts('Camp Outcome'),
        'module' => 'afsearchCampOutcome',
        'directive' => 'afsearch-camp-outcome',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin'],
      ],
      'campFeedback' => [
        'title' => ts('Volunteer Feedback'),
        'module' => 'afsearchVolunteerFeedback',
        'directive' => 'afsearch-volunteer-feedback',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin'],
      ],
      'monetaryContribution' => [
        'title' => ts('Monetary Contribution'),
        'module' => 'afsearchMonetaryContribution',
        'directive' => 'afsearch-monetary-contribution',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['account_team', 'ho_account'],
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
    if (!in_array($formName, self::COLLECTION_CAMP_INTENT_FB_NAME)) {
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

    if (!in_array($formName, self::COLLECTION_CAMP_INTENT_FB_NAME)) {
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

    if (!$objectRef->state_province_id) {
      return FALSE;
    }

    $groupId = self::getChapterGroupForState($objectRef->state_province_id);
    // Check if already assigned to group chapter
		$groupContacts = GroupContact::get(FALSE)
			->addWhere('contact_id', '=', self::$individualId)
			->addWhere('group_id', '=', $groupId)
			->execute()->first();

		if (!empty($groupContacts)) {
			return;
		}

    if ($groupId & self::$individualId) {
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

      $groupContacts = GroupContact::get(FALSE)
        ->addWhere('contact_id', '=', self::$individualId)
        ->addWhere('group_id', '=', $groupId)
        ->execute()->first();

      if (!empty($groupContacts)) {
        return;
      }

      if ($groupId & self::$individualId) {
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
  public static function linkCollectionCampToContact(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName != 'Eck_Collection_Camp' || !$objectId || !self::isCurrentSubtype($objectRef)) {
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
    if (!$contactId) {
      return;
    }
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
      $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
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
    if ($op !== 'create' || self::getEntitySubtypeName($entityID) !== self::ENTITY_SUBTYPE_NAME) {
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
    if ($objectName != 'Eck_Collection_Camp' || self::getEntitySubtypeName($objectId) !== self::ENTITY_SUBTYPE_NAME) {
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
  public static function getFromAddress() {
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
   *
   */
  public static function updateContributionCount($collectionCamp) {
    $contributions = Contribution::get(FALSE)
      ->addSelect('total_amount')
      ->addWhere('Contribution_Details.Source', '=', $collectionCamp['id'])
      ->addWhere('is_test', 'IS NOT NULL')
      ->execute();

    // Initialize sum variable.
    $totalSum = 0;

    // Iterate through the results and sum the total_amount.
    foreach ($contributions as $contribution) {
      $totalSum += $contribution['total_amount'];
    }

    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Camp_Outcome.Monitory_Contribution', $totalSum)
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
    if (!in_array($formName, ['CRM_Contribute_Form_Contribution', 'CRM_Custom_Form_CustomDataByType'])) {
      return;
    }

    $campSource = NULL;
    $puSource = NULL;

    // Fetching custom field for collection source.
    $sourceField = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
      ->addWhere('name', '=', 'Source')
      ->execute()->single();

    $sourceFieldId = 'custom_' . $sourceField['id'];

    // Fetching custom field for goonj office.
    $puSourceField = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
      ->addWhere('name', '=', 'PU_Source')
      ->execute()->single();

    $puSourceFieldId = 'custom_' . $puSourceField['id'];

    $eventSourceField = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
      ->addWhere('name', '=', 'Events')
      ->execute()->single();

    $eventSourceFieldId = 'custom_' . $eventSourceField['id'];

    // Determine the parameter to use based on the form and query parameters.
    if ($formName === 'CRM_Contribute_Form_Contribution') {
      // If the query parameter is present, update session and clear the other session value.
      if (isset($_GET[$sourceFieldId])) {
        $campSource = $_GET[$sourceFieldId];
        $_SESSION['camp_source'] = $campSource;
        // Ensure only one session value is active.
        unset($_SESSION['pu_source']);
        unset($_SESSION['eventSource']);
      }
      elseif (isset($_GET[$puSourceFieldId])) {
        $puSource = $_GET[$puSourceFieldId];
        $_SESSION['pu_source'] = $puSource;
        // Ensure only one session value is active.
        unset($_SESSION['camp_source']);
        unset($_SESSION['eventSource']);
      }
      elseif (isset($_GET[$eventSourceFieldId])) {
        $eventSource = $_GET[$eventSourceFieldId];
        $_SESSION['eventSource'] = $eventSource;
        // Ensure only one session value is active.
        unset($_SESSION['camp_source']);
        unset($_SESSION['pu_source']);
      }
      else {
        // Clear session if neither parameter is present.
        unset($_SESSION['camp_source'], $_SESSION['pu_source'], $_SESSION['eventSource']);
      }
    }
    else {
      // For other forms, retrieve from session if it exists.
      $campSource = $_SESSION['camp_source'] ?? NULL;
      $puSource = $_SESSION['pu_source'] ?? NULL;
      $eventSource = $_SESSION['eventSource'] ?? NULL;
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
      elseif (!empty($eventSource)) {
        $autoFillData[$eventSourceFieldId] = $eventSource;
      }
      else {
        // Clear values explicitly if neither source is found.
        $autoFillData[$sourceFieldId] = NULL;
        $autoFillData[$puSourceFieldId] = NULL;
        $autoFillData[$eventSourceFieldId] = NULL;
      }

      // Set default values for the specified fields.
      foreach ($autoFillData as $fieldName => $value) {
        if (isset($form->_elements) && is_array($form->_elements)) {
          foreach ($form->_elements as $element) {
            if (!isset($element->_attributes['data-api-params'])) {
              continue;
            }
            $apiParams = json_decode($element->_attributes['data-api-params'], TRUE);
            if ($apiParams['fieldName'] === 'Contribution.Contribution_Details.Source') {
              $formFieldName = $fieldName . '_-1';
              $form->setDefaults([$formFieldName => $value ?? '']);
            }
          }
        }
      }
    }
  }

  /**
   *
   */
  public static function alterReceiptMail(&$params, $context) {
    // Handle contribution_online_receipt workflow.
    if (!empty($params['workflow']) && $params['workflow'] === 'contribution_online_receipt') {
      error_log("checking: " . print_r($params, TRUE));
      $params['toName'] = '';
      $params['toEmail'] = '';
      // Extract donor name or use a default value.
      // $donorName = !empty($params['tplParams']['displayName']) ? $params['tplParams']['displayName'] : 'Valued Supporter';
      // $contributionID = !empty($params['tplParams']['contributionID']) ? $params['tplParams']['contributionID'] : NULL;
      // $params['cc'] = 'priyanka@goonj.org';

      // $contribution = Contribution::get(FALSE)
      //   ->addSelect('invoice_number')
      //   ->addWhere('id', '=', $contributionID)
      //   ->execute()->single();

      // $receiptNumber = $contribution['invoice_number'];

      // // Check if title is 'Team 5000'.
      // if (!empty($params['tplParams']['title']) && $params['tplParams']['title'] === 'Team 5000') {
      //   $params['text'] = "Dear $donorName,\n\nThank you for the contribution and coming on-board of Team 5000.\n\nBy joining https://goonj.org/donate/micro-team-5000 you step into this legacy of grassroots action and become an integral part of our extended family. This isn’t just about giving; it’s about becoming a vital contributor to a movement that empowers communities and amplifies the voices of the marginalised. Your committed cooperation and continued engagement fuel our sustained efforts.\n\nThe receipt No. ($receiptNumber) for the same is enclosed with the details of 80G exemptions and our PAN No.\n\nFor a regular update on our activities and new campaigns please keep an eye on www.goonj.org and our FB page https://www.facebook.com/goonj.org, which are regularly updated.\n\nThank you once again for joining the journey..\n\nRegards,\nPriyanka";

      //   $params['html'] = "
      //         <p>Dear <strong>$donorName</strong>,</p>
      //         <p>Thank you for the contribution and coming on-board of Team 5000.</p>
      //         <p>By joining <a href='https://goonj.org/donate/micro-team-5000'>https://goonj.org/donate/micro-team-5000</a> you step into this legacy of grassroots action and become an integral part of our extended family. This isn’t just about giving; it’s about becoming a vital contributor to a movement that empowers communities and amplifies the voices of the marginalised. Your committed cooperation and continued engagement fuel our sustained efforts.</p>
      //         <p>The receipt No. (<strong>$receiptNumber</strong>) for the same is enclosed with the details of 80G exemptions and our PAN No.</p>
      //         <p>For a regular update on our activities and new campaigns please keep an eye on <a href='https://www.goonj.org'>www.goonj.org</a> and our FB page <a href='https://www.facebook.com/goonj.org'>https://www.facebook.com/goonj.org</a>, which are regularly updated.</p>
      //         <p>Thank you once again for joining the journey..</p>
      //         <p>Regards,<br>Priyanka</p>
      //     ";
      // }
      // else {
      //   $params['text'] = "Dear $donorName,\n\nThank you for your contribution.\n\nThese contributions go a long way in sustaining our operations and implementing series of initiatives all across.\nThe receipt No. ($receiptNumber) for the same is enclosed with the details of 80G exemptions and our PAN No.\n\nFor updates on our activities and new campaigns, please visit our website www.goonj.org and our FB page https://www.facebook.com/goonj.org, which are regularly updated.\n\nThank you once again for joining the journey.\n\nWith best regards,\nTeam Goonj";

      //   $params['html'] = "
      //         <p>Dear <strong>$donorName</strong>,</p>
      //         <p>Thank you for your contribution.</p>
      //         <p>These contributions go a long way in sustaining our operations and implementing series of initiatives all across.</p>
      //         <p>The receipt No. (<strong>$receiptNumber</strong>) for the same is enclosed with the details of 80G exemptions and our PAN No.</p>
      //         <p>For updates on our activities and new campaigns, please visit our website <a href='https://www.goonj.org'>www.goonj.org</a> and our FB page <a href='https://www.facebook.com/goonj.org'>https://www.facebook.com/goonj.org</a>, which are regularly updated.</p>
      //         <p>Thank you once again for joining the journey.</p>
      //         <p>With best regards,<br>Team Goonj</p>
      //     ";
      // }
    }
  }

  /**
   *
   */
  public static function handleOfflineReceipt(&$params, $context) {
    if (!empty($params['workflow']) && $params['workflow'] === 'contribution_offline_receipt') {
      // Extract donor name or use a default value.
      $donorName = !empty($params['toName']) ? $params['toName'] : 'Valued Supporter';
      $contributionID = !empty($params['contributionId']) ? $params['contributionId'] : NULL;
      $params['cc'] = 'priyanka@goonj.org';

      $contribution = Contribution::get(FALSE)
        ->addSelect('invoice_number', 'contribution_page_id:label')
        ->addWhere('id', '=', $contributionID)
        ->execute()->single();

      $receiptNumber = $contribution['invoice_number'];
      $contributionName = $contribution['contribution_page_id:label'];

      // Check if title is 'Team 5000'.
      if ($contributionName === 'Team 5000') {
        $params['text'] = "Dear $donorName,\n\nThank you for the contribution and coming on-board of Team 5000.\n\nBy joining https://goonj.org/donate/micro-team-5000 you step into this legacy of grassroots action and become an integral part of our extended family. This isn’t just about giving; it’s about becoming a vital contributor to a movement that empowers communities and amplifies the voices of the marginalised. Your committed cooperation and continued engagement fuel our sustained efforts.\n\nThe receipt No. ($receiptNumber) for the same is enclosed with the details of 80G exemptions and our PAN No.\n\nFor a regular update on our activities and new campaigns, please keep an eye on www.goonj.org and our FB page https://www.facebook.com/goonj.org, which are regularly updated.\n\nThank you once again for joining the journey..\n\nRegards,\nPriyanka";

        $params['html'] = "
              <p>Dear <strong>$donorName</strong>,</p>
              <p>Thank you for the contribution and coming on-board of Team 5000.</p>
              <p>By joining <a href='https://goonj.org/donate/micro-team-5000'>https://goonj.org/donate/micro-team-5000</a>, you step into this legacy of grassroots action and become an integral part of our extended family. This isn’t just about giving; it’s about becoming a vital contributor to a movement that empowers communities and amplifies the voices of the marginalised. Your committed cooperation and continued engagement fuel our sustained efforts.</p>
              <p>The receipt No. (<strong>$receiptNumber</strong>) for the same is enclosed with the details of 80G exemptions and our PAN No.</p>
              <p>For a regular update on our activities and new campaigns, please keep an eye on <a href='https://www.goonj.org'>www.goonj.org</a> and our FB page <a href='https://www.facebook.com/goonj.org'>https://www.facebook.com/goonj.org</a>, which are regularly updated.</p>
              <p>Thank you once again for joining the journey.</p>
              <p>Regards,<br>Priyanka</p>
       ";
      }
      else {
        $params['text'] = "Dear $donorName,\n\nThank you for your contribution.\n\nThese contributions go a long way in sustaining our operations and implementing series of initiatives all across.\nThe receipt No. ($receiptNumber) for the same is enclosed with the details of 80G exemptions and our PAN No.\n\nFor updates on our activities and new campaigns, please visit our website www.goonj.org and our FB page https://www.facebook.com/goonj.org, which are regularly updated.\n\nThank you once again for joining the journey.\n\nWith best regards,\nTeam Goonj";

        $params['html'] = "
              <p>Dear <strong>$donorName</strong>,</p>
              <p>Thank you for your contribution.</p>
              <p>These contributions go a long way in sustaining our operations and implementing series of initiatives all across.</p>
              <p>The receipt No. (<strong>$receiptNumber</strong>) for the same is enclosed with the details of 80G exemptions and our PAN No.</p>
              <p>For updates on our activities and new campaigns, please visit our website <a href='https://www.goonj.org'>www.goonj.org</a> and our FB page <a href='https://www.facebook.com/goonj.org'>https://www.facebook.com/goonj.org</a>, which are regularly updated.</p>
              <p>Thank you once again for joining the journey.</p>
              <p>With best regards,<br>Team Goonj</p>
          ";
      }
    }
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
  public function autofillFinancialType($formName, &$form) {
    if ($formName === 'CRM_Contribute_Form_Contribution') {
      if ($form->getAction() == \CRM_Core_Action::ADD) {
        // Set the default value for 'financial_type_id'.
        $defaults = [];
        // Example: 'Donation' (adjust ID as per your requirement)
        $defaults['financial_type_id'] = self::DEFAULT_FINANCIAL_TYPE_ID;
        $form->setDefaults($defaults);
      }
    }
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
  public function autofillReceiptFrom($formName, &$form) {
    // Check if the form is the Contribution form.
    if ($formName === 'CRM_Contribute_Form_Contribution') {
      if ($form->getAction() == \CRM_Core_Action::ADD) {
        // Set the default value for 'Receipt From'.
        $defaults = [];
        $defaults['from_email_address'] = self::ACCOUNTS_TEAM_EMAIL;
        $form->setDefaults($defaults);
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
  public static function updateCampaignForCollectionSourceContribution(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($objectName !== 'Contribution' || !$objectRef->id || $op !== 'edit') {
      return;
    }

    try {
      $contributionId = $objectRef->id;
      if (!$contributionId) {
        return;
      }

      $contribution = Contribution::get(FALSE)
        ->addSelect('Contribution_Details.Source', 'campaign_id')
        ->addWhere('id', '=', $contributionId)
        ->execute()->first();

      if (!$contribution) {
        return;
      }

      $sourceID = $contribution['Contribution_Details.Source'];
      $contributionCampaignId = $contribution['campaign_id'];

      $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
        ->addSelect('Collection_Camp_Intent_Details.Campaign')
        ->addWhere('id', '=', $sourceID)
        ->execute()->single();

      if (!$collectionCamp) {
        return;
      }

      $campaignId = $collectionCamp['Collection_Camp_Intent_Details.Campaign'];

      if (!$campaignId) {
        return;
      }

      if ($contributionCampaignId) {
        return;
      }

      Contribution::update(FALSE)
        ->addValue('campaign_id', $campaignId)
        ->addWhere('id', '=', $contributionId)
        ->execute();

    }

    catch (\Exception $e) {
      \Civi::log()->error("Exception occurred in updateCampaignForCollectionSourceContribution.", [
        'Message' => $e->getMessage(),
        'Stack Trace' => $e->getTraceAsString(),
      ]);
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
  public static function generateInvoiceIdForContribution(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($objectName !== 'Contribution' || !$objectRef->id) {
      return;
    }

    try {
      $contributionId = $objectRef->id;
      if (!$contributionId) {
        return;
      }

      if (!empty($objectRef->invoice_id)) {
        return;
      }

      // Generate a unique invoice ID.
      // Current timestamp.
      $timestamp = time();
      // Generate a unique ID based on the current time in microseconds.
      $uniqueId = uniqid();
      $invoiceId = hash('sha256', $timestamp . $uniqueId);

      Contribution::update(FALSE)
        ->addValue('invoice_id', $invoiceId)
        ->addWhere('id', '=', $contributionId)
        ->execute();

    }
    catch (\Exception $e) {
      \Civi::log()->error("Exception occurred in generateInvoiceIdForContribution.", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
    }
  }

  /**
   * Implements hook_civicrm_validateForm().
   *
   * @param string $formName
   * @param array $fields
   * @param array $files
   * @param CRM_Core_Form $form
   * @param array $errors
   */
  public function validateCheckNumber($formName, &$fields, &$files, &$form, &$errors) {
    if ($formName == 'CRM_Contribute_Form_Contribution') {
      if (isset($fields['payment_instrument_id']) && $fields['payment_instrument_id'] == 4) {
        if (empty($fields['check_number'])) {
          $message = ts('Please provide a cheque number.');
          $form->setElementError('check_number', NULL);
          $errors['check_number'] = $message;
          $form->setElementError('check_number', $message);

          \CRM_Core_Resources::singleton()->addScript("
                    (function($) {
                        function ensureErrorVisible() {
                            var errorField = $('#check_number-error');
                            var inputField = $('#check_number');
                            $('.crm-error').hide();
                            if (!errorField.length) {
                                inputField.after('<div id=\"check_number-error\" class=\"crm-error\">' + " . json_encode($message) . " + '</div>');
                            } else {
                                errorField.show();
                            }
                        }

                        $(document).ajaxComplete(ensureErrorVisible);
                        $(document).ready(ensureErrorVisible);
                    })(CRM.$);
                ");
        }
      }
    }
  }

  /**
   *
   */
  public static function generateInvoiceNumber(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($objectName !== 'Contribution' || !$objectRef->id) {
      return;
    }

    try {
      $contributionId = $objectRef->id;
      if (!$contributionId) {
        return;
      }

      $contribution = Contribution::get(FALSE)
        ->addSelect('contribution_status_id:name', 'invoice_number')
        ->addWhere('id', '=', $contributionId)
        ->execute()->first();

      if (!$contribution) {
        return;
      }

      $contributionStatus = $contribution['contribution_status_id:name'];
      $existingInvoiceNumber = $contribution['invoice_number'];

      if ($contributionStatus !== 'Completed' || !empty($existingInvoiceNumber)) {
        return;
      }

      $contributions = Contribution::get(FALSE)
        ->addSelect('invoice_number')
        ->addClause('OR', ['is_test', '=', TRUE], ['is_test', '=', FALSE])
        ->addWhere('contribution_status_id:label', '=', 'Completed')
        ->addOrderBy('id', 'DESC')
        ->execute();

      $invoiceNumber = NULL;
      // Loop through contributions to find the first non-empty invoice_number.
      foreach ($contributions as $contribution) {
        if (!empty($contribution['invoice_number'])) {
          $invoiceNumber = $contribution['invoice_number'];
          // Stop after finding the first valid invoice number.
          break;
        }
      }

      if (!$invoiceNumber) {
        return;
      }

      // Extract number from invoice number.
      preg_match('/(\d+)$/', $invoiceNumber, $matches);
      $numberOnly = $matches[1] ?? NULL;

      if ($numberOnly === NULL) {
        return;
      }

      // Increment the number.
      $increaseNumber = (int) $numberOnly + 1;
      $invoicePrefix = 'GNJCRM/25-26/';
      $newInvoiceNumber = $invoicePrefix . $increaseNumber;

      // Update contribution with new invoice number.
      Contribution::update(FALSE)
        ->addValue('invoice_number', $newInvoiceNumber)
        ->addWhere('id', '=', $contributionId)
        ->execute();

    }
    catch (\Exception $e) {
      \Civi::log()->error("Exception occurred in generateInvoiceNumber.", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
    }
  }

}
