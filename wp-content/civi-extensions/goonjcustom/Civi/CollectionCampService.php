<?php

namespace Civi;

require_once __DIR__ . '/../../../../wp-content/civi-extensions/goonjcustom/vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Api4\OptionValue;
use Civi\Api4\Relationship;
use Civi\Api4\StateProvince;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class CollectionCampService extends AutoSubscriber {
  const FALLBACK_OFFICE_NAME = 'Delhi';
  const RELATIONSHIP_TYPE_NAME = 'Collection Camp Coordinator of';

  private static $individualId = NULL;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => [
        ['individualCreated'],
        ['assignChapterGroupToIndividual'],
        ['reGenerateCollectionCampQr'],
      ],
      '&hook_civicrm_pre' => [
        ['generateCollectionCampQr'],
        ['linkCollectionCampToContact'],
        ['generateCollectionCampCode'],
      ],
      '&hook_civicrm_custom' => [
        ['setOfficeDetails'],
        ['linkInductionWithCollectionCamp'],
      ],
      '&hook_civicrm_fieldOptions' => 'setIndianStateOptions',
    ];
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

    $stateId = $objectRef->state_province_id;

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

    $groupId = $stateContactGroup['id'];

    $groupContactResult = GroupContact::create(FALSE)
      ->addValue('contact_id', self::$individualId)
      ->addValue('group_id', $groupId)
      ->addValue('status', 'Added')
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
  public static function generateCollectionCampCode(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName != 'Eck_Collection_Camp') {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';

    if (!$newStatus) {
      return;
    }

    $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_Core_Details.Contact_Id', 'title')
      ->addWhere('id', '=', $objectId)
      ->execute()->single();

    $currentStatus = $collectionCamp['Collection_Camp_Core_Details.Status'];

    // Check for status change.
    if ($currentStatus !== $newStatus) {
      if ($newStatus === 'authorized') {
        // Access the subtype.
        $subtypeId = $objectRef['subtype'] ?? NULL;
        if ($subtypeId === NULL) {
          return;
        }

        // Access the id within the decoded data.
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
    if ($subtypeId === $optionValue['value']) {
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
      error_log("Exception caught while logging activity: " . $ex->getMessage());
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
    if ($objectName != 'Eck_Collection_Camp' || !$objectId) {
      return;
    }

    error_log("Testing -1");
    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';

    error_log("newStatus: " . print_r($newStatus, TRUE));

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

    error_log("currentCollectionCamp: " . print_r($currentCollectionCamp, TRUE));
    error_log("collectionCampId: " . print_r($collectionCampId, TRUE));

    // Check for status change.
    if ($currentStatus !== $newStatus) {
      if ($newStatus === 'authorized') {
        self::generateQrCode($collectionCampId);
      }
    }
  }

  /**
   *
   */
  public static function generateQrCode($collectionCampId) {

    error_log("Testing -2");
    try {
      $baseUrl = \CRM_Core_Config::singleton()->userFrameworkBaseURL;
      $url = "{$baseUrl}actions/collection-camp/{$collectionCampId}";

      $options = new QROptions([
        'version'    => 5,
        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
        'eccLevel'   => QRCode::ECC_L,
        'scale'      => 10,
      ]);

      $qrcode = (new QRCode($options))->render($url);

      // Remove the base64 header and decode the image data.
      $qrcode = str_replace('data:image/png;base64,', '', $qrcode);

      $qrcode = base64_decode($qrcode);

      $baseFileName = "qr_code_{$collectionCampId}.png";

      $fileName = \CRM_Utils_File::makeFileName($baseFileName);

      $tempFilePath = \CRM_Utils_File::tempnam($baseFileName);

      $numBytes = file_put_contents($tempFilePath, $qrcode);

      if (!$numBytes) {
        \CRM_Core_Error::debug_log_message('Failed to write QR code to temporary file for collection camp ID ' . $collectionCampId);
        return FALSE;
      }

      $customFields = CustomField::get(TRUE)
        ->addSelect('id')
        ->addWhere('custom_group_id:name', '=', 'Collection_Camp_QR_Code')
        ->addWhere('name', '=', 'QR_Code')
        ->setLimit(1)
        ->execute();

      $qrField = $customFields->first();

      error_log("qrField: " . print_r($qrField, TRUE));

      if (!$qrField) {
        \CRM_Core_Error::debug_log_message('No field to save QR Code for collection camp ID ' . $collectionCampId);
        return FALSE;
      }

      $qrFieldId = 'custom_' . $qrField['id'];
      error_log("qrFieldId: " . print_r($qrFieldId, TRUE));
      error_log("collectionCampId: " . print_r($collectionCampId, TRUE));
      error_log("fileName: " . print_r($fileName, TRUE));
      error_log("tempFilePath: " . print_r($tempFilePath, TRUE));

      // Save the QR code as an attachment linked to the collection camp.
      $params = [
        'entity_id' => $collectionCampId,
        'name' => $fileName,
        'mime_type' => 'image/png',
        'field_name' => $qrFieldId,
        'options' => [
          'move-file' => $tempFilePath,
        ],
      ];

      $result = civicrm_api3('Attachment', 'create', $params);
      error_log("result: " . print_r($result, TRUE));

      if (empty($result['id'])) {
        \CRM_Core_Error::debug_log_message('Failed to create attachment for collection camp ID ' . $collectionCampId);
        return FALSE;
      }

      $attachment = $result['values'][$result['id']];
      error_log("attachment: " . print_r($attachment, TRUE));

      $attachmentUrl = $attachment['url'];
    }
    catch (\CiviCRM_API3_Exception $e) {
      \CRM_Core_Error::debug_log_message('Error generating QR code: ' . $e->getMessage());
      return FALSE;
    }

    return TRUE;
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

    $collectionCampId = $objectRef->id ?? NULL;
    $collectionCamp = EckEntity::get('Collection_Camp', TRUE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_QR_Code.QR_Code')
      ->addWhere('id', '=', $collectionCampId)
      ->execute()->single();

    $status = $collectionCamp['Collection_Camp_Core_Details.Status'];
    $collectionCampQr = $collectionCamp['Collection_Camp_QR_Code.QR_Code'];

    if ($status !== 'authorized' || $collectionCampQr !== NULL) {
      return;
    }

    self::generateQrCode($collectionCampId);

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
    if ($op !== 'create') {
      return;
    }

    if (!($stateField = self::findStateField($params))) {
      return;
    }

    $stateId = $stateField['value'];
    $collectionCampId = $stateField['entity_id'];

    $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Intent_Details.Will_your_collection_drive_be_open_for_general_public')
      ->addWhere('id', '=', $collectionCampId)
      ->execute();

    $collectionCampData = $collectionCamp->first();
    $isPublicDriveOpen = $collectionCampData['Collection_Camp_Intent_Details.Will_your_collection_drive_be_open_for_general_public'];

    if (!$stateId) {
      \CRM_Core_Error::debug_log_message('Cannot assign Goonj Office to collection camp: ' . $collectionCamp['id']);
      \CRM_Core_Error::debug_log_message('No state provided on the intent for collection camp: ' . $collectionCamp['id']);
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
    $filteredItems = array_filter($array, fn($item) => $item['entity_table'] === 'civicrm_eck_collection_camp');

    if (empty($filteredItems)) {
      return FALSE;
    }

    $collectionCampStateFields = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'state')
      ->addWhere('custom_group_id:name', '=', 'Collection_Camp_Intent_Details')
      ->execute()
      ->first();

    if (!$collectionCampStateFields) {
      return FALSE;
    }

    $stateFieldId = $collectionCampStateFields['id'];

    $stateItemIndex = array_search(TRUE, array_map(fn($item) =>
        $item['entity_table'] === 'civicrm_eck_collection_camp' &&
        $item['custom_field_id'] == $stateFieldId,
        $filteredItems
    ));

    return $stateItemIndex !== FALSE ? $filteredItems[$stateItemIndex] : FALSE;
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
      ->addWhere('is_current', '=', FALSE)
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

}
