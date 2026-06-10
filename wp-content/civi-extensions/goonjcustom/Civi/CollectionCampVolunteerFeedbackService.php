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
    // checkPermissions=FALSE because cron has no user context; with TRUE, ACL
    // makes Contact::get return zero rows. The LEFT JOIN on Email can return
    // one row per email — use ->first() to pick any email when the contact
    // has multiple addresses.
    $initiator = Contact::get(FALSE)
      ->addSelect('email.email', 'display_name')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('id', '=', $initiatorId)
      ->execute()->first();

    $initiatorEmail = $initiator['email.email'];
    $initiatorName = $initiator['display_name'];

    $endDate = new \DateTime($camp['Collection_Camp_Intent_Details.End_Date']);
    $collectionCampId = $camp['id'];
    $campAddress = $camp['Collection_Camp_Intent_Details.Location_Area_of_camp'];

    // Calculate hours since camp ended.
    $hoursSinceCampEnd = abs($now->getTimestamp() - $endDate->getTimestamp()) / 3600;

    // Send a single reminder once 24 hours have passed since the camp ended.
    // The cron query already excludes camps whose feedback is filled or that
    // were already reminded (Logistics_Coordination.Feedback_Reminder_Sent), so
    // here we just send and flag the camp so the reminder never fires again.
    if ($hoursSinceCampEnd >= 24) {
      // Send the first (and only) reminder email to the volunteer.
      self::sendVolunteerFeedbackReminderEmail($initiatorEmail, $from, $campAddress, $collectionCampId, $endDate, $initiatorName, $initiatorId);

      // Flag the reminder on the camp itself (same pattern as Feedback_Email_Sent).
      // Runs only if the send above succeeded — sendVolunteerFeedbackReminderEmail
      // throws on failure, so a failed send is retried on the next cron run.
      EckEntity::update('Collection_Camp', FALSE)
        ->addWhere('id', '=', $collectionCampId)
        ->addValue('Logistics_Coordination.Feedback_Reminder_Sent', 1)
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
  public static function sendVolunteerFeedbackReminderEmail($initiatorEmail, $from, $campAddress, $collectionCampId, $endDate, $initiatorName, $initiatorId) {
    $mailParams = [
      'subject' => 'Reminder to share your feedback for ' . $campAddress . ' on ' . $endDate->format('Y-m-d'),
      'from' => $from,
      'toEmail' => $initiatorEmail,
      'replyTo' => $from,
      'html' => self::getVolunteerFeedbackReminderEmailHtml($initiatorName, $collectionCampId, $initiatorId, $campAddress),
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
  public static function getVolunteerFeedbackReminderEmailHtml($initiatorName, $collectionCampId, $initiatorId, $campAddress) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $campVolunteerFeedback = $homeUrl . 'volunteer-camp-feedback/#?Collection_Source_Feedback.Collection_Camp_Code=' . $collectionCampId . '&Collection_Source_Feedback.Collection_Camp_Address=' . urlencode($campAddress) . '&Collection_Source_Feedback.Filled_By=' . $initiatorId;

    $html = "
      <p>Dear $initiatorName,</p>
      <p>Greetings from Goonj!</p>
      <p>Kindly reminding you to share your valuable feedback on the recent collection camp. Your insights are important for us to continue improving in future camps.</p>
      <p>If you haven’t had the chance yet, please take a few minutes to fill out the feedback form here:</p>
      <p><a href=\"$campVolunteerFeedback\">Feedback Form Link</a></p>
      <p>We look forward to working with you again!</p>
      <p>Warm regards,<br>Team Goonj</p>";

    return $html;
  }

}
