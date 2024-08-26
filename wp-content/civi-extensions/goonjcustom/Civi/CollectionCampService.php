<?php

namespace Civi;

use Civi\Api4\EckEntity;
use Civi\Api4\StateProvince;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class CollectionCampService extends AutoSubscriber {

  const AUTHORIZED_TEMPLATE_ID_COLLECTION_CAMP = 78;
  const AUTHORIZED_TEMPLATE_ID_DROPPING_CENTER = 83;
  const UNAUTHORIZED_TEMPLATE_ID_COLLECTION_CAMP = 77;
  const UNAUTHORIZED_TEMPLATE_ID_DROPPING_CENTER = 82;


  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => 'generateCollectionCampCode',
      '&hook_civicrm_pre' => [
        ['handleAuthorizationEmails'],
        ['generateCollectionCampQr'],
      ],
    ];
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
      $stateProvinces = StateProvince::get(TRUE)
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

    $collectionCamps = EckEntity::get('Collection_Camp', TRUE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_Core_Details.Contact_Id')
      ->addWhere('id', '=', $objectId)
      ->execute();

    $currentCollectionCamp = $collectionCamps->first();
    $currentStatus = $currentCollectionCamp['Collection_Camp_Core_Details.Status'];
    $contactId = $currentCollectionCamp['Collection_Camp_Core_Details.Contact_Id'];

    // Check for status change.
    if ($currentStatus !== $newStatus) {
      if ($newStatus === 'authorized') {
        self::sendAuthorizationEmail($contactId, $subType);
      }
      elseif ($newStatus === 'unauthorized') {
        self::sendUnAuthorizationEmail($contactId, $subType);
      }
    }
  }

  /**
   * Send Authorization Email to contact.
   */
  private static function sendAuthorizationEmail($contactId, $subType) {
    try {
      // Determine the template based on dynamic subtype.
      $templateId = $subType == 4 ? self::AUTHORIZED_TEMPLATE_ID_COLLECTION_CAMP : ($subType == 5 ? self::AUTHORIZED_TEMPLATE_ID_DROPPING_CENTER : null);

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
  private static function sendUnAuthorizationEmail($contactId, $subType) {
    try {
      // Determine the template based on dynamic subtype.
      $templateId = $subType == 4 ? self::UNAUTHORIZED_TEMPLATE_ID_COLLECTION_CAMP : ($subType == 5 ? self::UNAUTHORIZED_TEMPLATE_ID_DROPPING_CENTER : null);

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

  }

}
