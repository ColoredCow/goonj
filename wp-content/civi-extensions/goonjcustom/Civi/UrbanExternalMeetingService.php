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

  const FORM_NAMES = [
    'afformUrbanExternalMeetingSession',
    'afformUrbanExternalMeeting',
  ];
  const ACTIVITY_TYPE_NAME = 'Urban External Meeting';
  const ACTIVITY_STATUS = 'Completed';
  const ACTIVITY_SUBJECT = 'Urban External Meeting';

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
    $afform = $event->getAfform();
    $formName = $afform['name'];

    if (!in_array($formName, self::FORM_NAMES)) {
      return;
    }

    $entityType = $event->getEntityType();

    if ($entityType !== 'Eck_Meetings_Sessions') {
      return;
    }

    foreach ($event->records as $record) {
      $fields = $record['fields'];
      $individualOrPocId = $fields['Urban_Meetings.Select_Individual'] ?? NULL;
      $institutionId = $fields['Urban_Meetings.Institution'] ?? NULL;
      $coordinatingPocId = $fields['Urban_Meetings.Coordinating_Goonj_POC'] ?? NULL;

      if ($individualOrPocId) {
        self::createActivityForContact($individualOrPocId, $coordinatingPocId);
      }

      if ($institutionId) {
        self::createActivityForContact($institutionId, $coordinatingPocId);
      }
    }
  }

  /**
   * Creates a meeting activity for the given contact.
   */
  private static function createActivityForContact(int $contactId, $sourceContactId = NULL) {
    try {
      Activity::create(FALSE)
        ->addValue('subject', self::ACTIVITY_SUBJECT)
        ->addValue('activity_type_id:name', self::ACTIVITY_TYPE_NAME)
        ->addValue('status_id:name', self::ACTIVITY_STATUS)
        ->addValue('activity_date_time', date('Y-m-d H:i:s'))
        ->addValue('source_contact_id', $sourceContactId ?: $contactId)
        ->addValue('target_contact_id', $contactId)
        ->execute();
    }
    catch (\CiviCRM_API4_Exception $ex) {
      \Civi::log()->error('Exception while creating Urban External Meeting Session activity for contact ' . $contactId . ': ' . $ex->getMessage());
    }
  }

}
