<?php

namespace Civi;

require_once __DIR__ . '/../../../../wp-content/civi-extensions/goonjcustom/vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Api4\MessageTemplate;
use Civi\Api4\OptionValue;
use Civi\Api4\Relationship;
use Civi\Api4\StateProvince;
use Civi\Core\Service\AutoSubscriber;

/**
 * Collection Camp Service file to generate collection camp qr code.
 */
class CollectionCampService extends AutoSubscriber {
  public const FALLBACK_OFFICE_NAME = 'Delhi';
  public const RELATIONSHIP_TYPE_NAME = 'Collection Camp Coordinator of';

  private static $individualId = NULL;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => [
              ['generateCollectionCampCode'],
              ['individualCreated'],
              ['assignChapterGroupToIndividual'],
      ],
      '&hook_civicrm_pre' => [
              ['handleAuthorizationEmails'],
              ['generateCollectionCampQr'],
      ],
      '&hook_civicrm_custom' => 'setOfficeDetails',
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
  public static function generateCollectionCampCode(string $op, string $objectName, int $objectId, &$objectRef) {
    // Check if the object name is 'AfformSubmission'.
    if ($objectName !== 'AfformSubmission') {
      return;
    }

    // Extract the 'data' field.
    $data = $objectRef->data;
    $decodedData = json_decode($data, TRUE);

    // Check if 'Eck_Collection_Camp1' exists.
    $collectionCampEntries = $decodedData['Eck_Collection_Camp1'] ?? [];
    if (empty($collectionCampEntries)) {
      return;
    }

    foreach ($collectionCampEntries as $entry) {
      $collectionCampData = $entry['fields'] ?? NULL;

      // Access the subtype.
      $subtypeId = $collectionCampData['subtype'] ?? NULL;
      if ($subtypeId === NULL) {
        continue;
      }

      // Access the id within the decoded data.
      $campId = $collectionCampData['id'] ?? NULL;
      if ($campId === NULL) {
        continue;
      }

      // Fetch the collection camp details.
      $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
        ->addWhere('id', '=', $campId)
        ->setLimit(1)
        ->execute();

      if (empty($collectionCamps)) {
        continue;
      }

      $collectionCampsCreatedDate = $collectionCamps->first()['created_date'] ?? NULL;

      // Get the year.
      $year = date('Y', strtotime($collectionCampsCreatedDate));

      // Fetch the state ID.
      $stateId = self::getStateIdForSubtype($collectionCampData, $subtypeId);

      if (!$stateId) {
        continue;
      }

      // Fetch the state abbreviation.
      $stateProvinces = StateProvince::get(FALSE)
        ->addWhere('id', '=', $stateId)
        ->setLimit(1)
        ->execute();

      if (empty($stateProvinces)) {
        continue;
      }

      $stateAbbreviation = $stateProvinces->first()['abbreviation'] ?? NULL;

      if (!$stateAbbreviation) {
        continue;
      }

      // Fetch the Goonj-specific state code.
      $config = self::getConfig();
      $stateCode = $config['state_codes'][$stateAbbreviation] ?? 'UNKNOWN';

      // Get the current event title.
      $currentTitle = $collectionCampData['title'] ?? 'Collection Camp';

      // Fetch the event code.
      $eventCode = $config['event_codes'][$currentTitle] ?? 'UNKNOWN';

      // Count existing camps for the state and year with the same event code.
      $existingCamps = EckEntity::get('Collection_Camp', FALSE)
        ->addSelect('title')
        ->addWhere('title', 'LIKE', "$year/$stateCode/$eventCode/%")
        ->execute();

      $serialNumber = sprintf('%03d', $existingCamps->count() + 1);

      // Modify the title to include the year, state code, event code, and serial number.
      $newTitle = $year . '/' . $stateCode . '/' . $eventCode . '/' . $serialNumber;
      $collectionCampData['title'] = $newTitle;

      // Save the updated title back to the Collection Camp entity.
      EckEntity::update('Collection_Camp')
        ->addWhere('id', '=', $campId)
        ->addValue('title', $newTitle)
        ->execute();
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
  public static function getStateIdForSubtype(array $collectionCampData, int $subtypeId): ?int {
    // Subtype for 'Dropping Centre'.
    if ($subtypeId === 5) {
      return $collectionCampData['Dropping_Centre.State'] ?? NULL;
    }
    return $collectionCampData['Collection_Camp_Intent_Details.State'] ?? NULL;
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
  public static function handleAuthorizationEmails(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName != 'Eck_Collection_Camp' || !$objectId) {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';
    $subType = $objectRef['subtype'] ?? '';

    if (!$newStatus) {
      return;
    }

    $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_Core_Details.Contact_Id')
      ->addWhere('id', '=', $objectId)
      ->execute();

    $currentCollectionCamp = $collectionCamps->first();
    $currentStatus = $currentCollectionCamp['Collection_Camp_Core_Details.Status'];
    $contactId = $currentCollectionCamp['Collection_Camp_Core_Details.Contact_Id'];

    $collectionCampOptionValues = OptionValue::get(FALSE)
      ->addWhere('option_group_id:label', '=', 'ECK Subtypes')
      ->addWhere('label', '=', 'Collection Camp')
      ->execute();
    $droppingCenterOptionValues = OptionValue::get(FALSE)
      ->addWhere('option_group_id:label', '=', 'ECK Subtypes')
      ->addWhere('label', '=', 'Dropping Center')
      ->execute();

    $collectionCampSubtype = $collectionCampOptionValues->first()['value'];
    $droppingCenterSubtype = $droppingCenterOptionValues->first()['value'];
    // Check for status change.
    if ($currentStatus !== $newStatus) {
      if ($newStatus === 'authorized') {
        self::sendAuthorizationEmail($contactId, $subType, $collectionCampSubtype, $droppingCenterSubtype);
      }
      elseif ($newStatus === 'unauthorized') {
        self::sendUnAuthorizationEmail($contactId, $subType, $collectionCampSubtype, $droppingCenterSubtype);
      }
    }
  }

  /**
   * Send Authorization Email to contact.
   */
  private static function sendAuthorizationEmail($contactId, $subType, $collectionCampSubtype, $droppingCenterSubtype) {
    try {
      // Determine the template based on dynamic subtype.
      $templateIds = self::getMessageTemplateIDs();
      $collectionCampAuthorizedTemplateId = $templateIds['collectionCampAuthorizedTemplateId'];
      $droppingCenterAuthorizedTemplateId = $templateIds['droppingCenterAuthorizedTemplateId'];
      $templateId = $subType == $collectionCampSubtype ? $collectionCampAuthorizedTemplateId : ($subType == $droppingCenterSubtype ? $droppingCenterAuthorizedTemplateId : NULL);

      if (!$templateId) {
        return;
      }

      $emailParams = [
        'contact_id' => $contactId,
        // Template ID for the authorization email.
        'template_id' => $templateId,
      ];

      $result = civicrm_api3('Email', 'send', $emailParams);

    }
    catch (\CiviCRM_API3_Exception $ex) {
      error_log("Exception caught while sending authorization email: " . $ex->getMessage());
    }
  }

  /**
   * Send UnAuthorization Email to contact.
   */
  private static function sendUnAuthorizationEmail($contactId, $subType, $collectionCampSubtype, $droppingCenterSubtype) {
    try {
      // Determine the template based on dynamic subtype.
      $templateIds = self::getMessageTemplateIDs();
      $collectionCampUnAuthorizedTemplateId = $templateIds['collectionCampUnAuthorizedTemplateId'];
      $droppingCenterUnAuthorizedTemplateId = $templateIds['droppingCenterUnAuthorizedTemplateId'];
      $templateId = $subType == $collectionCampSubtype ? $collectionCampUnAuthorizedTemplateId : ($subType == $droppingCenterSubtype ? $droppingCenterUnAuthorizedTemplateId : NULL);

      if (!$templateId) {
        return;
      }

      $emailParams = [
        'contact_id' => $contactId,
        // Template ID for the unauthorization email.
        'template_id' => $templateId,
      ];

      $result = civicrm_api3('Email', 'send', $emailParams);

    }
    catch (\CiviCRM_API3_Exception $ex) {
      error_log("Exception caught while sending unauthorization email: " . $ex->getMessage());
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
        self::generateQrCode($collectionCampId);
      }
    }
  }

  /**
   *
   */
  public static function generateQrCode($collectionCampId) {
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

      if (!$qrField) {
        \CRM_Core_Error::debug_log_message('No field to save QR Code for collection camp ID ' . $collectionCampId);
        return FALSE;
      }

      $qrFieldId = 'custom_' . $qrField['id'];

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

      if (empty($result['id'])) {
        \CRM_Core_Error::debug_log_message('Failed to create attachment for collection camp ID ' . $collectionCampId);
        return FALSE;
      }

      $attachment = $result['values'][$result['id']];
      $attachmentUrl = $attachment['url'];
    }
    catch (\CiviCRM_API3_Exception $e) {
      \CRM_Core_Error::debug_log_message('Error generating QR code: ' . $e->getMessage());
      return FALSE;
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

    self::sendAdminNotificatationForCollectionCamp($coordinatorId);

    return TRUE;

  }

  /**
   *
   */
  private static function findStateField(array $array) {
    $filteredItems = array_filter($array, fn ($item) => $item['entity_table'] === 'civicrm_eck_collection_camp');

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

    $stateItemIndex = array_search(TRUE, array_map(
          fn ($item) =>
              $item['entity_table'] === 'civicrm_eck_collection_camp' &&
              $item['custom_field_id'] == $stateFieldId,
          $filteredItems
      ));

    return $stateItemIndex !== FALSE ? $filteredItems[$stateItemIndex] : FALSE;
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

  /**
   *
   */
  public static function getMessageTemplateIDs() {
    $messageTemplates = MessageTemplate::get(TRUE)
      ->addSelect('id', 'msg_title')
      ->execute();

    $collectionCampAuthorizedTemplateId = NULL;
    $collectionCampUnAuthorizedTemplateId = NULL;
    $droppingCenterAuthorizedTemplateId = NULL;
    $droppingCenterUnAuthorizedTemplateId = NULL;

    foreach ($messageTemplates as $messageTemplate) {
      if ($messageTemplate['msg_title'] === 'Collection Camp Authorized Email To User') {
        $collectionCampAuthorizedTemplateId = $messageTemplate['id'];
      }
      if ($messageTemplate['msg_title'] === 'Trigger UnAuthorized Email for Collection Camp to User') {
        $collectionCampUnAuthorizedTemplateId = $messageTemplate['id'];
      }
      if ($messageTemplate['msg_title'] === 'Trigger UnAuthorized Email for dropping center to User') {
        $droppingCenterUnAuthorizedTemplateId = $messageTemplate['id'];
      }
      if ($messageTemplate['msg_title'] === 'Dropping Center Authorized Email To User') {
        $droppingCenterAuthorizedTemplateId = $messageTemplate['id'];
      }

      if ($messageTemplate['msg_title'] === 'Notify Urban Ops Team after collection camp form submission') {
        $adminNotificationTemplateId = $messageTemplate['id'];
      }

    }

    return [
      'collectionCampAuthorizedTemplateId' => $collectionCampAuthorizedTemplateId,
      'collectionCampUnAuthorizedTemplateId' => $collectionCampUnAuthorizedTemplateId,
      'droppingCenterAuthorizedTemplateId' => $droppingCenterAuthorizedTemplateId,
      'droppingCenterUnAuthorizedTemplateId' => $droppingCenterUnAuthorizedTemplateId,
      'adminNotificationColletionCampTemplateId' => $adminNotificationTemplateId,
    ];
  }

  /**
   *
   */
  public static function sendAdminNotificatationForCollectionCamp($contactId) {
    try {
      $templateIds = self::getMessageTemplateIDs();
      $adminNotificationColletionCampTemplateId = $templateIds['adminNotificationColletionCampTemplateId'];

      // Set up email parameters.
      $emailParams = [
        'contact_id' => $contactId,
        'template_id' => $adminNotificationColletionCampTemplateId,
      ];

      // Send email using CiviCRM API.
      $result = civicrm_api3('Email', 'send', $emailParams);
    }
    catch (\CiviCRM_API3_Exception $ex) {
      // Log the exception for debugging.
      \Civi::log()->error("Exception caught while sending unauthorized email: " . $ex->getMessage());
      error_log("Exception caught while sending unauthorized email: " . $ex->getMessage());
    }
  }

}
