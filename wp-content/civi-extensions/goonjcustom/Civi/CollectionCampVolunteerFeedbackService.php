<?php

namespace Civi;

use Civi\Core\Service\AutoSubscriber;

/**
 * Collection Camp Outcome Service.
 */
class CollectionCampVolunteerFeedbackService extends AutoSubscriber {

  /**
   * Define subscribed events.
   */
  public static function getSubscribedEvents() {
    return [];
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
      'subject' => 'Reminder to fill the camp feedback form for ' . $campAddress . ' on ' . $endDate->format('Y-m-d'),
      'from' => $from,
      'toEmail' => $volunteerEmailId,
      'replyTo' => $from,
      'html' => self::getVolunteerFeedbackReminderEmailHtml($volunteerName, $collectionCampId, $volunteerEmailId),
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
   * @param int $volunteerEmailId
   *
   * @return string
   */
  public static function getVolunteerFeedbackReminderEmailHtml($volunteerName, $collectionCampId, $volunteerEmailId) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $feedbackFormUrl = $homeUrl . 'volunteer-camp-feedback/#?Eck_Collection_Camp1=' . $collectionCampId;

    $html = "
      <p>Dear $volunteerName,</p>
      <p>Greetings from Goonj!</p>
      <p>We are kindly reminding you to share your valuable feedback on the recent collection camp you attended. Your insights are essential for us to continue improving our future camps and make them even better.</p>
      <p>If you haven’t had the chance yet, please take a few minutes to fill out the feedback form here:</p>
      <p><a href=\"$feedbackFormUrl\">Volunteer Feedback Form</a></p>
      <p>We look forward to working with you again!</p>
      <p>Warm regards,<br>Team Goonj</p>";

    return $html;
  }

}
