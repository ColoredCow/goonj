<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;

/**
 *
 */
class DroppingCenterFeedbackCron {

  /**
   * Send feedback email if it hasn't been sent yet.
   *
   * @param array $meta
   *   Meta data for the Dropping Center.
   * @param string $from
   *   Email address for 'from'.
   *
   * @throws \CRM_Core_Exception
   */
  public static function processDroppingCenterStatus($meta, $from) {
    $droppingCenterId = $meta['Dropping_Center_Meta.Dropping_Center'];
    $initiatorId = $meta['Dropping_Center_Meta.Dropping_Center.Collection_Camp_Core_Details.Contact_Id'];
    $status = $meta['Status.Feedback_Email_Delivered:name'];

    // Get recipient email and name.
    $campAttendedBy = Contact::get(TRUE)
      ->addSelect('email.email', 'display_name')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('id', '=', $initiatorId)
      ->execute()->single();

    $contactEmailId = $campAttendedBy['email.email'];
    $organizingContactName = $campAttendedBy['display_name'];

    if (!$status) {
      $mailParams = [
        'subject' => 'Your Feedback on Managing the Goonj Dropping Center',
        'from' => $from,
        'toEmail' => $contactEmailId,
        'replyTo' => $from,
        'html' => self::sendFeedbackEmail($organizingContactName, $droppingCenterId),
      ];

      // Send the email.
      $feedbackEmailSendResult = \CRM_Utils_Mail::send($mailParams);

      // Update status if the email is sent.
      if ($feedbackEmailSendResult) {
        EckEntity::update('Dropping_Center_Meta', TRUE)
          ->addValue('Status.Feedback_Email_Delivered:name', 1)
          ->addWhere('Dropping_Center_Meta.Dropping_Center', '=', $droppingCenterId)
          ->execute();
      }
    }
  }

  /**
   * Generate HTML for feedback email.
   *
   * @param string $organizingContactName
   * @param int $droppingCenterId
   *
   * @return string HTML content for email
   */
  public static function sendFeedbackEmail($organizingContactName, $droppingCenterId) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();

    // URL for the feedback form.
    $volunteerFeedback = $homeUrl . 'volunteer-feedback/#?Eck_Collection_Camp1=' . $droppingCenterId;

    $html = "
      <p>Dear $organizingContactName,</p>
    
      <p>Thank you for being such an outstanding representative of Goonj! 
      Your dedication, time, and passion are truly making a difference as we work to bring essential materials to remote villages across the country.</p>
    
      <p>As part of our commitment to constant improvement, we would greatly appreciate hearing about your experience managing the Dropping Center. 
      If you could spare a few moments to complete our feedback form, your input would be invaluable to us!</p>
    
      <p><a href='$volunteerFeedback'>Click here to access the feedback form.</a></p>
    
      <p>We encourage you to share any highlights, suggestions, or challenges you’ve encountered. Together, we can refine and enhance this process even further.</p>
    
      <p>We look forward to continuing this important journey with you!</p>
    
      <p>Warm Regards,<br>
      Team Goonj</p>
    ";

    return $html;
  }

}
