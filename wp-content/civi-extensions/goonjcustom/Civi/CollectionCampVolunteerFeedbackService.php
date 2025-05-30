<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;

/**
 * Collection Camp Outcome Service.
 */
class CollectionCampVolunteerFeedbackService {

  /**
   * Process the feedback reminder for a volunteer.
   *
   * @param array $camp
   *   The camp data.
   * @param \DateTimeImmutable $now
   *   The current date.
   *
   * @throws \CRM_Core_Exception
   */
  public static function processVolunteerFeedbackReminder($camp, $now, $from) {
    $initiatorId = $camp['Collection_Camp_Core_Details.Contact_Id'];
    $initiator = Contact::get(TRUE)
      ->addSelect('email.email', 'display_name')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('id', '=', $initiatorId)
      ->execute()->single();

    $initiatorEmail = $initiator['email.email'];
    $initiatorName = $initiator['display_name'];

    $endDate = new \DateTime($camp['Collection_Camp_Intent_Details.End_Date']);
    $collectionCampId = $camp['id'];
    $campAddress = $camp['Collection_Camp_Intent_Details.Location_Area_of_camp'];

    // Check last reminder sent.
    $lastReminderSent = $camp['Volunteer_Camp_Feedback.Last_Reminder_Sent'] ? new \DateTime($camp['Volunteer_Camp_Feedback.Last_Reminder_Sent']) : NULL;

    // Calculate hours since camp ended.
    $hoursSinceCampEnd = abs($now->getTimestamp() - $endDate->getTimestamp()) / 3600;

    // Check if feedback form is not filled and 24 hours have passed since camp end.
    if ($hoursSinceCampEnd >= 24 && !$lastReminderSent) {
      // Send the first reminder email to the volunteer.
      self::sendVolunteerFeedbackReminderEmail($initiatorEmail, $from, $campAddress, $collectionCampId, $endDate, $initiatorName);

      // Update the Last_Reminder_Sent field in the database.
      EckEntity::update('Collection_Camp', TRUE)
        ->addWhere('id', '=', $collectionCampId)
        ->addValue('Volunteer_Camp_Feedback.Last_Reminder_Sent', $now->format('Y-m-d H:i:s'))
        ->execute();
    }
  }

  /**
   * Send the feedback reminder email to the volunteer.
   *
   * @param int $initiatorEmail
   * @param string $from
   * @param string $campAddress
   * @param int $collectionCampId
   * @param \DateTime $endDate
   */
  public static function sendVolunteerFeedbackReminderEmail($initiatorEmail, $from, $campAddress, $collectionCampId, $endDate, $initiatorName) {
    $mailParams = [
      'subject' => 'Reminder to share your feedback for ' . $campAddress . ' on ' . $endDate->format('Y-m-d'),
      'from' => $from,
      'toEmail' => $initiatorEmail,
      'replyTo' => $from,
      'html' => self::getVolunteerFeedbackReminderEmailHtml($initiatorName, $collectionCampId),
    ];

    $emailSendResult = \CRM_Utils_Mail::send($mailParams);

    if (!$emailSendResult) {
      \Civi::log()->error('Failed to send feedback reminder email', [
        'initiatorEmail' => $initiatorEmail,
        'volunteerEmail' => $volunteerEmail,
      ]);
      throw new \CRM_Core_Exception('Failed to send feedback reminder email');
    }
  }

  /**
   * Generates HTML content for the volunteer feedback reminder email.
   *
   * @param string $initiatorName
   * @param int $collectionCampId
   *
   * @return string
   */
  public static function getVolunteerFeedbackReminderEmailHtml($initiatorName, $collectionCampId) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $feedbackFormUrl = $homeUrl . 'volunteer-camp-feedback/#?Eck_Collection_Camp1=' . $collectionCampId;

    $html = "
      <p>Dear $initiatorName,</p>
      <p>Greetings from Goonj!</p>
      <p>Kindly reminding you to share your valuable feedback on the recent collection camp. Your insights are important for us to continue improving in future camps.</p>
      <p>If you haven’t had the chance yet, please take a few minutes to fill out the feedback form here:</p>
      <p><a href=\"$feedbackFormUrl\">Feedback Form Link</a></p>
      <p>We look forward to working with you again!</p>
      <p>Warm regards,<br>Team Goonj</p>";

    return $html;
  }

}
