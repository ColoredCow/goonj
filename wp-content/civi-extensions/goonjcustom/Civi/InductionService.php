<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\MessageTemplate;
use Civi\Api4\Relationship;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class InductionService extends AutoSubscriber {
  const INDUCTION_ACTIVITY_TYPE_NAME = 'Induction';
  const INDUCTION_DEFAULT_STATUS_NAME = 'To be scheduled';
  const RELATIONSHIP_TYPE_NAME = 'Induction Coordinator of';

  private static $volunteerId = NULL;
  private static $volunteerInductionAssigneeEmail = NULL;
  private static $transitionedVolunteerId = NULL;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_pre' => [
        ['hasIndividualChangedToVolunteer'],
      ],
      '&hook_civicrm_post' => [
            ['volunteerCreated'],
            ['createInductionForVolunteer'],
            ['createInductionForTransitionedVolunteer'],
            ['sendInductionEmailToVolunteer'],
            ['sendInductionEmailForTransitionedVolunteer'],
      ],
      '&hook_civicrm_custom' => [
        ['volunteerInductionAssignee'],
      ],
    ];
  }

  /**
   *
   */
  public static function volunteerCreated(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'Individual') {
      return FALSE;
    }

    \Civi::log()->info('Individual created: ', [
      'id' => $objectId,
      'subtypes' => $objectRef->contact_sub_type,
    ]);

    $subTypes = $objectRef->contact_sub_type;

    if (empty($subTypes)) {
      return FALSE;
    }

    $subtypes = explode("\x01", $subTypes);
    $subtypes = array_filter($subtypes);

    if (!in_array('Volunteer', $subtypes)) {
      return FALSE;
    }

    self::$volunteerId = $objectId;

    \Civi::log()->info('Volunteer set: ', [
      'id' => self::$volunteerId,
    ]);
  }

  /**
   * Common logic for creating an induction.
   */
  private static function createInduction(int $contactId, int $stateId) {
    if (self::inductionExists($contactId)) {
      \Civi::log()->info('Induction already exists for contact', ['id' => $contactId]);
      return FALSE;
    }

    if (!$stateId) {
      return FALSE;
    }

    $office = self::findOfficeForState($stateId);
    if (!$office) {
      return FALSE;
    }

    $coordinatorId = self::findCoordinatorForOffice($office['id']);
    if (!$coordinatorId) {
      return FALSE;
    }

    $sourceContactId = self::getCurrentUserOrVolunteer($contactId);
    $targetContactId = ($sourceContactId === $contactId) ? $contactId : $contactId;

    $placeholderActivityDate = self::getPlaceholderActivityDate();

    \Civi::log()->info('Before induction activity create: ', [
      'source' => $sourceContactId,
      'target' => $targetContactId,
      'coordinator' => $coordinatorId,
      'officeId' => $office['id'],
    ]);

    Activity::create(FALSE)
      ->addValue('activity_type_id:name', self::INDUCTION_ACTIVITY_TYPE_NAME)
      ->addValue('status_id:name', self::INDUCTION_DEFAULT_STATUS_NAME)
      ->addValue('source_contact_id', $sourceContactId)
      ->addValue('target_contact_id', $targetContactId)
      ->addValue('Induction_Fields.Assign', $coordinatorId)
      ->addValue('activity_date_time', $placeholderActivityDate)
      ->addValue('Induction_Fields.Goonj_Office', $office['id'])
      ->execute();

    return TRUE;
  }

  /**
   * Handles induction creation for a volunteer.
   */
  public static function createInductionForVolunteer(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'Address' || self::$volunteerId !== $objectRef->contact_id || !$objectRef->is_primary) {
      return FALSE;
    }

    $stateId = $objectRef->state_province_id;

    if (!$stateId) {
      \Civi::log()->info('state not found', ['VolunteerId' => self::$volunteerId]);
    }
    self::createInduction(self::$volunteerId, $stateId);
  }

  /**
   * Get the current user ID or volunteer ID.
   */
  private static function getCurrentUserOrVolunteer($volunteerId) {
    $session = \CRM_Core_Session::singleton();
    return $session->get('userID') ?: $volunteerId;
  }

  /**
   * Find office based on state.
   */
  private static function findOfficeForState($stateId) {
    return Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addWhere('Goonj_Office_Details.Induction_Catchment', 'CONTAINS', $stateId)
      ->execute()->first();
  }

  /**
   * Find coordinator for office.
   */
  private static function findCoordinatorForOffice($officeId) {
    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $officeId)
      ->addWhere('relationship_type_id:name', '=', self::RELATIONSHIP_TYPE_NAME)
      ->execute();

    $coordinatorCount = $coordinators->count();

    return $coordinatorCount > 1
      ? $coordinators->itemAt(rand(0, $coordinatorCount - 1))['contact_id_a']
      : $coordinators->first()['contact_id_a'];
  }

  /**
   * Common logic to send an email.
   */
  private static function sendInductionEmail($contactId) {
    if (self::emailAlreadySent($contactId)) {
      \Civi::log()->info('Induction email already sent for contact', ['id' => $contactId]);
      return FALSE;
    }

    $template = MessageTemplate::get(FALSE)
      ->addWhere('msg_title', 'LIKE', 'New_Volunteer_Registration%')
      ->setLimit(1)
      ->execute()->single();

    if (!$template) {
      return FALSE;
    }

    $emailParams = [
      'contact_id' => $contactId,
      'template_id' => $template['id'],
      'cc' => self::$volunteerInductionAssigneeEmail,
    ];

    civicrm_api3('Email', 'send', $emailParams);
    return TRUE;
  }

  /**
   * Handles sending induction email to a volunteer.
   */
  public static function sendInductionEmailToVolunteer(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'Email' || !$objectId || $objectRef->contact_id !== self::$volunteerId) {
      return;
    }

    self::sendInductionEmail(self::$volunteerId);
  }

  /**
   * Handles sending induction email to an individual.
   */
  public static function sendInductionEmailForTransitionedVolunteer(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'edit' || $objectName !== 'Individual' || (int) self::$transitionedVolunteerId !== (int) $objectRef->id) {
      return FALSE;
    }

    self::sendInductionEmail(self::$transitionedVolunteerId);
  }

  /**
   * Get placeholder time for induction activity.
   */
  private static function getPlaceholderActivityDate() {
    $date = new \DateTime();
    $date->modify('+3 days');
    $dayOfWeek = $date->format('N');

    if ($dayOfWeek >= 6) {
      $date->modify('next monday');
    }

    $date->setTime(11, 0);
    return $date->format('Y-m-d H:i:s');
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
  public static function volunteerInductionAssignee($op, $groupID, $entityID, &$params) {
    if ($op !== 'create') {
      return;
    }

    if (!($inductionsFields = self::findInductionOfficeFields($params))) {
      return;
    }

    if (!$inductionsFields['Assign']) {
      return;
    }

    $assignee = Contact::get(FALSE)
      ->addSelect('email.email')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('id', '=', $inductionsFields['Assign']['value'])
      ->addWhere('email.is_primary', '=', TRUE)
      ->setLimit(1)
      ->execute()->single();

    self::$volunteerInductionAssigneeEmail = $assignee['email.email'];

  }

  /**
   *
   */
  private static function findInductionOfficeFields(array $array) {
    $filteredItems = array_filter($array, fn($item) => $item['entity_table'] === 'civicrm_activity');

    if (empty($filteredItems)) {
      return FALSE;
    }

    $inductionOfficeFields = CustomField::get(FALSE)
      ->addSelect('name')
      ->addWhere('custom_group_id:name', '=', 'Induction_Fields')
      ->addWhere('name', 'IN', ['Goonj_Office', 'Assign'])
      ->execute();

    if ($inductionOfficeFields->count() === 0) {
      return FALSE;
    }

    $inductionOfficeFieldValues = [];

    foreach ($inductionOfficeFields as $field) {
      $fieldIndex = array_search(TRUE, array_map(fn($item) =>
      $item['entity_table'] === 'civicrm_activity' &&
      $item['custom_field_id'] == $field['id'],
      $filteredItems
      ));

      $inductionOfficeFieldValues[$field['name']] = $fieldIndex !== FALSE ? $filteredItems[$fieldIndex] : FALSE;
    }

    return $inductionOfficeFieldValues;
  }

  /**
   * Check if induction activity already exists for the contact.
   */
  private static function inductionExists($contactId) {
    $inductionActivity = Activity::get(FALSE)
      ->addWhere('activity_type_id:name', '=', self::INDUCTION_ACTIVITY_TYPE_NAME)
      ->addWhere('status_id:name', 'IN', ['Scheduled', 'Completed', 'To be scheduled', 'Cancelled'])
      ->addWhere('target_contact_id', '=', $contactId)
      ->setLimit(1)
      ->execute();

    return $inductionActivity->count() > 0;
  }

  /**
   * Check if induction email has already been sent.
   * Adjust the logic based on actual email activity type.
   */
  private static function emailAlreadySent($contactId) {

    $volunteerEmailActivity = Activity::get(FALSE)
      ->addWhere('activity_type_id:name', '=', 'Email')
      ->addWhere('subject', 'LIKE', '%Volunteering with Goonj%')
      ->addWhere('target_contact_id', '=', $contactId)
      ->setLimit(1)
      ->execute();

    return $volunteerEmailActivity->count() > 0;
  }

  /**
   *
   */
  public static function hasIndividualChangedToVolunteer($op, $objectName, $id, &$params) {
    if ($op !== 'edit' || $objectName !== 'Individual') {
      return FALSE;
    }

    $newSubtypes = $params['contact_sub_type'];

    // Check if "Volunteer" is present in the contact_sub_type array.
    if (!in_array('Volunteer', $newSubtypes)) {
      \Civi::log()->info('Volunteer not found in subtypes, returning.');
      // Exit the function if "Volunteer" is not present.
      return;
    }

    $contacts = Contact::get(FALSE)
      ->addSelect('contact_sub_type')
      ->addWhere('id', '=', $id)
      ->execute()->single();

    if ($contacts['contact_sub_type' === 'Volunteer']) {
      return;
    }
    self::$transitionedVolunteerId = $contacts['id'];
  }

  /**
   *
   */
  public static function createInductionForTransitionedVolunteer(string $op, string $objectName, int $objectId, &$objectRef) {

    if ($op !== 'edit' || $objectName !== 'Individual' || (int) self::$transitionedVolunteerId !== (int) $objectRef->id) {
      return FALSE;
    }

    $contact = Contact::get(FALSE)
      ->addSelect('address.state_province_id')
      ->addJoin('Address AS address', 'LEFT')
      ->addWhere('id', '=', $objectId)
      ->execute()->single();

    $stateId = $contact['address.state_province_id'];
    if (!$stateId) {
      return FALSE;
    }

    self::createInduction(self::$transitionedVolunteerId, $stateId);
  }

}
