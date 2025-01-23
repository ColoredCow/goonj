<?php

namespace Civi;

use Civi\Afform\Event\AfformSubmitEvent;
use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Email;
use Civi\Api4\Relationship;
use Civi\Api4\Utils\CoreUtil;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\CollectionSource;
use Civi\Traits\QrCodeable;

/**
 *
 */
class DroppingCenterService extends AutoSubscriber {
  use QrCodeable;
  use CollectionSource;

  const ENTITY_NAME = 'Collection_Camp';
  const RELATIONSHIP_TYPE_NAME = 'Dropping Center Coordinator of';
  const ENTITY_SUBTYPE_NAME = 'Dropping_Center';
  const MATERIAL_RELATIONSHIP_TYPE_NAME = 'Material Management Team of';
  const FALLBACK_OFFICE_NAME = 'Delhi';
  const DROPPING_CENTER_INTENT_FB_NAMES = [
    'afformDroppingCenterDetailForm',
    'afformAdminDroppingCenterDetailForm',
  ];
  private static $droppingCentreAddress = NULL;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_tabset' => 'droppingCenterTabset',
      'civi.afform.submit' => [
        ['setDroppingCenterAddress', 9],
        ['setEventVolunteersAddress', 8],
      ],
      '&hook_civicrm_pre' => [
        ['generateDroppingCenterQr'],
        ['linkDroppingCenterToContact'],
      ],
      '&hook_civicrm_custom' => [
        ['setOfficeDetails'],
        ['mailNotificationToMmt'],
      ],
      '&hook_civicrm_pre' => 'processDispatchEmail',
    ];
  }

  /**
   *
   */
  public static function setDroppingCenterAddress(AfformSubmitEvent $event) {
    $afform = $event->getAfform();
    $formName = $afform['name'];

    if (!in_array($formName, self::DROPPING_CENTER_INTENT_FB_NAMES, TRUE)) {
      return;
    }
    $entityType = $event->getEntityType();

    if ($entityType !== 'Eck_Collection_Camp') {
      return;
    }

    $records = $event->records;

    foreach ($records as $record) {
      $fields = $record['fields'];

      self::$droppingCentreAddress = [
        'location_type_id' => 3,
        'state_province_id' => $fields['Dropping_Centre.State'],
        'country_id' => 1101,
        'street_address' => $fields['Dropping_Centre.Where_do_you_wish_to_open_dropping_center_Address_'],
        'city' => $fields['Dropping_Centre.District_City'],
        'postal_code' => $fields['Dropping_Centre.Postal_Code'],
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

    if (!in_array($formName, self::DROPPING_CENTER_INTENT_FB_NAMES, TRUE)) {
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

      $event->records[$index]['joins']['Address'][] = self::$droppingCentreAddress;
    }

  }

  /**
   *
   */
  public static function linkDroppingCenterToContact(string $op, string $objectName, $objectId, &$objectRef) {
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
    $droppingCenterCode = $currentCollectionCamp['title'];
    $droppingCenterId = $currentCollectionCamp['id'];

    // Check for status change.
    if ($currentStatus !== $newStatus) {
      if ($newStatus === 'authorized') {
        self::createDroppingCenterOrganizeActivity($contactId, $droppingCenterCode, $droppingCenterId);
      }
    }
  }

  /**
   * Log an activity in CiviCRM.
   */
  private static function createDroppingCenterOrganizeActivity($contactId, $droppingCenterCode, $droppingCenterId) {
    try {
      $results = Activity::create(FALSE)
        ->addValue('subject', $droppingCenterCode)
        ->addValue('activity_type_id:name', 'Organize Dropping Center')
        ->addValue('status_id:name', 'Authorized')
        ->addValue('activity_date_time', date('Y-m-d H:i:s'))
        ->addValue('source_contact_id', $contactId)
        ->addValue('target_contact_id', $contactId)
        ->addValue('Collection_Camp_Data.Collection_Camp_ID', $droppingCenterId)
        ->execute();

    }
    catch (\CiviCRM_API4_Exception $ex) {
      \Civi::log()->debug("Exception while creating Organize Dropping Center activity: " . $ex->getMessage());
    }
  }

  /**
   *
   */
  public static function generateDroppingCenterQr(string $op, string $objectName, $objectId, &$objectRef) {
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
    $droppingCenterId = $currentCollectionCamp['id'];

    // Check for status change.
    if ($currentStatus !== $newStatus && $newStatus === 'authorized') {
      self::generateDroppingCenterQrCode($droppingCenterId);
    }
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
  private static function findStateField(array $array) {
    $stateFieldId = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'State')
      ->addWhere('custom_group_id:name', '=', 'Dropping_Centre')
      ->execute()
      ->first()['id'] ?? NULL;

    if (!$stateFieldId) {
      return FALSE;
    }

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
  private static function generateDroppingCenterQrCode($id) {
    $baseUrl = \CRM_Core_Config::singleton()->userFrameworkBaseURL;
    $data = "{$baseUrl}actions/dropping-center/{$id}";

    $saveOptions = [
      'customGroupName' => 'Collection_Camp_QR_Code',
      'customFieldName' => 'QR_Code',
    ];

    self::generateQrCode($data, $id, $saveOptions);
  }

  /**
   *
   */
  private static function findOfficeId(array $array) {

    $filteredItems = array_filter($array, fn($item) => $item['entity_table'] === 'civicrm_eck_collection_source_vehicle_dispatch');
    if (empty($filteredItems)) {
      return FALSE;
    }
    $goonjOfficeField = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', 'Camp_Vehicle_Dispatch')
      ->addWhere('name', '=', 'To_which_PU_Center_material_is_being_sent')
      ->execute()
      ->first();

    if (!$goonjOfficeField) {
      return FALSE;
    }

    $goonjOfficeFieldId = $goonjOfficeField['id'];

    $goonjOfficeIndex = array_search(TRUE, array_map(fn($item) =>
        $item['entity_table'] === 'civicrm_eck_collection_source_vehicle_dispatch' &&
        $item['custom_field_id'] == $goonjOfficeFieldId,
        $filteredItems
    ));

    return $goonjOfficeIndex !== FALSE ? $filteredItems[$goonjOfficeIndex] : FALSE;
  }

  /**
   *
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
      ->addSelect('Camp_Vehicle_Dispatch.Dropping_Center')
      ->addWhere('id', '=', $vehicleDispatchId)
      ->execute()->first();

    $droppingCenterId = $collectionSourceVehicleDispatch['Camp_Vehicle_Dispatch.Dropping_Center'];

    if (self::getEntitySubtypeName($droppingCenterId) !== self::ENTITY_SUBTYPE_NAME) {
      return;
    }

    $droppingCenter = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Dropping_Centre.Where_do_you_wish_to_open_dropping_center_Address_', 'title')
      ->addWhere('id', '=', $droppingCenterId)
      ->execute()->single();

    $droppingCenterCode = $droppingCenter['title'];
    $droppingCenterAddress = $droppingCenter['Dropping_Centre.Where_do_you_wish_to_open_dropping_center_Address_'];

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
    $from = HelperService::getDefaultFromEmail();
    $mailParams = [
      'subject' => 'Dropping Center Material Acknowledgement - ' . $droppingCenterAddress,
      'from' => $from,
      'toEmail' => $mmtEmail,
      'replyTo' => $fromEmail['label'],
      'html' => self::getMmtEmailHtml($droppingCenterId, $droppingCenterCode, $droppingCenterAddress, $vehicleDispatchId, $mmtId),
    ];
    \CRM_Utils_Mail::send($mailParams);

  }

  /**
   *
   */
  public static function getMmtEmailHtml($droppingCenterId, $droppingCenterCode, $droppingCenterAddress, $vehicleDispatchId, $mmtId) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $materialdispatchUrl = $homeUrl . '/acknowledgement-for-dispatch/#?Eck_Collection_Source_Vehicle_Dispatch1=' . $vehicleDispatchId
        . '&Camp_Vehicle_Dispatch.Dropping_Center=' . $droppingCenterId
        . '&id=' . $vehicleDispatchId
        . '&Eck_Collection_Camp1=' . $droppingCenterId
        . '&Acknowledgement_For_Logistics.Verified_By=' . $mmtId;
    $html = "
    <p>Dear MMT team,</p>
    <p>This is to inform you that a vehicle has been sent from the dropping center <strong>$droppingCenterCode</strong> at <strong>$droppingCenterAddress</strong>.</p>
    <p>Kindly acknowledge the details by clicking on this form <a href=\"$materialdispatchUrl\"> Link </a> when it is received at the center.</p>
    <p>Warm regards,<br>Urban Relations Team</p>";

    return $html;
  }

  /**
   *
   */
  public static function setOfficeDetails($op, $groupID, $entityID, &$params) {
    if ($op !== 'create' || self::getEntitySubtypeName($entityID) !== self::ENTITY_SUBTYPE_NAME) {
      return;
    }

    if (!($stateField = self::findStateField($params))) {
      return;
    }

    $stateId = $stateField['value'];

    $droppingCenterId = $stateField['entity_id'];

    $droppingCenter = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Dropping_Centre.Will_your_dropping_center_be_open_for_general_public_as_well_out')
      ->addWhere('id', '=', $droppingCenterId)
      ->execute();

    $collectionCampData = $droppingCenter->first();

    if (!$stateId) {
      \CRM_Core_Error::debug_log_message('Cannot assign Goonj Office to  dropping center: ' . $droppingCenter['id']);
      \CRM_Core_Error::debug_log_message('No state provided on the intent for  dropping center: ' . $droppingCenter['id']);
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
      ->addValue('Dropping_Centre.Goonj_Office', $stateOfficeId)
      ->addWhere('id', '=', $droppingCenterId)
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
      ->addValue('Dropping_Centre.Coordinating_Urban_POC', $coordinatorId)
      ->addWhere('id', '=', $droppingCenterId)
      ->execute();

    return TRUE;

  }

  /**
   *
   */
  public static function processDispatchEmail(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectRef['afform_name'] !== 'afformSendDispatchEmail') {
      return;
    }

    $dataArray = $objectRef['data'];

    $droppingCenterId = $dataArray['Eck_Collection_Camp1'][0]['fields']['id'] ?? NULL;

    if (!$droppingCenterId) {
      return;
    }

    $contactId = $dataArray['Eck_Collection_Camp1'][0]['fields']['Dropping_Centre.Contact_Dispatch_Email'];
    $droppingCenterData = EckEntity::get('Collection_Camp', TRUE)
      ->addSelect('Dropping_Centre.Goonj_Office', 'Dropping_Centre.Goonj_Office.display_name')
      ->addWhere('id', '=', $droppingCenterId)
      ->execute()->single();

    $goonjOffice = $droppingCenterData['Dropping_Centre.Goonj_Office'] ?? 'N/A';
    $goonjOfficeName = $droppingCenterData['Dropping_Centre.Goonj_Office.display_name'];

    if (!$contactId) {
      return;
    }

    $contactInfo = Contact::get(TRUE)
      ->addSelect('email_primary.email', 'phone_primary.phone', 'display_name')
      ->addWhere('id', '=', $contactId)
      ->execute()->single();

    $email = $contactInfo['email_primary.email'];
    $phone = $contactInfo['phone_primary.phone'];
    $initiatorName = $contactInfo['display_name'];

    // Send the dispatch email.
    self::sendDispatchEmail($email, $initiatorName, $droppingCenterId, $contactId, $goonjOffice, $goonjOfficeName);
  }

  /**
   *
   */
  public static function sendDispatchEmail($email, $initiatorName, $droppingCenterId, $contactId, $goonjOffice, $goonjOfficeName) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $vehicleDispatchFormUrl = $homeUrl . '/vehicle-dispatch/#?Camp_Vehicle_Dispatch.Dropping_Center=' . $droppingCenterId . '&Camp_Vehicle_Dispatch.Filled_by=' . $contactId . '&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent=' . $goonjOffice . '&Camp_Vehicle_Dispatch.Goonj_Office_Name=' . $goonjOfficeName . '&Eck_Collection_Camp1=' . $droppingCenterId;

    $emailHtml = "
    <html>
    <body>
    <p>Dear {$initiatorName},</p>
    <p>Thank you so much for your invaluable efforts in running the Goonj Dropping Center. 
    Your dedication plays a crucial role in our work, and we deeply appreciate your continued support.</p>
    <p>Please fill out this Dispatch Form – <a href='{$vehicleDispatchFormUrl}'>Link</a> once the vehicle is loaded and ready to head to Goonj’s processing center. 
    This will help us to verify and acknowledge the materials as soon as they arrive.</p>
    <p>We truly appreciate your cooperation and continued commitment to our cause.</p>
    <p>Warm Regards,<br>Team Goonj..</p>
    </body>
    </html>
    ";
    $from = HelperService::getDefaultFromEmail();
    $mailParams = [
      'subject' => 'Kindly fill the Dispatch Form for Material Pickup',
      'from' => $from,
      'toEmail' => $email,
      'html' => $emailHtml,
    ];

    \CRM_Utils_Mail::send($mailParams);
  }

  /**
   *
   */
  public static function droppingCenterTabset($tabsetName, &$tabs, $context) {
    if (!self::isViewingDroppingCenter($tabsetName, $context)) {
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
      'logistics' => [
        'title' => ts('Logistics'),
        'module' => 'afsearchLogistics',
        'directive' => 'afsearch-logistics',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'eventCoordinators' => [
        'title' => ts('Event Coordinators'),
        'module' => 'afsearchCoordinator',
        'directive' => 'afsearch-coordinator',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'vehicleDispatch' => [
        'title' => ts('Dispatch'),
        'module' => 'afsearchDroppingCenterVehicleDispatchData',
        'directive' => 'afsearch-dropping-center-vehicle-dispatch-data',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'materialAuthorization' => [
        'title' => ts('Material Authorization'),
        'module' => 'afsearchDroppingCenterAcknowledgementForLogisticsData',
        'directive' => 'afsearch-dropping-center-acknowledgement-for-logistics-data',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'materialContribution' => [
        'title' => ts('Material Contribution'),
        'module' => 'afsearchDroppingCenterMaterialContributions',
        'directive' => 'afsearch-dropping-center-material-contributions',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'mmt'],
      ],
      'status' => [
        'title' => ts('Status'),
        'module' => 'afsearchStatus',
        'directive' => 'afsearch-status',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'visit' => [
        'title' => ts('Visit'),
        'module' => 'afsearchVisitList',
        'directive' => 'afsearch-visit-list',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'donationBox' => [
        'title' => ts('Donation Box'),
        'module' => 'afsearchDonation',
        'directive' => 'afsearch-donation',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'outcome' => [
        'title' => ts('Outcome'),
        'module' => 'afformDroppingCenterOutcome',
        'directive' => 'afform-dropping-center-outcome',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCampService.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'feedback' => [
        'title' => ts('Feedback'),
        'module' => 'afsearchFeedback',
        'directive' => 'afsearch-feedback',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      // 'monetaryContribution' => [
      //   'title' => ts('Monetary Contribution'),
      //   'module' => 'afsearchMonetaryContribution',
      //   'directive' => 'afsearch-monetary-contribution',
      //   'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
      //   'permissions' => ['account_team', 'ho_account'],
      // ],
      // 'monetaryContributionForUrbanOps' => [
      //   'title' => ts('Monetary Contribution'),
      //   'module' => 'afsearchMonetaryContributionForUrbanOps',
      //   'directive' => 'afsearch-monetary-contribution-for-urban-ops',
      //   'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
      //   'permissions' => ['goonj_chapter_admin', 'urbanops'],
      // ],
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
  private static function isViewingDroppingCenter($tabsetName, $context) {
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

}
