<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class CollectionCampOutcomeService extends AutoSubscriber {

  /**
   * Define subscribed events.
   */
  public static function getSubscribedEvents() {
    return [];
  }

  /**
   * Send the reminder email to the camp attendee.
   *
   * @param int $campAttendedById
   */
  public static function sendOutcomeReminderEmail($campAttendedById, $from, $campCode, $campAddress, $collectionCampId, $endDateForCollectionCamp) {
    $campAttendedBy = Contact::get(FALSE)
      ->addSelect('email.email', 'display_name')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('id', '=', $campAttendedById)
      ->execute()->single();

    $attendeeEmail = $campAttendedBy['email.email'];
    $attendeeName = $campAttendedBy['display_name'];

    // Prepare and send the email.
    $mailParams = [
      'subject' => 'Reminder to fill the camp outcome form for ' . $campCode . ' at ' . $campAddress . ' on ' . $endDateForCollectionCamp,
      'from' => $from,
      'toEmail' => $attendeeEmail,
      'replyTo' => $from,
      'html' => self::getOutcomeReminderEmailHtml($attendeeName, $collectionCampId, $campAttendedById),
    ];

    $emailSendResult = \CRM_Utils_Mail::send($mailParams);

    if (!$emailSendResult) {
      \Civi::log()->error('Failed to send reminder email', [
        'campAttendedById' => $campAttendedById,
        'attendeeEmail' => $attendeeEmail,
      ]);
      throw new \CRM_Core_Exception('Failed to send reminder email');
    }
  }

  /**
   *
   */
  public static function getOutcomeReminderEmailHtml($attendeeName, $collectionCampId, $campAttendedById) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $campOutcomeFormUrl = $homeUrl . '/camp-outcome-form/#?Eck_Collection_Camp1=' . $collectionCampId . '&Camp_Outcome.Filled_By=' . $campAttendedById;
    $html = "
      <p>Dear $attendeeName,</p>
      <p>This is a kind reminder to complete the Camp Outcome Form at the earliest. Your feedback is crucial in helping us assess the overall response and impact of the camp/drive.</p>
      <p>You can access the form using the link below:</p>
      <p><a href=\"$campOutcomeFormUrl\">Camp Outcome Form</a></p>
      <p>We appreciate your cooperation.</p>
      <p>Warm Regards,<br>Urban Relations Team</p>";

    return $html;
  }

}
