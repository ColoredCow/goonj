<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Email;
use Civi\Api4\Relationship;
use Civi\Api4\OptionValue;
use Civi\Api4\Activity;
use Civi\Api4\StateProvince;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\CollectionSource;
use Civi\Afform\Event\AfformSubmitEvent;
use Civi\Traits\QrCodeable;
use Civi\Api4\Utils\CoreUtil;
use Civi\CollectionCampService;

/**
 *
 */
class GoonjActivitiesService extends AutoSubscriber {
  use QrCodeable;
  use CollectionSource;

  const ENTITY_NAME = 'Collection_Camp';
  const ENTITY_SUBTYPE_NAME = 'Goonj_Activities';
  const GOONJ_ACTIVITIES_INTENT_FB_NAME = 'afformGoonjActivitiesIndividualIntentForm';
  const RELATIONSHIP_TYPE_NAME = 'Goonj Activities Coordinator of';
  private static $goonjActivitiesAddress = NULL;
  const FALLBACK_OFFICE_NAME = 'Delhi';

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_fieldOptions' => 'setIndianStateOptions',
      'civi.afform.submit' => [
        ['setGoonjActivitiesAddress', 9],
        ['setActivitiesVolunteersAddress', 8]
      ],
      '&hook_civicrm_custom' => [
        ['setOfficeDetails'],
        ['linkInductionWithGoonjActivities'],
        // ['mailNotificationToMmt'],
      ],
    ];
  }

  /**
   *
   */
  public static function setIndianStateOptions(string $entity, string $field, array &$options, array $params) {
    if ($entity !== 'Eck_Collection_Camp') {
      return;
    }

    $customStateFields = CustomField::get(FALSE)
      ->addWhere('custom_group_id:name', '=', 'Goonj_Activities')
      ->addWhere('name', '=', 'State')
      ->execute();

    $stateField = $customStateFields->first();

    $stateFieldId = $stateField['id'];

    if ($field !== "custom_$stateFieldId") {
      return;
    }

    $activeIndianStates = StateProvince::get(FALSE)
      ->addWhere('country_id.iso_code', '=', 'IN')
      ->addOrderBy('name', 'ASC')
      ->execute();

    $stateOptions = [];
    foreach ($activeIndianStates as $state) {
      if ($state['is_active']) {
        $stateOptions[$state['id']] = $state['name'];
      }
    }

    $options = $stateOptions;

  }

    /**
   *
   */
  public static function setGoonjActivitiesAddress(AfformSubmitEvent $event) {
    $afform = $event->getAfform();
    $formName = $afform['name'];
    \Civi::log()->info('formName', ['formName'=>$formName]);

    if ($formName !== self::GOONJ_ACTIVITIES_INTENT_FB_NAME) {
      return;
    }

    $entityType = $event->getEntityType();

    if ($entityType !== 'Eck_Collection_Camp') {
      return;
    }

    $records = $event->records;

    foreach ($records as $record) {
      $fields = $record['fields'];

      self::$goonjActivitiesAddress = [
        'location_type_id' => 3,
        'state_province_id' => $fields['Goonj_Activities.State'],
      // India.
        'country_id' => 1101,
        'street_address' => $fields['Goonj_Activities.Where_do_you_wish_to_organise_the_activity_'],
        'city' => $fields['Goonj_Activities.City'],
        'postal_code' => $fields['Goonj_Activities.Postal_Code'],
        'is_primary' => 1,
      ];
    }
  }

    /**
   *
   */
  public static function setActivitiesVolunteersAddress(AfformSubmitEvent $event) {
    $afform = $event->getAfform();
    $formName = $afform['name'];

    if ($formName !== self::GOONJ_ACTIVITIES_INTENT_FB_NAME) {
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

      $event->records[$index]['joins']['Address'][] = self::$goonjActivitiesAddress;
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
  public static function linkInductionWithGoonjActivities($op, $groupID, $entityID, &$params) {
    if ($op !== 'create' || self::getEntitySubtypeName($entityID) !== self::ENTITY_SUBTYPE_NAME) {
      return;
    }
    \Civi::log()->info('check');

    if (!($contactId = CollectionCampService::findCollectionCampInitiatorContact($params))) {
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
    \Civi::log()->info('check2323');

    if (!($stateField = self::findStateField($params))) {
      return;
    }
    \Civi::log()->info('stateField', ['stateField'=>$stateField]);

    $stateId = $stateField['value'];
    \Civi::log()->info('stateId', ['stateId'=>$stateId]);
    $goonjActivitiesId = $stateField['entity_id'];
    \Civi::log()->info('goonjActivitiesId', ['goonjActivitiesId'=>$goonjActivitiesId]);


    if (!$stateId) {
      \CRM_Core_Error::debug_log_message('Cannot assign Goonj Office to goonj activities: ' . $goonjActivitiesId);
      \CRM_Core_Error::debug_log_message('No state provided on the intent for goonj activities: ' . $goonjActivitiesId);
      return FALSE;
    }

    $officesFound = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addWhere('Goonj_Office_Details.Collection_Camp_Catchment', 'CONTAINS', $stateId)
      ->execute();
    \Civi::log()->info('officesFound', ['officesFound'=>$officesFound]);

    $stateOffice = $officesFound->first();

    // If no state office is found, assign the fallback state office.
    if (!$stateOffice) {
      $stateOffice = self::getFallbackOffice();
      \Civi::log()->info('stateOffice', ['stateOffice'=>$stateOffice]);
    }

    $stateOfficeId = $stateOffice['id'];

    $rs = EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Collection_Camp_Intent_Details.Goonj_Office', $stateOfficeId)
      ->addWhere('id', '=', $goonjActivitiesId)
      ->execute();
    \Civi::log()->info('rs', ['rs'=>$rs]);

    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $stateOfficeId)
      ->addWhere('relationship_type_id:name', '=', self::RELATIONSHIP_TYPE_NAME)
      ->addWhere('is_current', '=', TRUE)
      ->execute();
    \Civi::log()->info('coordinators', ['coordinators'=>$coordinators]);

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

    $rs1 = EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Collection_Camp_Intent_Details.Coordinating_Urban_POC', $coordinatorId)
      ->addWhere('id', '=', $goonjActivitiesId)
      ->execute();
    \Civi::log()->info('rs1', ['rs1'=>$rs1]);

    return TRUE;

  }

    /**
   *
   */
  private static function findStateField(array $array) {
    $stateFieldId = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'State')
      ->addWhere('custom_group_id:name', '=', 'Goonj_Activities')
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
}
