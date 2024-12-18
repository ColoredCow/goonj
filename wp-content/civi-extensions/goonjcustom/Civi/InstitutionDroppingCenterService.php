<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Email;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Api4\Relationship;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\CollectionSource;
use Civi\Traits\QrCodeable;

/**
 *
 */
class InstitutionDroppingCenterService extends AutoSubscriber {
  use QrCodeable;
  use CollectionSource;
  /**
   *
   */

  const ENTITY_SUBTYPE_NAME = 'Institution_Dropping_Center';
  const ENTITY_NAME = 'Collection_Camp';
  const FALLBACK_OFFICE_NAME = 'Delhi';
  const MATERIAL_RELATIONSHIP_TYPE_NAME = 'Material Management Team of';

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_tabset' => 'institutionDroppingCenterTabset',
      '&hook_civicrm_custom' => [
        ['setOfficeDetails'],
        ['mailNotificationToMmt'],
      ],
      '&hook_civicrm_post' => 'processDispatchEmail',
      '&hook_civicrm_pre' => [
        ['assignChapterGroupToIndividual'],
        ['generateInstitutionDroppingCenterQr'],
        ['linkInstitutionDroppingCenterToContact'],
      ],
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

    if ($objectName !== 'Eck_Collection_Camp' || !$objectId || !self::isCurrentSubtype($objectRef)) {
      return;
    }
    $stateId = $objectRef['Institution_Dropping_Center_Intent.State'];
    $contactId = $objectRef['Institution_Dropping_Center_Intent.Institution_POC'];
    $status = $objectRef['Collection_Camp_Core_Details.Status'];

    if ($status == 'authorized') {
      return;
    }
    if (!$stateId) {
      \Civi::log()->info("Missing Contact ID and State ID");
      return FALSE;
    }
    $groupId = self::getChapterGroupForState($stateId);

    if ($groupId) {
      self::addContactToGroup($contactId, $groupId);
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
      \Civi::log()->info("Successfully added contact_id: $contactId to group_id: $groupId.");
    }
    catch (Exception $e) {
      \Civi::log()->error("Error adding contact_id: $contactId to group_id: $groupId. Exception: " . $e->getMessage());
    }
  }

  /**
   *
   */
  public static function linkInstitutionDroppingCenterToContact(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName !== 'Eck_Collection_Camp' || !$objectId || !self::isCurrentSubtype($objectRef)) {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';
    $organizationId = $objectRef['Institution_Dropping_Center_Intent.Organization_Name'];
    if (!$newStatus) {
      return;
    }

    $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Institution_Dropping_Center_Intent.Organization_Name', 'title', 'Institution_Dropping_Center_Intent.Institution_POC')
      ->addWhere('id', '=', $objectId)
      ->execute();

    $currentDroppingCenter = $collectionCamps->first();
    $currentStatus = $currentDroppingCenter['Collection_Camp_Core_Details.Status'];
    $PocId = $currentDroppingCenter['Institution_Dropping_Center_Intent.Institution_POC'];

    if (!$PocId && !$organizationId) {
      return;
    }

    $droppingCenterCode = $currentDroppingCenter['title'];
    $droppingCenterId = $currentDroppingCenter['id'];

    if ($currentStatus !== $newStatus && $newStatus === 'authorized') {
      self::createDroppingCenterOrganizeActivity($PocId, $organizationId, $droppingCenterCode, $droppingCenterId);
    }
  }

  /**
   *
   */
  private static function createDroppingCenterOrganizeActivity($PocId, $organizationId, $droppingCenterCode, $droppingCenterId) {
    try {

      // Create activity for PocId.
      self::createActivity($PocId, $droppingCenterCode, $droppingCenterId);

      // Create activity for organizationId, only if it's different from PocId.
      if ($organizationId !== $PocId) {
        self::createActivity($organizationId, $droppingCenterCode, $droppingCenterId);
      }

    }
    catch (\CiviCRM_API4_Exception $ex) {
      \Civi::log()->debug("Exception while creating Organize Institution Dropping Center activity: " . $ex->getMessage());
    }
  }

  /**
   *
   */
  private static function createActivity($contactId, $droppingCenterCode, $droppingCenterId) {
    Activity::create(FALSE)
      ->addValue('subject', $droppingCenterCode)
      ->addValue('activity_type_id:name', 'Organize Institution Dropping Center')
      ->addValue('status_id:name', 'Authorized')
      ->addValue('activity_date_time', date('Y-m-d H:i:s'))
      ->addValue('source_contact_id', $contactId)
      ->addValue('target_contact_id', $contactId)
      ->addValue('Collection_Camp_Data.Collection_Camp_ID', $droppingCenterId)
      ->execute();

    \Civi::log()->info("Activity created for contact {$contactId} for Institution Dropping Center {$droppingCenterCode}");
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
  private static function findStateField(array $array) {
    $stateFieldId = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'State')
      ->addWhere('custom_group_id:name', '=', 'Institution_Dropping_Center_Intent')
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
  public static function assignCoordinatorByRelationshipType($stateOfficeId, $registrationType, $institutionDroppingCenterId) {
    // Define the mapping of registration categories to relationship type names.
    $relationshipTypeMap = [
      'Corporate' => 'Corporate Coordinator of',
      'School' => 'School Coordinator of',
      'College' => 'College Coordinator of',
      'Associations' => 'Associations Coordinator of',
      'Others' => 'Others Coordinator of',
    ];

    $registrationCategorySelection = $registrationType['Institution_Dropping_Center_Intent.You_wish_to_register_as:name'];
    $registrationCategorySelection = trim($registrationCategorySelection);

    if (array_key_exists($registrationCategorySelection, $relationshipTypeMap)) {
      $relationshipTypeName = $relationshipTypeMap[$registrationCategorySelection];
    }
    else {
      $relationshipTypeName = 'Other Coordinator of';
    }

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

    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Institution_Dropping_Center_Review.Coordinating_POC', $coordinator['contact_id_a'])
      ->addWhere('id', '=', $institutionDroppingCenterId)
      ->execute();

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
    if ($op !== 'create' || self::getEntitySubtypeName($entityID) !== self::ENTITY_SUBTYPE_NAME) {
      return;
    }

    if (!($stateField = self::findStateField($params))) {
      return;
    }

    $stateId = $stateField['value'];
    $institutionDroppingCenterId = $stateField['entity_id'];

    if (!$stateId) {
      \CRM_Core_Error::debug_log_message('State ID not found, unable to assign Goonj Office.');
      return FALSE;
    }

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

    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Institution_Dropping_Center_Review.Goonj_Office', $stateOfficeId)
      ->addWhere('id', '=', $institutionDroppingCenterId)
      ->execute();

    $registrationType = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Institution_Dropping_Center_Intent.You_wish_to_register_as:name')
      ->addWhere('id', '=', $entityID)
      ->execute()->single();

    return self::assignCoordinatorByRelationshipType($stateOfficeId, $registrationType, $institutionDroppingCenterId);
  }

  /**
   *
   */
  public static function generateInstitutionDroppingCenterQr(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName !== 'Eck_Collection_Camp' || !$objectId || !self::isCurrentSubtype($objectRef)) {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';
    if (!$newStatus) {
      return;
    }

    $collectionCamps = EckEntity::get('Collection_Camp', TRUE)
      ->addSelect('Collection_Camp_Core_Details.Status')
      ->addWhere('id', '=', $objectId)
      ->execute();

    $currentDroppingCenter = $collectionCamps->first();
    $currentStatus = $currentDroppingCenter['Collection_Camp_Core_Details.Status'];
    $droppingCenterId = $currentDroppingCenter['id'];

    // Check for status change.
    if ($currentStatus !== $newStatus && $newStatus === 'authorized') {
      self::generateInstitutionDroppingCenterQrCode($droppingCenterId);
    }
  }

  /**
   *
   */
  private static function generateInstitutionDroppingCenterQrCode($id) {
    $baseUrl = \CRM_Core_Config::singleton()->userFrameworkBaseURL;
    $data = "{$baseUrl}actions/institution-dropping-center/{$id}";

    $saveOptions = [
      'customGroupName' => 'Collection_Camp_QR_Code',
      'customFieldName' => 'QR_Code',
    ];

    self::generateQrCode($data, $id, $saveOptions);
  }

  /**
   *
   */
  public static function processDispatchEmail(string $op, string $objectName, int $objectId, &$objectRef) {

    if ($objectName !== 'AfformSubmission') {
      return;
    }

    $afformName = $objectRef->afform_name;

    if ($afformName !== 'afformNotifyDispatchViaEmail') {
      return;
    }

    $jsonData = $objectRef->data;
    $dataArray = json_decode($jsonData, TRUE);

    $institutionDroppingCenterId = $dataArray['Eck_Collection_Camp1'][0]['fields']['id'];
    $isSelfManaged = $dataArray['Eck_Collection_Camp1'][0]['fields']['Institution_Collection_Camp_Logistics.Self_Managed_by_Institution'];
    $campAttendedBy = $dataArray['Eck_Collection_Camp1'][0]['fields']['Institution_Dropping_Center_Logistics.Camp_to_be_attended_by'];

    if (!$institutionDroppingCenterId) {
      return;
    }

    $droppingCenterData = EckEntity::get('Collection_Camp', TRUE)
      ->addSelect('Institution_Dropping_Center_Intent.Institution_POC', 'Institution_Dropping_Center_Review.Goonj_Office', 'Institution_Dropping_Center_Review.Goonj_Office.display_name')
      ->addWhere('id', '=', $institutionDroppingCenterId)
      ->execute()
      ->single();

    $pocId = $droppingCenterData['Institution_Dropping_Center_Intent.Institution_POC'];
    $goonjOffice = $droppingCenterData['Institution_Dropping_Center_Review.Goonj_Office'];
    $goonjOfficeName = $droppingCenterData['Institution_Dropping_Center_Review.Goonj_Office.display_name'];

    $recipientId = $isSelfManaged ? $pocId : $campAttendedBy;
    if (!$recipientId) {
      return;
    }

    $recipientContactInfo = Contact::get(TRUE)
      ->addSelect('email_primary.email', 'phone_primary.phone', 'display_name')
      ->addWhere('id', '=', $recipientId)
      ->execute()->single();

    $email = $recipientContactInfo['email_primary.email'];
    $phone = $recipientContactInfo['phone_primary.phone'];
    $initiatorName = $recipientContactInfo['display_name'];

    // Send the dispatch email.
    self::sendDispatchEmail($email, $initiatorName, $institutionDroppingCenterId, $recipientId, $goonjOffice, $goonjOfficeName);
  }

  /**
   *
   */
  public static function sendDispatchEmail($email, $initiatorName, $institutionDroppingCenterId, $contactId, $goonjOffice, $goonjOfficeName) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $vehicleDispatchFormUrl = $homeUrl . '/institution-dropping-center-vehicle-dispatch/#?Camp_Vehicle_Dispatch.Institution_Dropping_Center=' . $institutionDroppingCenterId . '&Camp_Vehicle_Dispatch.Filled_by=' . $contactId . '&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent=' . $goonjOffice . '&Camp_Vehicle_Dispatch.Goonj_Office_Name=' . $goonjOfficeName . '&Eck_Collection_Camp1=' . $institutionDroppingCenterId;

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
      ->addSelect('Camp_Vehicle_Dispatch.Institution_Dropping_Center')
      ->addWhere('id', '=', $vehicleDispatchId)
      ->execute()->first();

    $institutionDroppingCenterId = $collectionSourceVehicleDispatch['Camp_Vehicle_Dispatch.Institution_Dropping_Center'];

    if (self::getEntitySubtypeName($institutionDroppingCenterId) !== self::ENTITY_SUBTYPE_NAME) {
      return;
    }

    $institutionDroppingCenter = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Institution_Dropping_Center_Intent.Dropping_Center_Address', 'title')
      ->addWhere('id', '=', $institutionDroppingCenterId)
      ->execute()->single();

    $InstitutionDroppingCenterCode = $institutionDroppingCenter['title'];
    $institutionDroppingCenterAddress = $institutionDroppingCenter['Institution_Dropping_Center_Intent.Dropping_Center_Address'];

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
      'subject' => 'Dropping Center Material Acknowledgement - ' . $institutionDroppingCenterAddress,
      'from' => $from,
      'toEmail' => $mmtEmail,
      'replyTo' => $fromEmail['label'],
      'html' => self::getMmtEmailHtml($institutionDroppingCenterId, $InstitutionDroppingCenterCode, $institutionDroppingCenterAddress, $vehicleDispatchId, $mmtId),
    ];
    \CRM_Utils_Mail::send($mailParams);

  }

  /**
   *
   */
  public static function getMmtEmailHtml($institutionDroppingCenterId, $institutionDroppingCenterCode, $institutionDroppingCenterAddress, $vehicleDispatchId, $mmtId) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $materialdispatchUrl = $homeUrl . '/dropping-center-acknowledgement-for-dispatch/#?Eck_Collection_Source_Vehicle_Dispatch1=' . $vehicleDispatchId
        . '&Camp_Vehicle_Dispatch.Institution_Dropping_Center=' . $institutionDroppingCenterId
        . '&id=' . $vehicleDispatchId
        . '&Eck_Collection_Camp1=' . $institutionDroppingCenterId
        . '&Acknowledgement_For_Logistics.Verified_By=' . $mmtId;
    $html = "
    <p>Dear MMT team,</p>
    <p>This is to inform you that a vehicle has been sent from the dropping center <strong>$institutionDroppingCenterCode</strong> at <strong>$institutionDroppingCenterAddress</strong>.</p>
    <p>Kindly acknowledge the details by clicking on this form <a href=\"$materialdispatchUrl\"> Link </a> when it is received at the center.</p>
    <p>Warm regards,<br>Urban Relations Team</p>";

    return $html;
  }

  /**
   *
   */
  public static function institutionDroppingCenterTabset($tabsetName, &$tabs, $context) {
    if (!self::isViewingInstitutionDroppingCenter($tabsetName, $context)) {
      return;
    }

    $tabConfigs = [
      'logistics' => [
        'title' => ts('Logistics'),
        'module' => 'afsearchInstitutionDroppingCenterLogistics',
        'directive' => 'afsearch-institution-dropping-center-logistics',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'vehicleDispatch' => [
        'title' => ts('Dispatch'),
        'module' => 'afsearchInstitutionDroppingCenterVehicleDispatchData',
        'directive' => 'afsearch-institution-dropping-center-vehicle-dispatch-data',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'materialAuthorization' => [
        'title' => ts('Material Authorization'),
        'module' => 'afsearchInstitutionDroppingCenterAcknowledgementForLogisticsData',
        'directive' => 'afsearch-institution-dropping-center-acknowledgement-for-logistics-data',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'materialContribution' => [
        'title' => ts('Material Contribution'),
        'module' => 'afsearchInstitutionDroppingCenterMaterialContribution',
        'directive' => 'afsearch-institution-dropping-center-material-contribution',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'status' => [
        'title' => ts('Status'),
        'module' => 'afsearchInstitutionDroppingCenterStatus',
        'directive' => 'afsearch-institution-dropping-center-status',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'visit' => [
        'title' => ts('Visit'),
        'module' => 'afsearchInstitutionDroppingCenterVisit',
        'directive' => 'afsearch-institution-dropping-center-visit',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'donationBox' => [
        'title' => ts('Donation Box'),
        'module' => 'afsearchInstitutionDroppingCenterDonation',
        'directive' => 'afsearch-institution-dropping-center-donation',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'outcome' => [
        'title' => ts('Outcome'),
        'module' => 'afformInstitutionDroppingCenterOutcome',
        'directive' => 'afform-institution-dropping-center-outcome',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCampService.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'feedback' => [
        'title' => ts('Feedback'),
        'module' => 'afsearchInstitutionDroppingCenterFeedback',
        'directive' => 'afsearch-institution-dropping-center-feedback',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
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
  private static function isViewingInstitutionDroppingCenter($tabsetName, $context) {
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
