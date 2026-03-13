<?php

namespace Civi\Traits;

use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;
use Civi\Api4\Organization;
use Civi\Api4\StateProvince;

/**
 *
 */
trait CollectionSource {
  private static $subtypeId;

  /**
   *
   */
  public static function getSubtypeId() {
    if (!self::$subtypeId) {
      $subtype = OptionValue::get(FALSE)
        ->addWhere('grouping', '=', static::ENTITY_NAME)
        ->addWhere('name', '=', static::ENTITY_SUBTYPE_NAME)
        ->execute()->single();

      self::$subtypeId = (int) $subtype['value'];
    }

    return self::$subtypeId;
  }

  /**
   *
   */
  public static function getEntitySubtypeName($entityID) {
    $getSubtypeName = civicrm_api4('Eck_Collection_Camp', 'get', [
      'select' => [
        'subtype:name',
      ],
      'where' => [
              ['id', '=', $entityID],
      ],
      'checkPermissions' => FALSE,
    ]);

    $entityData = $getSubtypeName[0] ?? [];

    return $entityData['subtype:name'] ?? NULL;
  }

  /**
   *
   */
  public static function getOrgSubtypeName($entityID) {
    $organization = Organization::get(FALSE)
      ->addSelect('contact_sub_type')
      ->addWhere('id', '=', $entityID)
      ->execute()
      ->first();

    if (!$organization) {
      return FALSE;
    }

    $subType = $organization['contact_sub_type'];

    return $subType;
  }

  /**
   *
   */
  private static function isCurrentSubtype($objectRef) {
    if (empty($objectRef['subtype'])) {
      return FALSE;
    }

    $subtypeId = self::getSubtypeId();
    return (int) $objectRef['subtype'] === $subtypeId;
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
  public static function generateCollectionSourceCode(string $op, string $objectName, $objectId, &$objectRef) {
    $statusDetails = self::checkCampStatusAndIds($objectName, $objectId, $objectRef);

    if (!$statusDetails) {
      return;
    }
    \Civi::log()->debug('generateCollectionSourceCode code is running');

    $newStatus = $statusDetails['newStatus'];
    $currentStatus = $statusDetails['currentStatus'];

    if ($currentStatus !== $newStatus) {
      if ($newStatus === 'authorized') {
        $subtypeId = $objectRef['subtype'] ?? NULL;
        \Civi::log()->debug('[CodeGen] subtypeId', ['subtypeId' => $subtypeId]);
        if (!$subtypeId) {
          return;
        }

        $sourceId = $objectRef['id'] ?? NULL;
        \Civi::log()->debug('[CodeGen] sourceId', ['sourceId' => $sourceId]);
        if (!$sourceId) {
          return;
        }

        $collectionSource = EckEntity::get('Collection_Camp', FALSE)
          ->addWhere('id', '=', $sourceId)
          ->execute()->single();

        $collectionSourceCreatedDate = $collectionSource['created_date'] ?? NULL;
        \Civi::log()->debug('[CodeGen] createdDate', ['createdDate' => $collectionSourceCreatedDate]);

        $sourceTitle = $collectionSource['title'] ?? NULL;

        $year = date('Y', strtotime($collectionSourceCreatedDate));

        $stateId = self::getStateIdForSourceType($objectRef, $subtypeId, $sourceTitle);
        \Civi::log()->debug('[CodeGen] stateId', ['stateId' => $stateId]);

        if (!$stateId) {
          \Civi::log()->debug('[CodeGen] Aborting: stateId missing', ['sourceId' => $sourceId]);
          return;
        }

        $stateProvince = StateProvince::get(FALSE)
          ->addWhere('id', '=', $stateId)
          ->execute()->single();

        if (empty($stateProvince)) {
          \Civi::log()->debug('[CodeGen] Aborting: stateProvince not found', ['stateId' => $stateId]);
          return;
        }

        $stateAbbreviation = $stateProvince['abbreviation'] ?? NULL;
        if (!$stateAbbreviation) {
          \Civi::log()->debug('[CodeGen] Aborting: stateAbbreviation missing', ['stateId' => $stateId]);
          return;
        }

        $config = self::getConfig();
        $stateCode = $config['state_codes'][$stateAbbreviation] ?? 'UNKNOWN';
        $sourceTypeName = self::getSourceTypeName($subtypeId);
        $sourceTypeTitle = str_replace('_', ' ', $sourceTypeName);
        $eventCode = $config['event_codes'][$sourceTypeTitle] ?? 'UNKNOWN';
        \Civi::log()->debug('[CodeGen] eventCode', ['sourceTypeName' => $sourceTypeName, 'sourceTypeTitle' => $sourceTypeTitle, 'eventCode' => $eventCode]);

        $newTitle = $year . '/' . $stateCode . '/' . $eventCode . '/' . $sourceId;
        \Civi::log()->info('[CodeGen] newTitle', ['newTitle' => $newTitle]);
        $objectRef['title'] = $newTitle;

        \Civi::log()->info('[CodeGen] Camp code generated', ['sourceId' => $sourceId, 'newTitle' => $newTitle]);
      }
    }
  }

  /**
   * Check the status and return status details.
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

    $collectionSource = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Status')
      ->addWhere('id', '=', $objectId)
      ->execute()->single();

    $currentStatus = $collectionSource['Collection_Camp_Core_Details.Status'] ?? '';

    return [
      'newStatus' => $newStatus,
      'currentStatus' => $currentStatus,
    ];
  }

  /**
   *
   */
  private static function getConfig() {
    $extensionsDir = \CRM_Core_Config::singleton()->extensionsDir;

    $extensionPath = $extensionsDir . 'goonjcustom/config/';

    return [
      'state_codes' => include $extensionPath . 'constants.php',
      'event_codes' => include $extensionPath . 'eventCode.php',
    ];
  }

  /**
   *
   */
  public static function shouldSendAuthorizationEmail(string $subtypeName, string $newStatus, &$objectRef) {
    $subtypes = [
      'Institution_Collection_Camp' => 'Institution_collection_camp_Review.Send_Authorization_Email',
      'Institution_Dropping_Center' => 'Institution_Dropping_Center_Review.Send_Authorization_Email',
      'Institution_Goonj_Activities' => 'Institution_Goonj_Activities.Send_Authorization_Email',
    ];

    if (in_array($subtypeName, array_keys($subtypes)) && in_array($newStatus, ['authorized', 'unauthorized'])) {
      $sendAuthorizedEmail = $objectRef[$subtypes[$subtypeName]];

      if (!$sendAuthorizedEmail) {
        return FALSE;
      }
    }
    else {
      \Civi::log()->info("Other subtype detected: " . $subtypeName);

    }

    return TRUE;
  }

  /**
   *
   */
  public static function getStateIdForSourceType(array $objectRef, int $subtypeId, ?string $campTitle): ?int {

    $sourceStateFieldMapper = [
      'Collection_Camp' => 'Collection_Camp_Intent_Details.State',
      'Dropping_Center' => 'Dropping_Centre.State',
      'Institution_Collection_Camp' => 'Institution_Collection_Camp_Intent.State',
      'Goonj_Activities' => 'Goonj_Activities.State',
      'Institution_Dropping_Center' => 'Institution_Dropping_Center_Intent.State',
      'Institution_Goonj_Activities' => 'Institution_Goonj_Activities.State',
    ];

    $sourceTypeName = self::getSourceTypeName($subtypeId);

    $stateFieldName = $sourceStateFieldMapper[$sourceTypeName];

    return $objectRef[$stateFieldName];

  }

  /**
   *
   */
  private static function getInitiatorId(array $collectionCamp) {
    $subtypeName = $collectionCamp['subtype:name'];

    if ($subtypeName === 'Institution_Collection_Camp') {
      return $collectionCamp['Institution_Collection_Camp_Intent.Institution_POC'];
    }
    elseif ($subtypeName === 'Institution_Dropping_Center') {
      return $collectionCamp['Institution_Dropping_Center_Intent.Institution_POC'];
    }
    elseif ($subtypeName === 'Institution_Goonj_Activities') {
      return $collectionCamp['Institution_Goonj_Activities.Institution_POC'];
    }
    else {
      return $collectionCamp['Collection_Camp_Core_Details.Contact_Id'];
    }
  }

  /**
   * Helper function to fetch option value by name.
   */
  private static function getSourceTypeName(string $sourceTypeId): ?string {

    $sourceType = OptionValue::get(TRUE)
      ->addSelect('name')
      ->addWhere('option_group_id:name', '=', 'eck_sub_types')
      ->addWhere('grouping', '=', 'Collection_Camp')
      ->addWhere('value', '=', $sourceTypeId)
      ->execute()->single();

    return $sourceType['name'];
  }

}
