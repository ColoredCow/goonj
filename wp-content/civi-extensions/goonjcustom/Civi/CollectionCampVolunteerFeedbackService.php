<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Core\Service\AutoSubscriber;

/**
 * Collection Camp Outcome Service.
 */
class CollectionCampVolunteerFeedbackService {

  /**
   * Process the feedback reminder for a volunteer.
   *
   * @param array $camp
   *   The camp data.
   * @param \DateTimeImmutable $today
   *   The current date.
   *
   * @throws \CRM_Core_Exception
   */
  public static function processVolunteerFeedbackReminder($camp, $today) {
    $from = self::getDefaultFromEmail();
    $volunteerContactId = $camp['Collection_Camp_Core_Details.Contact_Id'];
    $campAttendedBy = Contact::get(TRUE)
      ->addSelect('email.email', 'display_name')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('id', '=', $volunteerContactId)
      ->execute()->single();

    $volunteerEmailId = $campAttendedBy['email.email'];
    $volunteerName = $campAttendedBy['display_name'];

    $endDate = new \DateTime($camp['Collection_Camp_Intent_Details.End_Date']);
    $collectionCampId = $camp['id'];
    $campAddress = $camp['Collection_Camp_Intent_Details.Location_Area_of_camp'];

    // Check last reminder sent.
    $lastReminderSent = $camp['Volunteer_Camp_Feedback.Last_Reminder_Sent'] ? new \DateTime($camp['Volunteer_Camp_Feedback.Last_Reminder_Sent']) : NULL;

    // Calculate hours since camp ended.
    $hoursSinceCampEnd = abs($today->getTimestamp() - $endDate->getTimestamp()) / 3600;


    // Check if feedback form is not filled and 24 hours have passed since camp end.
    if ($hoursSinceCampEnd >= 24 && ($lastReminderSent === NULL)) {
      // Send the first reminder email to the volunteer.
      self::sendVolunteerFeedbackReminderEmail($volunteerEmailId, $from, $campAddress, $collectionCampId, $endDate, $volunteerName);

      // Update the Last_Reminder_Sent field in the database.
      EckEntity::update('Collection_Camp', TRUE)
        ->addWhere('id', '=', $collectionCampId)
        ->addValue('Volunteer_Camp_Feedback.Last_Reminder_Sent', $today->format('Y-m-d H:i:s'))
        ->execute();
    }
  }

  /**
   * Send the feedback reminder email to the volunteer.
   *
   * @param int $volunteerEmailId
   * @param string $from
   * @param string $campAddress
   * @param int $collectionCampId
   * @param \DateTime $endDate
   */
  public static function sendVolunteerFeedbackReminderEmail($volunteerEmailId, $from, $campAddress, $collectionCampId, $endDate, $volunteerName) {
    $mailParams = [
      'subject' => 'Reminder to share your feedback for ' . $campAddress . ' on ' . $endDate->format('Y-m-d'),
      'from' => $from,
      'toEmail' => $volunteerEmailId,
      'replyTo' => $from,
      'html' => self::getVolunteerFeedbackReminderEmailHtml($volunteerName, $collectionCampId),
    ];

    $emailSendResult = \CRM_Utils_Mail::send($mailParams);

    if (!$emailSendResult) {
      \Civi::log()->error('Failed to send feedback reminder email', [
        'volunteerEmailId' => $volunteerEmailId,
        'volunteerEmail' => $volunteerEmail,
      ]);
      throw new \CRM_Core_Exception('Failed to send feedback reminder email');
    }
  }

  /**
   * Generates HTML content for the volunteer feedback reminder email.
   *
   * @param string $volunteerName
   * @param int $collectionCampId
   *
   * @return string
   */
  public static function getVolunteerFeedbackReminderEmailHtml($volunteerName, $collectionCampId) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $feedbackFormUrl = $homeUrl . 'volunteer-camp-feedback/#?Eck_Collection_Camp1=' . $collectionCampId;

    $html = "
      <p>Dear $volunteerName,</p>
      <p>Greetings from Goonj!</p>
      <p>Kindly reminding you to share your valuable feedback on the recent collection camp. Your insights are important for us to continue improving in future camps.</p>
      <p>If you haven’t had the chance yet, please take a few minutes to fill out the feedback form here:</p>
      <p><a href=\"$feedbackFormUrl\">Volunteer Feedback Form</a></p>
      <p>We look forward to working with you again!</p>
      <p>Warm regards,<br>Team Goonj</p>";

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
