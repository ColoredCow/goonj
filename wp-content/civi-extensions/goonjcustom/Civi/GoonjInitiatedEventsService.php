<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Core\Service\AutoSubscriber;
use Civi\Api4\Event;

/**
 *
 */
class GoonjInitiatedEventsService extends AutoSubscriber {
  // Use QrCodeable;
  //   use CollectionSource;
  //   const ENTITY_NAME = 'Collection_Camp';
  //   const ENTITY_SUBTYPE_NAME = 'Goonj_Activities';
  //   const GOONJ_ACTIVITIES_INTENT_FB_NAME = 'afformGoonjActivitiesIndividualIntentForm';
  //   const RELATIONSHIP_TYPE_NAME = 'Goonj Activities Coordinator of';
  //   private static $goonjActivitiesAddress = NULL;
  //   const FALLBACK_OFFICE_NAME = 'Delhi';.

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [];
  }

  /**
   *
   */
  public static function sendEventOutcomeEmail($event) {
    try {
      $eventId = $event['id'];
      $eventCode = $event['title'];
      $eventAddress = $event['Goonj_Events.Venue'];
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
          ->execute()->single();

        $attendeeEmail = $eventAttendedBy['email.email'];
        $attendeeName = $eventAttendedBy['display_name'];
        $from = HelperService::getDefaultFromEmail();

        if (!$attendeeEmail) {
          throw new \Exception('Attendee email missing');
        }

        $mailParams = [
          'subject' => 'Goonj Events Notification: ' . $eventCode . ' at ' . $eventAddress,
          'from' => $from,
          'toEmail' => $attendeeEmail,
          'replyTo' => $from,
          'html' => self::getLogisticsEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress),
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
      \Civi::log()->error("Error in sendLogisticsEmail for $campId " . $e->getMessage());
    }

  }

  /**
   *
   */
  private static function getLogisticsEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    // Construct the full URLs for the forms.
    $campOutcomeFormUrl = $homeUrl . 'goonj-initiated-events-outcome/#?Event1=' . $eventId . '&Goonj_Events_Outcome.Filled_By=' . $campAttendedById;

    $html = "
    <p>Dear $attendeeName,</p>
    <p>Thank you for attending the goonj activity <strong>$eventId</strong> at <strong>$eventAddress</strong>. Their is one forms that require your attention during and after the goonj activity:</p>
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
