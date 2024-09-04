<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Relationship;
use Civi\Api4\StateProvince;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class InductionService extends AutoSubscriber {

  const FALLBACK_OFFICE_NAME = 'Delhi';
  const RELATIONSHIP_TYPE_NAME = 'Induction Coordinator is';

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_custom' => 'setOfficeDetails',
    ];
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

    $coordinatorCount = $coordinators->count();

    $randomIndex = rand(0, $coordinatorCount - 1);
    $coordinator = $coordinators->itemAt($randomIndex);

    return $coordinator;
  }

}
