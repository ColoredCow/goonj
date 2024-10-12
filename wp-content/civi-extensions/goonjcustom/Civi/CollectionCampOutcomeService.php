<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class CollectionCampOutcomeService {

  /**
   * Process camp reminder logic.
   *
   * @param array $camp
   * @param \DateTimeImmutable $today
   *
   * @throws \Exception
   */
  public static function processCampReminder($camp, $today, $from) {
    $campAttendedById = $camp['Logistics_Coordination.Camp_to_be_attended_by'];
    $endDateString = $camp['Collection_Camp_Intent_Details.End_Date'];
    $endDate = new \DateTime($camp['Collection_Camp_Intent_Details.End_Date']);
    $collectionCampId = $camp['id'];
    $campCode = $camp['title'];
    $campAddress = $camp['Collection_Camp_Intent_Details.Location_Area_of_camp'];

    $lastReminderSent = $camp['Camp_Outcome.Last_Reminder_Sent'] ? new \DateTime($camp['Camp_Outcome.Last_Reminder_Sent']) : NULL;

    // Calculate hours since camp ended.
    $hoursSinceCampEnd = abs($today->getTimestamp() - $endDate->getTimestamp()) / 3600;


    // Calculate hours since last reminder was sent (if any)
    $hoursSinceLastReminder = $lastReminderSent ? abs($today->getTimestamp() - $lastReminderSent->getTimestamp()) / 3600 : NULL;

    // Check if the outcome form is not filled and send the first reminder after 48 hours of camp end.
    if ($hoursSinceCampEnd >= 48) {
      // Send the reminder email if the form is still not filled.
      if ($lastReminderSent === NULL || $hoursSinceLastReminder >= 24) {
        // Send the reminder email.
        self::sendOutcomeReminderEmail($campAttendedById, $from, $campCode, $campAddress, $collectionCampId, $endDateString);

        // Update the Last_Reminder_Sent field in the database.
        EckEntity::update('Collection_Camp', TRUE)
          ->addWhere('id', '=', $camp['id'])
          ->addValue('Camp_Outcome.Last_Reminder_Sent', $today->format('Y-m-d H:i:s'))
          ->execute();
      }
    }
  }

  /**
   * Send the reminder email to the camp attendee.
   */
  public static function sendOutcomeReminderEmail($campAttendedById, $from, $campCode, $campAddress, $collectionCampId, $endDateString) {
    $campAttendedBy = Contact::get(FALSE)
      ->addSelect('email.email', 'display_name')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('id', '=', $campAttendedById)
      ->execute()->single();

    $attendeeEmail = $campAttendedBy['email.email'];
    $attendeeName = $campAttendedBy['display_name'];

    $mailParams = [
      'subject' => 'Reminder to fill the camp outcome form for ' . $campCode . ' at ' . $campAddress . ' on ' . $endDateString,
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
   * Generate the outcome reminder email HTML.
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

  /**
   * Get default from email.
   */
  public static function getDefaultFromEmail() {
    [$defaultFromName, $defaultFromEmail] = \CRM_Core_BAO_Domain::getNameAndEmail();
    return "\"$defaultFromName\" <$defaultFromEmail>";
  }

}
