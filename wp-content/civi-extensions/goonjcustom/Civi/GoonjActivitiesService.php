<?php

namespace Civi;

use Civi\Afform\Event\AfformSubmitEvent;
use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;
use Civi\Api4\Relationship;
use Civi\Api4\StateProvince;
use Civi\Api4\Utils\CoreUtil;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\CollectionSource;
use Civi\Traits\QrCodeable;

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
  private static $fromAddress = NULL;
  const FALLBACK_OFFICE_NAME = 'Delhi';

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_fieldOptions' => 'setIndianStateOptions',
      'civi.afform.submit' => [
        ['setGoonjActivitiesAddress', 9],
        ['setActivitiesVolunteersAddress', 8],
      ],
      '&hook_civicrm_custom' => [
        ['setOfficeDetails'],
        ['linkInductionWithGoonjActivities'],
        // ['mailNotificationToMmt'],
      ],
      '&hook_civicrm_tabset' => 'goonjActivitiesTabset',
      '&hook_civicrm_pre' => [
        ['generateGoonjActivitiesQr'],
        ['createActivityForGoonjActivityCollectionCamp'],
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

    if (!($contactId = self::findGoonjActivitiesInitiatorContact($params))) {
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

    if (!($stateField = self::findStateField($params))) {
      return;
    }

    $stateId = $stateField['value'];
    $goonjActivitiesId = $stateField['entity_id'];

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

    $stateOffice = $officesFound->first();

    // If no state office is found, assign the fallback state office.
    if (!$stateOffice) {
      $stateOffice = self::getFallbackOffice();
    }

    $stateOfficeId = $stateOffice['id'];

    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Goonj_Activities.Goonj_Office', $stateOfficeId)
      ->addWhere('id', '=', $goonjActivitiesId)
      ->execute();

    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $stateOfficeId)
      ->addWhere('relationship_type_id:name', '=', self::RELATIONSHIP_TYPE_NAME)
      ->execute();
    $RELATIONSHIP_TYPE_NAME = self::RELATIONSHIP_TYPE_NAME;

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
      ->addValue('Goonj_Activities.Coordinating_Urban_Poc', $coordinatorId)
      ->addWhere('id', '=', $goonjActivitiesId)
      ->execute();

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
      ->first()['id'];

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

  /**
   *
   */
  private static function findGoonjActivitiesInitiatorContact(array $array) {
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
  public static function goonjActivitiesTabset($tabsetName, &$tabs, $context) {
    if (!self::isViewingGoonjActivities($tabsetName, $context)) {
      return;
    }

    $tabConfigs = [
      'activities' => [
        'title' => ts('Activities'),
        'module' => 'afsearchCollectionCampActivity',
        'directive' => 'afsearch-collection-camp-activity',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin'],
      ],
      'logistics' => [
        'title' => ts('Logistics'),
        'module' => 'afsearchGoonjActivitiesLogistics',
        'directive' => 'afsearch-goonj-activities-logistics',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin'],
      ],
      'eventVolunteers' => [
        'title' => ts('Event Volunteers'),
        'module' => 'afsearchEventVolunteer',
        'directive' => 'afsearch-event-volunteer',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin'],
      ],
      'campOutcome' => [
        'title' => ts('Outcome'),
        'module' => 'afsearchGoonjActivitiesOutcomeView',
        'directive' => 'afsearch-goonj-activities-outcome-view',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin'],
      ],
      'campFeedback' => [
        'title' => ts('Volunteer Feedback'),
        'module' => 'afsearchVolunteerFeedback',
        'directive' => 'afsearch-volunteer-feedback',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin'],
      ],
      'monetaryContribution' => [
        'title' => ts('Monetary Contribution'),
        'module' => 'afsearchMonetaryContribution',
        'directive' => 'afsearch-monetary-contribution',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['account_team'],
      ],
      'monetaryContributionForUrbanOps' => [
        'title' => ts('Monetary Contribution'),
        'module' => 'afsearchMonetaryContributionForUrbanOps',
        'directive' => 'afsearch-monetary-contribution-for-urban-ops',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin'],
      ],
    ];

    foreach ($tabConfigs as $key => $config) {
      $isAdmin = \CRM_Core_Permission::check('admin');
      if ($key == 'monetaryContributionForUrbanOps' && $isAdmin) {
        continue;
      }

      $hasPermission = \CRM_Core_Permission::check($config['permissions']);
      if (!$hasPermission) {
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
  private static function isViewingGoonjActivities($tabsetName, $context) {
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
  public static function generateGoonjActivitiesQr(string $op, string $objectName, $objectId, &$objectRef) {
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
        self::generateGoonjActivitiesQrCode($collectionCampId);

      }
    }
  }

  /**
   *
   */
  private static function generateGoonjActivitiesQrCode($id) {
    $baseUrl = \CRM_Core_Config::singleton()->userFrameworkBaseURL;
    $data = "{$baseUrl}actions/goonj-activities/{$id}";

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
  public static function createActivityForGoonjActivityCollectionCamp(string $op, string $objectName, $objectId, &$objectRef) {
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

    // // Check for status change.
    // // Access the id within the decoded data.
    $campId = $objectRef['id'];

    if ($campId === NULL) {
      return;
    }

    $activities = $objectRef['Goonj_Activities.How_do_you_want_to_engage_with_Goonj_'];
    $startDate = $objectRef['Goonj_Activities.Start_Date'];
    $endDate = $objectRef['Goonj_Activities.End_Date'];
    $initiator = $objectRef['Collection_Camp_Core_Details.Contact_Id'];

    foreach ($activities as $activityName) {
      // Check if the activity is 'Others'.
      if ($activityName == 'Other') {
        $otherActivity = $objectRef['Goonj_Activities.Other_Activity_Details'] ?? '';

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
        ->addWhere('name', '=', 'Goonj_Activities')
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
  public static function sendActivityLogisticsEmail($collectionCamp) {
    try {
      $campId = $collectionCamp['id'];
      $activityCode = $collectionCamp['title'];
      $activityOffice = $collectionCamp['Goonj_Activities.Goonj_Office'];
      $activityAddress = $collectionCamp['Goonj_Activities.Where_do_you_wish_to_organise_the_activity_'];
      $activityAttendedById = $collectionCamp['Logistics_Coordination.Camp_to_be_attended_by'];
      $logisticEmailSent = $collectionCamp['Logistics_Coordination.Email_Sent'];

      $startDate = new \DateTime($collectionCamp['Goonj_Activities.Start_Date']);


      $today = new \DateTimeImmutable();
      $endOfToday = $today->setTime(23, 59, 59);


      if (!$logisticEmailSent && $startDate <= $endOfToday) {
        $campAttendedBy = Contact::get(FALSE)
          ->addSelect('email.email', 'display_name')
          ->addJoin('Email AS email', 'LEFT')
          ->addWhere('id', '=', $activityAttendedById)
          ->execute()->single();

        $attendeeEmail = $campAttendedBy['email.email'];
        $attendeeName = $campAttendedBy['display_name'];

        if (!$attendeeEmail) {
          throw new \Exception('Attendee email missing');
        }

        $mailParams = [
          'subject' => 'Goonj Activity Notification: ' . $activityCode . ' at ' . $activityAddress,
          'from' => self::getFromAddress(),
          'toEmail' => $attendeeEmail,
          'replyTo' => self::getFromAddress(),
          'html' => self::getLogisticsEmailHtml($attendeeName, $campId, $activityAttendedById, $activityOffice, $activityCode, $activityAddress),
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
  private static function getFromAddress() {
    if (!self::$fromAddress) {
      [$defaultFromName, $defaultFromEmail] = \CRM_Core_BAO_Domain::getNameAndEmail();
      self::$fromAddress = "\"$defaultFromName\" <$defaultFromEmail>";
    }
    return self::$fromAddress;
  }

    /**
   *
   */
  private static function getLogisticsEmailHtml($contactName, $collectionCampId, $campAttendedById, $collectionCampGoonjOffice, $campCode, $campAddress) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    // Construct the full URLs for the forms.
    // $campVehicleDispatchFormUrl = $homeUrl . 'camp-vehicle-dispatch-form/#?Camp_Vehicle_Dispatch.Collection_Camp=' . $collectionCampId . '&Camp_Vehicle_Dispatch.Filled_by=' . $campAttendedById . '&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent=' . $collectionCampGoonjOffice . '&Eck_Collection_Camp1=' . $collectionCampId;

    $campOutcomeFormUrl = $homeUrl . '/goonj-activity-outcome-form/#?Eck_Collection_Camp1=' . $collectionCampId . '&Camp_Outcome.Filled_By=' . $campAttendedById;

    $html = "
    <p>Dear $contactName,</p>
    <p>Thank you for attending the goonj activity <strong>$campCode</strong> at <strong>$campAddress</strong>. Their is one forms that require your attention during and after the goonj activity:</p>
    <ol>
        Please complete this form from the goonj activity location once the goonj activity ends.</li>
        <li><a href=\"$campOutcomeFormUrl\">Goonj Activity Outcome Form</a><br>
        This feedback form should be filled out after the goonj activity/session ends, once you have an overview of the event's outcomes.</li>
    </ol>
    <p>We appreciate your cooperation.</p>
    <p>Warm Regards,<br>Urban Relations Team</p>";

    return $html;
  }
}
