<?php

namespace Civi;

use Civi\Afform\Event\AfformSubmitEvent;
use Civi\Api4\Activity;
use Civi\Core\Service\AutoSubscriber;

/**
 * Handles Urban External Meeting Session form submissions.
 *
 * When the afformUrbanExternalMeetingSession form is submitted:
 * - If an individual/POC contact is selected, a meeting activity is created in their profile.
 * - If an institution is selected, a meeting activity is also created in the institution's profile.
 */
class UrbanExternalMeetingService extends AutoSubscriber {

  const FORM_NAME = 'afformUrbanExternalMeetingSession';
  const ACTIVITY_TYPE_NAME = 'Urban External Meeting Session';
  const ACTIVITY_STATUS = 'Completed';
  const ACTIVITY_SUBJECT = 'Urban External Meeting Session';

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      'civi.afform.submit' => [
        ['createMeetingActivity', 9],
      ],
    ];
  }

  /**
   * Creates meeting activities in contact profiles on form submission.
   */
  public static function createMeetingActivity(AfformSubmitEvent $event) {
    error_log('working1');
    $afform = $event->getAfform();
    $formName = $afform['name'];


    error_log('Form submitted: ' . $formName);

    if ($formName !== self::FORM_NAME) {
      return;
    }

    $entityType = $event->getEntityType();
    error_log('entityType: ' . $entityType);


    if ($entityType !== 'Eck_Meetings_Sessions') {
      return;
    }

    foreach ($event->records as $record) {
      $fields = $record['fields'];
      error_log('Record fields: ' . print_r($fields, TRUE));

      $individualOrPocId = $fields['Urban_Meetings.Select_Individual'] ?? NULL;
      error_log('individualOrPocId: ' . print_r($individualOrPocId, TRUE));
      $institutionId = $fields['Urban_Meetings.Institution'] ?? NULL;
      error_log('institutionId: ' . print_r($institutionId, TRUE));


      if ($individualOrPocId) {
        self::createActivityForContact($individualOrPocId);
      }

      if ($institutionId) {
        self::createActivityForContact($institutionId);
      }
    }
  }

  /**
   * Creates a meeting activity for the given contact.
   */
  private static function createActivityForContact(int $contactId) {
    try {
      Activity::create(FALSE)
        ->addValue('subject', self::ACTIVITY_SUBJECT)
        ->addValue('activity_type_id:name', self::ACTIVITY_TYPE_NAME)
        ->addValue('status_id:name', self::ACTIVITY_STATUS)
        ->addValue('activity_date_time', date('Y-m-d H:i:s'))
        ->addValue('source_contact_id', $contactId)
        ->addValue('target_contact_id', $contactId)
        ->execute();
    }
    catch (\CiviCRM_API4_Exception $ex) {
      \Civi::log()->error('Exception while creating Urban External Meeting Session activity for contact ' . $contactId . ': ' . $ex->getMessage());
    }
  }

}
