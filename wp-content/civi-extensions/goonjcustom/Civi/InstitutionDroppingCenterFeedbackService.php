<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;

/**
 * Class to manage sending feedback emails for Dropping Centers.
 */
class InstitutionDroppingCenterFeedbackService {

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
  public static function processInstitutionDroppingCenterStatus($droppingCenterId, $initiatorId, $status, $from) {
    // Get recipient email and name.
    $contactDetails = self::getContactDetails($initiatorId);

    if ($contactDetails) {
      $contactEmailId = $contactDetails['email.email'];
      $organizingContactName = $contactDetails['display_name'];

      if ($status) {
        return;
      }

      self::sendFeedbackEmail($organizingContactName, $droppingCenterId, $contactEmailId, $from);

      // Update status if the email is sent.
      EckEntity::update('Dropping_Center_Meta', TRUE)
        ->addValue('Status.Feedback_Email_Delivered', 1)
        ->addWhere('Dropping_Center_Meta.Institution_Dropping_Center', '=', $droppingCenterId)
        ->execute();
    }
  }

  /**
   * Get contact details for the given initiator ID.
   *
   * @param int $initiatorId
   *   The ID of the contact to retrieve.
   *
   * @return array|null
   *   An associative array containing the email and display name, or null if not found.
   */
  public static function getContactDetails($initiatorId) {
    return Contact::get(TRUE)
      ->addSelect('email.email', 'display_name')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('id', '=', $initiatorId)
      ->execute()->single();
  }

  /**
   *
   */
  public static function updateFeedbackLastSentDate($droppingCenterId) {
    EckEntity::update('Collection_Camp', TRUE)
      ->addWhere('id', '=', $droppingCenterId)
      ->addValue('Dropping_Centre.last_feedback_sent_date', (new \DateTime())->format('Y-m-d'))
      ->execute();
  }

  /**
   * Send feedback email to the contact.
   *
   * @param string $organizingContactName
   *   Name of the organizing contact.
   * @param int $droppingCenterId
   *   ID of the Dropping Center.
   * @param string $contactEmailId
   *   Email address of the recipient.
   * @param string $from
   *   Email address for 'from'.
   */
  public static function sendFeedbackEmail($organizingContactName, $droppingCenterId, $contactEmailId, $from) {
    $mailParams = [
      'subject' => 'Your Feedback on Managing the Goonj Dropping Center',
      'from' => $from,
      'toEmail' => $contactEmailId,
      'replyTo' => $from,
      'html' => self::generateFeedbackEmailHtml($organizingContactName, $droppingCenterId),
    ];

    // Send the email.
    \CRM_Utils_Mail::send($mailParams);
  }

  /**
   * Generate HTML for feedback email.
   *
   * @param string $organizingContactName
   * @param int $droppingCenterId
   *
   * @return string HTML content for email
   */
  public static function generateFeedbackEmailHtml($organizingContactName, $droppingCenterId) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $volunteerFeedback = $homeUrl . 'volunteer-feedback-2/#?Eck_Collection_Camp1=' . $droppingCenterId;

    $html = "
      <p>Dear $organizingContactName,</p>
    
      <p>Thank you for being such an outstanding representative of Goonj! 
      Your dedication, time, and passion are truly making a difference as we work to bring essential materials to remote villages across the country.</p>
    
      <p>As part of our commitment to constant improvement, we would greatly appreciate hearing about your experience managing the Dropping Center. 
      If you could spare a few moments to complete our feedback form, your input would be invaluable to us!</p>
    
      <p><a href='$volunteerFeedback'>Feedback Form Link.</a></p>
    
      <p>We encourage you to share any highlights, suggestions, or challenges you’ve encountered. Together, we can refine and enhance this process even further.</p>
    
      <p>We look forward to continuing this important journey with you!</p>
    
      <p>Warm Regards,<br>
      Team Goonj</p>
    ";

    return $html;
  }

}
