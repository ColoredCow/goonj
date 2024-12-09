<?php

namespace Civi\Traits;

use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;
use Civi\Api4\Organization;
use Civi\Api4\Relationship;
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

    $newStatus = $statusDetails['newStatus'];
    $currentStatus = $statusDetails['currentStatus'];

    if ($currentStatus !== $newStatus) {
      if ($newStatus === 'authorized') {
        $subtypeId = $objectRef['subtype'] ?? NULL;
        if (!$subtypeId) {
          return;
        }

        $sourceId = $objectRef['id'] ?? NULL;
        if (!$sourceId) {
          return;
        }

        $collectionSource = EckEntity::get('Collection_Camp', FALSE)
          ->addWhere('id', '=', $sourceId)
          ->execute()->single();

        $collectionSourceCreatedDate = $collectionSource['created_date'] ?? NULL;

        $sourceTitle = $collectionSource['title'] ?? NULL;

        $year = date('Y', strtotime($collectionSourceCreatedDate));

        $stateId = self::getStateIdForSourceType($objectRef, $subtypeId, $sourceTitle);

        if (!$stateId) {
          return;
        }

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
        $newTitle = $year . '/' . $stateCode . '/' . $eventCode . '/' . $sourceId;
        $objectRef['title'] = $newTitle;

        // Save the updated title back to the Collection Camp entity.
        EckEntity::update('Collection_Camp')
          ->addWhere('id', '=', $sourceId)
          ->addValue('title', $newTitle)
          ->execute();
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
  public static function getStateIdForSourceType(array $objectRef, int $subtypeId, ?string $campTitle): ?int {

    $sourceStateFieldMapper = [
      'Collection_Camp' => 'Collection_Camp_Intent_Details.State',
      'Dropping_Center' => 'Dropping_Centre.State',
      'Institution_Collection_Camp' => 'Institution_Collection_Camp_Intent.State',
      'Goonj_Activities' => 'Goonj_Activities.State',
      'Institution_Dropping_Center' => 'Institution_Dropping_Center_Intent.State',
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
      $organizationId = $collectionCamp['Institution_Collection_Camp_Intent.Organization_Name.id'];
      $relationshipType = 'Institution POC of';
      $alternateType = 'Primary Institution POC of';
    }
    elseif ($subtypeName === 'Institution_Dropping_Center') {
      $organizationId = $collectionCamp['Institution_Dropping_Center_Intent.Organization_Name.id'];
      $relationshipType = 'Institution POC of';
      $alternateType = 'Secondary Institution POC of';
    }
    else {
      return $collectionCamp['Collection_Camp_Core_Details.Contact_Id'];
    }

    $relationships = Relationship::get(FALSE)
      ->addWhere('contact_id_a', '=', $organizationId)
      ->addWhere('relationship_type_id:name', '=', $relationshipType)
      ->execute();
    if (empty($relationships)) {
      $relationships = Relationship::get(FALSE)
        ->addWhere('contact_id_a', '=', $organizationId)
        ->addWhere('relationship_type_id:name', '=', $alternateType)
        ->execute();
    }

    return !empty($relationships) && isset($relationships[0]['contact_id_b'])
        ? $relationships[0]['contact_id_b']
        : $collectionCamp['Collection_Camp_Core_Details.Contact_Id'];
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
