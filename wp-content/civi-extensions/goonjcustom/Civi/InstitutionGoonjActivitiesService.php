<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Api4\OptionValue;
use Civi\Api4\Relationship;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\CollectionSource;
use Civi\Traits\QrCodeable;

/**
 *
 */
class InstitutionGoonjActivitiesService extends AutoSubscriber {
  use QrCodeable;
  use CollectionSource;
  const ENTITY_SUBTYPE_NAME = 'Institution_Goonj_Activities';
  const ENTITY_NAME = 'Collection_Camp';
  const FALLBACK_OFFICE_NAME = 'Delhi';
  private static $instituteGoonjActivitiesAddress = NULL;
  private static $institutePocAddress = NULL;
  const MATERIAL_RELATIONSHIP_TYPE_NAME = 'Material Management Team of';
  const Institution_Goonj_Activities_INTENT_FB_NAME = 'afformInstitutionGoonjActivitiesIntent';
  private static $addressAdded = FALSE;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_pre' => [
        ['assignChapterGroupToIndividual'],
        ['generateInstitutionGoonjActivitiesQr'],
        ['createActivityForInstitutionGoonjActivityCollectionCamp'],
      ],
      '&hook_civicrm_custom' => [
        ['setOfficeDetails'],
      ],
      '&hook_civicrm_tabset' => 'institutionGoonjActivitiesTabset',
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
    if ($op !== 'create' || $objectName !== 'Eck_Collection_Camp' || empty($objectRef['title']) || $objectRef['title'] !== 'Institution Goonj Activities') {
      return FALSE;
    }

    $stateId = $objectRef['Institution_Goonj_Activities.State'];
    $contactId = $objectRef['Institution_Goonj_Activities.Institution_POC'];
    $organizationId = $objectRef['Institution_Goonj_Activities.Organization_Name'];

    if (!$stateId || !$contactId) {
      \Civi::log()->info("Missing Contact ID and State ID");
      return FALSE;
    }
    $groupId = self::getChapterGroupForState($stateId);

    if ($groupId) {
      self::addContactToGroup($contactId, $groupId);
      if ($organizationId) {
        self::addContactToGroup($organizationId, $groupId);
      }
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
    }
    catch (Exception $e) {
      \Civi::log()->error("Error adding contact_id: $contactId to group_id: $groupId. Exception: " . $e->getMessage());
    }
  }

  /**
   *
   */
  private static function findStateField(array $array) {
    $institutionGoonjActivitiesStateField = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'state')
      ->addWhere('custom_group_id:name', '=', 'Institution_Goonj_Activities')
      ->execute()
      ->first();

    if (!$institutionGoonjActivitiesStateField) {
      return FALSE;
    }

    $stateFieldId = $institutionGoonjActivitiesStateField['id'];

    foreach ($array as $item) {
      if (isset($item['entity_table']) && $item['entity_table'] === 'civicrm_eck_collection_camp' &&
          isset($item['custom_field_id']) && $item['custom_field_id'] === $stateFieldId) {
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
  public static function assignCoordinatorByRelationshipType($stateOfficeId, $registrationType, $collectionCampId) {
    // Define the mapping of registration categories to relationship type names.
    $relationshipTypeMap = [
      'A_Corporate_organisation' => 'Corporate Organisation Coordinator of',
      'A_School' => 'School Coordinator of',
      'A_College_University' => 'University/College Coordinator of',
    ];

    $registrationCategorySelection = $registrationType['Institution_Goonj_Activities.You_wish_to_register_as:name'];

    $registrationCategorySelection = trim($registrationCategorySelection);

    if (array_key_exists($registrationCategorySelection, $relationshipTypeMap)) {
      $relationshipTypeName = $relationshipTypeMap[$registrationCategorySelection];
    }
    else {
      $relationshipTypeName = 'Other Entities Coordinator of';
    }

    // Retrieve the coordinators for the selected relationship type.
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

    // Assign the coordinator to the collection camp.
    $res = EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Institution_Goonj_Activities.Coordinating_Urban_Poc', $coordinator['contact_id_a'])
      ->addWhere('id', '=', $collectionCampId)
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

    $institutionCollectionCampId = $stateField['entity_id'];

    if (!$stateId) {
      \CRM_Core_Error::debug_log_message('Cannot assign Goonj Office to institution collection camp: ' . $institutionCollectionCampId);
      return FALSE;
    }

    $officesFound = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addWhere('Goonj_Office_Details.Institution_Catchment', 'CONTAINS', $stateId)
      ->execute();

    $stateOffice = $officesFound->first();

    if (!$stateOffice) {
      $stateOffice = self::getFallbackOffice();
    }

    $stateOfficeId = $stateOffice['id'];
    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Institution_Goonj_Activities.Goonj_Office', $stateOfficeId)
      ->addWhere('id', '=', $institutionCollectionCampId)
      ->execute();

    $registrationType = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Institution_Goonj_Activities.You_wish_to_register_as:name')
      ->addWhere('id', '=', $entityID)
      ->execute()->single();

    return self::assignCoordinatorByRelationshipType($stateOfficeId, $registrationType, $institutionCollectionCampId);

  }

  /**
   *
   */
  public static function generateInstitutionGoonjActivitiesQr(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName !== 'Eck_Collection_Camp' || !$objectId || !self::isCurrentSubtype($objectRef)) {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';
    if (!$newStatus) {
      return;
    }

    $collectionCamp = EckEntity::get('Collection_Camp', TRUE)
      ->addSelect('Collection_Camp_Core_Details.Status')
      ->addWhere('id', '=', $objectId)
      ->execute()->first();

    $currentStatus = $collectionCamp['Collection_Camp_Core_Details.Status'];
    $collectionCampId = $collectionCamp['id'];

    // Check for status change.
    if ($currentStatus !== $newStatus && $newStatus === 'authorized') {
      self::generateInstitutionGoonjActivitiesQrCode($collectionCampId);
    }
  }

  /**
   *
   */
  private static function generateInstitutionGoonjActivitiesQrCode($id) {
    $baseUrl = \CRM_Core_Config::singleton()->userFrameworkBaseURL;
    $data = "{$baseUrl}actions/institution-goonj-activities/{$id}";

    $saveOptions = [
      'customGroupName' => 'Collection_Camp_QR_Code',
      'customFieldName' => 'QR_Code',
    ];

    self::generateQrCode($data, $id, $saveOptions);
  }

  /**
   *
   */
  private static function isViewingIntitutionGoonjActivities($tabsetName, $context) {
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
   *
   */
  public static function institutionGoonjActivitiesTabset($tabsetName, &$tabs, $context) {
    if (!self::isViewingIntitutionGoonjActivities($tabsetName, $context)) {
      return;
    }

    $tabConfigs = [
      'activities' => [
        'title' => ts('Activities'),
        'module' => 'afsearchGoonjAllInstitutionActivity',
        'directive' => 'afsearch-goonj-all-institution-activity',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'logistics' => [
        'title' => ts('Logistics'),
        'module' => 'afsearchInstitutionGoonjActivitiesLogistics',
        'directive' => 'afsearch-institution-goonj-activities-logistics',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'campOutcome' => [
        'title' => ts('Outcome'),
        'module' => 'afsearchInstitutionGoonjActivitiesOutcomeView',
        'directive' => 'afsearch-institution-goonj-activities-outcome-view',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'campFeedback' => [
        'title' => ts('Volunteer Feedback'),
        'module' => 'afsearchInstitutionGoonjActivityVolunteerFeedback',
        'directive' => 'afsearch-institution-goonj-activity-volunteer-feedback',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'attendeeFeedback' => [
        'title' => ts('Attendee Feedback'),
        'module' => 'afsearchInstitutionGoonjActivityAttendeeFeedbacks',
        'directive' => 'afsearch-institution-goonj-activity-attendee-feedbacks',
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
  public static function createActivityForInstitutionGoonjActivityCollectionCamp(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName != 'Eck_Collection_Camp' || self::getEntitySubtypeName($objectId) !== self::ENTITY_SUBTYPE_NAME) {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';

    if (!$newStatus || !$objectId) {
      return;
    }

    $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Institution_Goonj_Activities.Institution_POC', 'title')
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

    $activities = $objectRef['Institution_Goonj_Activities.How_do_you_want_to_engage_with_Goonj_'];
    $startDate = $objectRef['Institution_Goonj_Activities.Start_Date'];
    $endDate = $objectRef['Institution_Goonj_Activities.End_Date'];
    $initiator = $objectRef['Institution_Goonj_Activities.Institution_POC'];

    foreach ($activities as $activityName) {
      // Check if the activity is 'Others'.
      if ($activityName == 'Other') {
        $otherActivity = $objectRef['Institution_Goonj_Activities.Other_Activity_Details'] ?? '';

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
        ->addWhere('name', '=', 'Institution_Goonj_Activities')
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
  public static function sendInsitutionActivityLogisticsEmail($collectionCamp) {
    try {
      $campId = $collectionCamp['id'];
      $activityCode = $collectionCamp['title'];
      $activityOffice = $collectionCamp['Institution_Goonj_Activities.Goonj_Office'];
      $activityAddress = $collectionCamp['Institution_Goonj_Activities.Where_do_you_wish_to_organise_the_activity_'];
      $activityAttendedById = $collectionCamp['Logistics_Coordination.Camp_to_be_attended_by'];
      $logisticEmailSent = $collectionCamp['Logistics_Coordination.Email_Sent'];
      $outcomeFormLink = $collectionCamp['Institution_Goonj_Activities.Select_Goonj_POC_Attendee_Outcome_Form'];

      $startDate = new \DateTime($collectionCamp['Institution_Goonj_Activities.Start_Date']);

      $today = new \DateTimeImmutable();
      $endOfToday = $today->setTime(23, 59, 59);

      if (TRUE) {
        $campAttendedBy = Contact::get(FALSE)
          ->addSelect('email.email', 'display_name')
          ->addJoin('Email AS email', 'LEFT')
          ->addWhere('id', '=', $activityAttendedById)
          ->execute()->single();

        $attendeeEmail = $campAttendedBy['email.email'];
        $attendeeName = $campAttendedBy['display_name'];
        $from = HelperService::getDefaultFromEmail();

        if (!$attendeeEmail) {
          throw new \Exception('Attendee email missing');
        }

        $mailParams = [
          'subject' => 'Goonj Activity Notification: ' . $activityCode . ' at ' . $activityAddress,
          'from' => $from,
          'toEmail' => $attendeeEmail,
          'replyTo' => $from,
          'html' => self::getLogisticsEmailHtml($attendeeName, $campId, $activityAttendedById, $activityOffice, $activityCode, $activityAddress, $outcomeFormLink),
        ];

        $emailSendResult = \CRM_Utils_Mail::send($mailParams);

        if ($emailSendResult) {
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
  private static function getLogisticsEmailHtml($contactName, $collectionCampId, $campAttendedById, $collectionCampGoonjOffice, $campCode, $campAddress, $outcomeFormLink) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    // Construct the full URLs for the forms.
    $campOutcomeFormUrl = $homeUrl . $outcomeFormLink . '#?Eck_Collection_Camp1=' . $collectionCampId . '&Camp_Outcome.Filled_By=' . $campAttendedById;

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

  /**
   *
   */
  public static function getInstitutionPocActivitiesFeedbackEmailHtml($collectionCamp) {

    try {
      $endDate = new \DateTime($collectionCamp['Institution_Goonj_Activities.End_Date']);
      $collectionCampId = $collectionCamp['id'];
      $endDateFormatted = $endDate->format('Y-m-d');
      $today = new \DateTime();
      $today->setTime(23, 59, 59);
      $todayFormatted = $today->format('Y-m-d');
      $feedbackEmailSent = $collectionCamp['Logistics_Coordination.Feedback_Email_Sent'];
      $initiatorId = $collectionCamp['Institution_Goonj_Activities.Institution_POC'];

      $campAddress = $collectionCamp['Institution_Goonj_Activities.Where_do_you_wish_to_organise_the_activity_'];
      $volunteerFeedbackForm = $collectionCamp['Institution_Goonj_Activities.Select_Volunteer_Feedback_Form'] ?? NULL;

      // Get recipient email and name.
      $campAttendedBy = Contact::get(TRUE)
        ->addSelect('email.email', 'display_name')
        ->addJoin('Email AS email', 'LEFT')
        ->addWhere('id', '=', $initiatorId)
        ->execute()->single();

      $contactEmailId = $campAttendedBy['email.email'];
      $organizingContactName = $campAttendedBy['display_name'];
      $from = HelperService::getDefaultFromEmail();

      // Send email if the end date is today or earlier.
      if (!$feedbackEmailSent && $endDateFormatted <= $todayFormatted) {
        $mailParams = [
          'subject' => 'Thank You for Organizing the Goonj Activity! Share Your Feedback.',
          'from' => $from,
          'toEmail' => $contactEmailId,
          'replyTo' => $from,
          'html' => self::getInstitutionPocFeedbackEmailHtml($organizingContactName, $collectionCampId, $campAddress, $volunteerFeedbackForm),
        ];
        $feedbackEmailSendResult = \CRM_Utils_Mail::send($mailParams);

        if ($feedbackEmailSendResult) {
          EckEntity::update('Collection_Camp', TRUE)
            ->addValue('Logistics_Coordination.Feedback_Email_Sent', 1)
            ->addWhere('id', '=', $collectionCampId)
            ->execute();
        }
      }

    }
    catch (\Exception $e) {
      \Civi::log()->error("Error in sendVolunteerEmail for $campId " . $e->getMessage());
    }

  }

  /**
   *
   */
  private static function getInstitutionPocFeedbackEmailHtml($organizingContactName, $collectionCampId, $campAddress, $volunteerFeedbackForm) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();

    // URL for the volunteer feedback form.
    $campVolunteerFeedback = $homeUrl . $volunteerFeedbackForm . '#?Eck_Collection_Camp1=' . $collectionCampId;

    $html = "
      <p>Dear $organizingContactName,</p>
      <p>Thank you for stepping up and organising the recent goonj activity  at <strong>$campAddress</strong>! Your time, effort, and enthusiasm made all the difference, and we hope that it was a meaningful effort for you as well.</p>
      <p>To help us improve, we’d love to hear your thoughts and experiences. Kindly take a few minutes to fill out our feedback form. Your input will be valuable to us:</p>
      <p><a href=\"$campVolunteerFeedback\">Feedback Form Link</a></p>
      <p>Feel free to share any highlights, suggestions, or challenges you faced. We're eager to learn how we can make it better together!</p>
      <p>We look forward to continuing this journey together!</p>
      <p>Warm Regards,<br>Team Goonj</p>";

    return $html;
  }

}