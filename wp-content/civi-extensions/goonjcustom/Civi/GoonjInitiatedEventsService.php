<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Core\Service\AutoSubscriber;
use Civi\Api4\Event;
use Civi\Api4\Address;
use Civi\Traits\QrCodeable;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;

/**
 *
 */
class GoonjInitiatedEventsService extends AutoSubscriber {
  use QrCodeable;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_pre' => 'generateGoonjEventsQr',
      '&hook_civicrm_tabset' => 'goonjEventsTabset',
      '&hook_civicrm_post' => 'assignChapterGroupToIndividual',
    ];
  }

  /**
   *
   */
  public static function sendEventOutcomeEmail($event) {
    try {
      $eventId = $event['id'];
      $eventCode = $event['title'];
      $addresses = Address::get(FALSE)
        ->addWhere('id', '=', $event['loc_block_id.address_id'])
        ->setLimit(1)
        ->execute()->first();
      $eventAddress = \CRM_Utils_Address::format($addresses);

      $eventAttendedById = $event['Goonj_Events.Goonj_Coordinating_POC_Main_'];
      $outcomeEmailSent = $event['Goonj_Events_Outcome.Outcome_Email_Sent'];

      $startDate = new \DateTime($event['start_date']);

      $today = new \DateTimeImmutable();
      $endOfToday = $today->setTime(23, 59, 59);

      if (!$outcomeEmailSent && $startDate <= $endOfToday) {
        $eventAttendedBy = Contact::get(FALSE)
          ->addSelect('email.email', 'display_name')
          ->addJoin('Email AS email', 'LEFT')
          ->addWhere('id', '=', $eventAttendedById)
          ->execute()->first();

        $attendeeEmail = $eventAttendedBy['email.email'];
        $attendeeName = $eventAttendedBy['display_name'];
        $from = HelperService::getDefaultFromEmail();

        if (!$attendeeEmail) {
          throw new \Exception('Attendee email missing');
        }

        $mailParams = [
          'subject' => 'Goonj Events Notification: ' . $eventCode,
          'from' => $from,
          'toEmail' => $attendeeEmail,
          'replyTo' => $from,
          'html' => self::getOutcomeEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress),
        ];

        $emailSendResult = \CRM_Utils_Mail::send($mailParams);

        if ($emailSendResult) {
          Event::update(FALSE)
            ->addValue('Goonj_Events_Outcome.Outcome_Email_Sent', TRUE)
            ->addWhere('id', '=', $eventId)
            ->execute();
        }
      }
    }
    catch (\Exception $e) {
      \Civi::log()->error("Error in sendEventsOutcomeEmail for $eventId " . $e->getMessage());
    }

  }

  /**
   *
   */
  private static function getOutcomeEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    // Construct the full URLs for the forms.
    $eventOutcomeFormUrl = $homeUrl . 'goonj-initiated-events-outcome/#?Event1=' . $eventId . '&Goonj_Events_Outcome.Filled_By=' . $eventAttendedById;

    $html = "
	<p>Dear $attendeeName,</p>
	<p>Thank you for coordinating the <strong>$eventCode</strong> Please fill the below form after the event is over:</p>
  <p><a href=\"$eventOutcomeFormUrl\">Event Outcome Form</a><br></p>
	<p>We appreciate your cooperation.</p>
	<p>Warm Regards,<br>Team goonj</p>";

    return $html;
  }

  /**
   * This hook is called after a database write operation on entities.
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
  public static function generateGoonjEventsQr(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName !== 'Event') {
      return;
    }

    try {
      $eventId = $objectRef['id'] ?? NULL;
      if (!$eventId) {
        \Civi::log()->warning('Event ID is missing from object reference.' . $objectId);
        return;
      }

      // Fetch event details with the QR Code field.
      $events = Event::get(FALSE)
        ->addSelect('Event_QR.QR_Code')
        ->addWhere('id', '=', $eventId)
        ->execute();

      $event = $events->first();
      if (!$event) {
        \Civi::log()->warning('Event not found..' . $eventId);
        return;
      }

      $eventQrCode = $event['Event_QR.QR_Code'] ?? NULL;
      if (!empty($eventQrCode)) {
        \Civi::log()->info('QR Code already exists for the event.', ['eventId' => $eventId]);
        return;
      }

      // Generate base URL for QR Code.
      $baseUrl = \CRM_Core_Config::singleton()->userFrameworkBaseURL;
      $qrCodeData = "{$baseUrl}actions/events/{$eventId}";
      // Define save options for custom group and field.
      $saveOptions = [
        'customGroupName' => 'Event_QR',
        'customFieldName' => 'QR_Code',
      ];

      // Generate and save the QR Code.
      self::generateQrCode($qrCodeData, $eventId, $saveOptions);
      \Civi::log()->info('QR Code generated and saved successfully.', ['eventId' => $eventId]);
    }
    catch (\Exception $e) {
      \Civi::log()->error('Error generating QR Code for event.', [
        'errorMessage' => $e->getMessage(),
        'objectName' => $objectName,
        'objectId' => $objectId,
      ]);
    }
  }

  /**
   *
   */
  public static function goonjEventsTabset($tabsetName, &$tabs, $context) {

    if ($tabsetName !== 'civicrm/event/manage') {
      return;
    }

    if (!isset($context['event_id'])) {
      return;
    }

    $eventId = $context['event_id'];

    $event = Event::get(FALSE)
      ->addSelect('event_type_id:name')
      ->addWhere('id', '=', $eventId)
      ->setLimit(1)
      ->execute()
      ->first();

    if (!$event) {
      \Civi::log()->error('Event not found', ['EventId' => $eventId]);
      return;
    }

    $eventType = $event['event_type_id:name'];

    if ($eventType === 'Rural Planned Visit') {
      return;
    }

    $restrictedRoles = ['account_team', 'ho_account'];

    $isAdmin = \CRM_Core_Permission::check('admin');

    $hasRestrictedRole = !$isAdmin && \CRM_Core_Permission::checkAnyPerm($restrictedRoles);

    if ($hasRestrictedRole) {
      unset($tabs['view']);
      unset($tabs['edit']);
    }

    $eventID = $context['event_id'];

    $tabConfigs = [
      'outcome' => [
        'id' => 'outcome',
        'title' => ts('Outcome'),
        'active' => 1,
        'module' => 'afsearchEventsOutcomeDetails',
        'directive' => 'afsearch-events-outcome-details',
        'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin', 'sanjha_team', 'project_team_ho', 'project_team_chapter'],
      ],
      'feedback' => [
        'id' => 'feedback',
        'title' => ts('Feedback'),
        'active' => 1,
        'module' => 'afsearchGoonjInitiatedEventsFeedbackView',
        'directive' => 'afsearch-goonj-initiated-events-feedback-view',
        'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin', 'sanjha_team', 'project_team_ho', 'project_team_chapter'],
      ],
      'materialContributions' => [
        'id' => 'material_contributions',
        'title' => ts('Material Contributions'),
        'active' => 1,
        'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
        'module' => 'afsearchEventsMaterialContributions',
        'directive' => 'afsearch-events-material-contributions',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin', 'sanjha_team', 'project_team_ho', 'project_team_chapter', 'project_team_chapter'],
      ],
      'monetaryContribution' => [
        'id' => 'monetary_contributions',
        'title' => ts('Monetary Contribution'),
        'active' => 1,
        'module' => 'afsearchEventsMonetaryContribution',
        'directive' => 'afsearch-events-monetary-contribution',
        'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'sanjha_team', 'project_team_ho', 'project_team_chapter'],
      ],
    ];

    foreach ($tabConfigs as $key => $config) {
      $isAdmin = \CRM_Core_Permission::check('admin');

      if (!\CRM_Core_Permission::checkAnyPerm($config['permissions'])) {
        // Does not permission; just continue.
        continue;
      }

      $tabs[$key] = [
        'id' => $key,
        'title' => $config['title'],
        'active' => 1,
        'template' => $config['template'],
        'module' => $config['module'],
        'directive' => $config['directive'],
        'entity' => $config['entity'],
        'valid' => 1,
      ];

      \Civi::service('angularjs.loader')->addModules($config['module']);
    }
  }

  /**
   *
   */
  public static function sendGoonjInitiatedFeedbackEmail($eventsArray) {
    $updatedEventIds = [];

    foreach ($eventsArray as $event) {
      try {
        $eventId = $event['id'];
        $eventCode = $event['title'];
        $addresses = Address::get(FALSE)
          ->addWhere('id', '=', $event['loc_block_id.address_id'])
          ->setLimit(1)
          ->execute()->first();
        $eventAddress = \CRM_Utils_Address::format($addresses);
        $feedbackEmailSent = $event['Goonj_Events_Feedback.Last_Reminder_Sent'];
        $eventAttendedById = $event['participant.created_id'];

        $endDate = new \DateTime($event['end_date']);
        $today = new \DateTimeImmutable();
        $endOfToday = $today->setTime(23, 59, 59);
        $endOfTodayFormatted = $today->format('Y-m-d');
        $endDateFormatted = $endDate->format('Y-m-d');

        if (!$feedbackEmailSent && $endDateFormatted <= $endOfTodayFormatted) {
          $eventAttendedBy = Contact::get(FALSE)
            ->addSelect('email.email', 'display_name')
            ->addJoin('Email AS email', 'LEFT')
            ->addWhere('id', '=', $eventAttendedById)
            ->execute()->first();

          $attendeeEmail = $eventAttendedBy['email.email'];
          $attendeeName = $eventAttendedBy['display_name'];
          $from = HelperService::getDefaultFromEmail();

          if (!$attendeeEmail) {
            throw new \Exception('Attendee email missing');
          }

          $mailParams = [
            'subject' => 'Goonj Events Notification: ' . $eventCode,
            'from' => $from,
            'toEmail' => $attendeeEmail,
            'replyTo' => $from,
            'html' => self::getParticipantsFeedbackEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress),
          ];

          $emailSendResult = \CRM_Utils_Mail::send($mailParams);

          if ($emailSendResult) {
            // If email is successfully sent, store the eventId for later update.
            $updatedEventIds[] = $eventId;
          }
        }
      }
      catch (\Exception $e) {
        \Civi::log()->error("Error in sendEventsFeedbackEmail for $eventId " . $e->getMessage());
      }
    }

    if (!empty($updatedEventIds)) {
      Event::update(FALSE)
        ->addValue('Goonj_Events_Feedback.Last_Reminder_Sent', TRUE)
        ->addWhere('id', 'IN', $updatedEventIds)
        ->execute();
    }
  }

  /**
   *
   */
  private static function getParticipantsFeedbackEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();

    $eventFeedBackFormUrl = $homeUrl . 'goonj-events-feedbacks/#?Events_Feedback.Event=' . $eventId . '&source_contact_id=' . $eventAttendedById;

    $html = "
    <p>Dear $attendeeName,</p>
    <p>Thank you for attending <strong>$eventCode</strong>. Your presence and contributions made this event a huge success.</p>
    <p>We value your feedback to continue improving the experience of such gatherings and tailoring our future events. Kindly take a few moments to share your experience with us by filling out this <a href=\"$eventFeedBackFormUrl\">form link</a>.</p>
    <p>Warm Regards,<br>Team Goonj</p>";

    return $html;
  }

  /**
   *
   */
  public static function assignChapterGroupToIndividual(string $op, string $objectName, $objectId, &$objectRef) {

    if ($op !== 'create' || $objectName !== 'Participant') {
      return FALSE;
    }

    $contactId = $objectRef->contact_id;

    if (!$contactId) {
      \Civi::log()->info("Missing Contact ID ");
      return FALSE;
    }

    $contactQuery = Contact::get(FALSE)
      ->addSelect('address_primary.state_province_id')
      ->addWhere('id', '=', $contactId)
      ->execute();
    $contact = $contactQuery->first();
    $stateProvinceId = $contact['address_primary.state_province_id'];

    if (empty($contact)) {
      return FALSE;
    }

    $groupContacts = GroupContact::get(TRUE)
      ->addSelect('*', 'group_id:name')
      ->addWhere('contact_id', '=', $contact['id'])
      ->execute()->first();

    if ($groupContacts) {
      return FALSE;
    }

    $groupId = self::getChapterGroupForState($stateProvinceId);

    if ($groupId) {
      self::addContactToGroup($contactId, $groupId);
    }
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
  private static function addContactToGroup($contactId, $groupId) {
    if ($contactId & $groupId) {
      $groupContact = GroupContact::get(FALSE)
        ->addWhere('contact_id', '=', $contactId)
        ->addWhere('group_id', '=', $groupId)
        ->execute()->first();
      if ($groupContact) {
        return FALSE;
      }
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
  }

}
