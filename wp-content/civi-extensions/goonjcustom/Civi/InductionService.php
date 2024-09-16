<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Api4\Contact;
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

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => [
            ['volunteerCreated'],
            ['createInductionForVolunteer'],
            ['sendAdminNotificatationForVolunteerSignup'],
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

  }

  /**
   * Creates an induction for a volunteer.
   */
  public static function createInductionForVolunteer(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'Address') {
      return FALSE;
    }

    if (self::$volunteerId !== $objectRef->contact_id || !$objectRef->is_primary) {
      return FALSE;
    }

    $stateId = $objectRef->state_province_id;
    $officeId = self::getGoonjOfficeForVolunteer(self::$volunteerId, $stateId);
    $coordinatorId = self::getCoordinatorForOffice($officeId);

    $session = \CRM_Core_Session::singleton();
    $currentUserId = $session->get('userID') ?: self::$volunteerId;

    $sourceContactId = $currentUserId;
    $targetContactId = ($currentUserId === self::$volunteerId) ? $currentUserId : [self::$volunteerId];
    $placeholderActivityDate = self::getPlaceholderActivityDate();

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
   * Retrieves the relevant Goonj office based on state and volunteer ID.
   */
  public static function getGoonjOfficeForVolunteer(int $volunteerId, int $stateId) {
    $officesFound = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addWhere('Goonj_Office_Details.Induction_Catchment', 'CONTAINS', $stateId)
      ->execute();

    $office = $officesFound->first();

    // @todo Implement fallback office logic here if $office is not found.
    return $office['id'];
  }

  /**
   * Retrieves the coordinator for a given office.
   */
  public static function getCoordinatorForOffice(int $officeId) {
    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $officeId)
      ->addWhere('relationship_type_id:name', '=', self::RELATIONSHIP_TYPE_NAME)
      ->execute();

    $coordinatorCount = $coordinators->count();

    // @todo Implement fallback coordinator logic here if no coordinators are found.
    if ($coordinatorCount > 1) {
      $randomIndex = rand(0, $coordinatorCount - 1);
      return $coordinators->itemAt($randomIndex)['contact_id_a'];
    }

    return $coordinators->first()['contact_id_a'];
  }

  /**
   * Sends an admin notification for volunteer signup.
   */
  public static function sendAdminNotificatationForVolunteerSignup(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'Address') {
      return FALSE;
    }

    if (self::$volunteerId !== $objectRef->contact_id || !$objectRef->is_primary) {
      return FALSE;
    }

    $stateId = $objectRef->state_province_id;
    $officeId = self::getGoonjOfficeForVolunteer(self::$volunteerId, $stateId);
    $coordinatorId = self::getCoordinatorForOffice($officeId);

    self::sendAdminNotificatation($coordinatorId);
  }

  /**
   * Sends an admin notification email.
   */
  public static function sendAdminNotificatation($contactId) {
    try {
      $templateId = MessageTemplate::get(TRUE)
        ->addWhere('msg_title', '=', 'Admin volunteer sign up')
        ->execute()->single()['id'];

      $emailParams = [
        'contact_id' => $contactId,
        'template_id' => $templateId,
      ];

      $result = civicrm_api3('Email', 'send', $emailParams);
    }
    catch (\CiviCRM_API3_Exception $ex) {
      error_log("Exception caught while sending notification email: " . $ex->getMessage());
    }
  }

}
