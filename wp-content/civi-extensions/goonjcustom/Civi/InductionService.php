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

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => [
            ['volunteerCreated'],
            ['createInductionForVolunteer'],
            ['sendInductionEmailToVolunteer'],
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

    // The ASCII control character \x01 represents the "Start of Header".
    // It is used to separate values internally by CiviCRM for multiple subtypes.
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
   *
   */
  public static function createInductionForVolunteer(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'Address') {
      return FALSE;
    }

    \Civi::log()->info('Address created: ', [
      'id' => $objectId,
      'volunteer' => self::$volunteerId,
      'contactId' => $objectRef->contact_id,
      'isPrimary' => $objectRef->is_primary,
    ]);

    if (self::$volunteerId !== $objectRef->contact_id || !$objectRef->is_primary) {
      return FALSE;
    }

    $stateId = $objectRef->state_province_id;

    $officesFound = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addWhere('Goonj_Office_Details.Induction_Catchment', 'CONTAINS', $stateId)
      ->execute();

    $office = $officesFound->first();

    // @todo Implement fallback office logic here.
    $officeId = $office['id'];

    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $officeId)
      ->addWhere('relationship_type_id:name', '=', self::RELATIONSHIP_TYPE_NAME)
      ->execute();

    $coordinatorCount = $coordinators->count();

    // @todo Implement fallback coordinator logic here.
    if ($coordinatorCount > 1) {
      $randomIndex = rand(0, $coordinatorCount - 1);
      $coordinator = $coordinators->itemAt($randomIndex);
    }
    else {
      $coordinator = $coordinators->first();
    }

    $coordinatorId = $coordinator['contact_id_a'];

    $session = \CRM_Core_Session::singleton();
    $currentUserId = $session->get('userID') ?: self::$volunteerId;

    $sourceContactId = $currentUserId;
    $targetContactId = ($currentUserId === self::$volunteerId) ? $currentUserId : [self::$volunteerId];

    $placeholderActivityDate = self::getPlaceholderActivityDate();

    \Civi::log()->info('Before induction activity create: ', [
      'id' => $objectId,
      'source' => $sourceContactId,
      'target' => $targetContactId,
      'coordinator' => $coordinatorId,
      'officeId' => $officeId,
    ]);

    Activity::create(FALSE)
      ->addValue('activity_type_id:name', self::INDUCTION_ACTIVITY_TYPE_NAME)
      ->addValue('status_id:name', self::INDUCTION_DEFAULT_STATUS_NAME)
      ->addValue('source_contact_id', $sourceContactId)
      ->addValue('target_contact_id', $targetContactId)
      ->addValue('Induction_Fields.Assign', $coordinatorId)
      ->addValue('activity_date_time', $placeholderActivityDate)
      ->addValue('Induction_Fields.Goonj_Office', $officeId)
      ->execute();
  }

  /**
   * Get placeholder time for induction activity.
   *
   * Calculate the date and time 3 days from today at 11:00 AM.
   * If the resulting date is on a weekend (Saturday or Sunday), adjust to the next Monday at 11:00 AM.
   *
   * @return string The formatted date and time for 3 days later or the next Monday at 11 AM.
   */
  private static function getPlaceholderActivityDate() {
    $date = new \DateTime();
    $date->modify('+3 days');
    $dayOfWeek = $date->format('N');

    // If the resulting day is Saturday (6) or Sunday (7), move to next Monday.
    if ($dayOfWeek >= 6) {
      $date->modify('next monday');
    }

    // Set the time to 11:00 AM.
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
   *
   */
  public static function sendInductionEmailToVolunteer(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'Email' || !$objectId) {
      return;
    }

    if ($objectRef->contact_id !== self::$volunteerId) {
      return;
    }

    $template = MessageTemplate::get(FALSE)
      ->addWhere('msg_title', 'LIKE', 'New_Volunteer_Registration%')
      ->setLimit(1)
      ->execute()->single();

    $emailParams = [
      'contact_id' => self::$volunteerId,
      'template_id' => $template['id'],
      'cc' => self::$volunteerInductionAssigneeEmail,
    ];

    $result = civicrm_api3('Email', 'send', $emailParams);

  }

}
