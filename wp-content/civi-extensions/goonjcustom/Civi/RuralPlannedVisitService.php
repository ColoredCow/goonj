<?php

namespace Civi;

use Civi\Core\Service\AutoSubscriber;
use Civi\Api4\Event;
use Civi\Api4\Address;
use Civi\Api4\Contact;

/**
 *
 */
class RuralPlannedVisitService extends AutoSubscriber {

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_tabset' => 'ruralPlannedVisitTabset',
    ];
  }

  /**
   *
   */
  public static function ruralPlannedVisitTabset($tabsetName, &$tabs, $context) {

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

    if ($eventType !== 'Rural Planned Visit') {
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
      'logistics' => [
        'id' => 'logistics',
        'title' => ts('Logistics'),
        'active' => 1,
        'module' => 'afsearchRuralLogisticsDetails',
        'directive' => 'afsearch-rural-logistics-details',
        'template' => 'CRM/Goonjcustom/Tabs/RuralPlannedVisit/Logistics.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin', 'mmt_and_accounts_chapter_team'],
      ],
      'report' => [
        'id' => 'outcome report',
        'title' => ts('Outcome Report'),
        'active' => 1,
        'module' => 'afsearchRuralPlannedVisitsOutcomeReportsDetails',
        'directive' => 'afsearch-rural-planned-visits-outcome-reports-details',
        'template' => 'CRM/Goonjcustom/Tabs/RuralPlannedVisit/Report.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin', 'mmt_and_accounts_chapter_team'],
      ],
      'outcome' => [
        'id' => 'outcome',
        'title' => ts('Outcome'),
        'active' => 1,
        'module' => 'afsearchRuralPlannedVisitOutcomeDetails',
        'directive' => 'afsearch-rural-planned-visit-outcome-details',
        'template' => 'CRM/Goonjcustom/Tabs/RuralPlannedVisit/Outcome.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin', 'mmt_and_accounts_chapter_team'],
      ],
      'feedback' => [
        'id' => 'feedback',
        'title' => ts('Feedback'),
        'active' => 1,
        'module' => 'afsearchRuralPlannedVisitFeedbacksDetails',
        'directive' => 'afsearch-rural-planned-visit-feedbacks-details',
        'template' => 'CRM/Goonjcustom/Tabs/RuralPlannedVisit/Feedback.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin', 'mmt_and_accounts_chapter_team'],
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
  public static function sendRuralPlannedVisitOutcomeEmail($event) {
    try {
      $eventId = $event['id'];
      $eventCode = $event['title'];
      $addresses = Address::get(FALSE)
        ->addWhere('id', '=', $event['loc_block_id.address_id'])
        ->setLimit(1)
        ->execute()->first();
      $eventAddress = \CRM_Utils_Address::format($addresses);

      $eventAttendedById = $event['Rural_Planned_Visit.Goonj_Coordinator'];
      $outcomeEmailSent = $event['Rural_Planned_Visit_Outcome.Outcome_Email_Sent'];

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
          'subject' => 'Rural Planned Visit Events Notification: ' . $eventCode,
          'from' => $from,
          'toEmail' => $attendeeEmail,
          'replyTo' => $from,
          'html' => self::getOutcomeEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress),
        ];

        $emailSendResult = \CRM_Utils_Mail::send($mailParams);

        if ($emailSendResult) {
          Event::update(FALSE)
            ->addValue('Rural_Planned_Visit_Outcome.Outcome_Email_Sent', TRUE)
            ->addValue('Rural_Planned_Visit_Outcome.Filled_By', $eventAttendedById)
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
    $eventOutcomeFormUrl = $homeUrl . 'rural-planned-visit-outcome/#?Event1=' . $eventId;

    $html = "
  <p>Dear $attendeeName,</p>
	<p>Thank you for coordinating the <strong>$eventCode</strong> Please fill the below form after the event is over:</p>
  <p><a href=\"$eventOutcomeFormUrl\">Event Outcome Form</a><br></p>
	<p>We appreciate your cooperation.</p>
	<p>Warm Regards,<br>Team goonj</p>";

    return $html;
  }

  /**
   *
   */
  public static function sendRuralPlannedVisitFeedbackEmail($eventsArray) {
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
        $feedbackEmailSent = $event['Rural_Planned_Visit_Outcome.Feedback_Email_Sent'];
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
            'subject' => 'Rural Planned Visit Events Notification: ' . $eventCode,
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
        ->addValue('Rural_Planned_Visit_Outcome.Feedback_Email_Sent', TRUE)
        ->addWhere('id', 'IN', $updatedEventIds)
        ->execute();
    }
  }

  /**
   *
   */
  private static function getParticipantsFeedbackEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();

    $eventFeedBackFormUrl = $homeUrl . 'rural-planned-visit-feedback/#?Rural_Planned_Visit_Feedback.Event=' . $eventId . '&source_contact_id=' . $eventAttendedById;

    $html = "
  <p>Dear $attendeeName,</p>
  <p>Thank you for attending <strong>$eventCode</strong>. Your presence and contributions made this event a huge success.</p>
  <p>We value your feedback to continue improving the experience of such gatherings and tailoring our future events. Kindly take a few moments to share your experience with us by filling out this <a href=\"$eventFeedBackFormUrl\">form link</a>.</p>
  <p>Warm Regards,<br>Team Goonj</p>";

    return $html;
  }

}
