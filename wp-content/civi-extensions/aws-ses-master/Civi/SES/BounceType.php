<?php

namespace Civi\SES;

use CRM_Core_DAO;

class BounceType {

  public static $types = [
    'SES undetermined' => [
      'type' => 'Undetermined',
      'subType' => 'Undetermined',
      'description' => "The recipient's email provider sent a bounce message. The bounce message didn't contain enough information for Amazon SES to determine the reason for the bounce. The bounce email, which was sent to the address in the Return-Path header of the email that resulted in the bounce, might contain additional information about the issue that caused the email to bounce.",
      'hold_threshold' => 5,
    ],
    'SES permanent: general' => [
      'type' => 'Permanent',
      'subType' => 'General',
      'description' => "The recipient's email provider sent a hard bounce message, but didn't specify the reason for the hard bounce. Important: When you receive this type of bounce notification, you should immediately remove the recipient's email address from your mailing list. Sending messages to addresses that produce hard bounces can have a negative impact on your reputation as a sender. If you continue sending email to addresses that produce hard bounces, we might pause your ability to send additional email.",
      'hold_threshold' => 1,
    ],
    'SES permanent: no email' => [
      'type' => 'Permanent',
      'subType' => 'NoEmail',
      'description' => "The intended recipient's email provider sent a bounce message indicating that the email address doesn't exist. Important: When you receive this type of bounce notification, you should immediately remove the recipient's email address from your mailing list. Sending messages to addresses that don't exist can have a negative impact on your reputation as a sender. If you continue sending email to addresses that don't exist, we might pause your ability to send additional email.",
      'hold_threshold' => 1,
    ],
    'SES permanent: suppressed' => [
      'type' => 'Permanent',
      'subType' => 'Suppressed',
      'description' => "The recipient's email address is on the Amazon SES suppression list because it has a recent history of producing hard bounces. For information about removing an address from the Amazon SES suppression list, see Using the Amazon SES global suppression list.",
      'hold_threshold' => 1,
    ],
    'SES permanent: on account suppression list' => [
      'type' => 'Permanent',
      'subType' => 'OnAccountSuppressionList',
      'description' => "Amazon SES has suppressed sending to this address because it is on the account-level suppression list.",
      'hold_threshold' => 1,
    ],
    'SES transient: general' => [
      'type' => 'Transient',
      'subType' => 'General',
      'description' => "The recipient's email provider sent a general bounce message. You might be able to send a message to the same recipient in the future if the issue that caused the message to bounce is resolved. Note: If you send an email to a recipient who has an active automatic response rule (such as an 'out of the office' message), you might receive this type of notification. Even though the response has a notification type of Bounce, Amazon SES doesn't count automatic responses when it calculates the bounce rate for your account.",
      'hold_threshold' => 0,
    ],
    'SES transient: mailbox full' => [
      'type' => 'Transient',
      'subType' => 'MailboxFull',
      'description' => "The recipient's email provider sent a bounce message because the recipient's inbox was full. You might be able to send to the same recipient in the future when the mailbox is no longer full.",
      'hold_threshold' => 5,
    ],
    'SES transient: message too large' => [
      'type' => 'Transient',
      'subType' => 'MessageTooLarge',
      'description' => "The recipient's email provider sent a bounce message because message you sent was too large. You might be able to send a message to the same recipient if you reduce the size of the message.",
      'hold_threshold' => 0,
    ],
    'SES transient: content rejected' => [
      'type' => 'Transient',
      'subType' => 'ContentRejected',
      'description' => "The recipient's email provider sent a bounce message because the message you sent contains content that the provider doesn't allow. You might be able to send a message to the same recipient if you change the content of the message.",
      'hold_threshold' => 5,
    ],
    'SES transient: attachment rejected' => [
      'type' => 'Transient',
      'subType' => 'AttachmentRejected',
      'description' => "The recipient's email provider sent a bounce message because the message contained an unacceptable attachment. For example, some email providers may reject messages with attachments of a certain file type, or messages with very large attachments. You might be able to send a message to the same recipient if you remove or change the content of the attachment.",
      'hold_threshold' => 5,
    ],
    "SES complaint" => [
      'type' => 'Complaint',
      'subType' => 'Complaint',
      'description' => "The recipient's email provider sent a complaint message because the recipient marked the email as spam, phishing, or similar. You should investigate why this happened and ensure complaints are kept to an absolute minimum.",
      'hold_threshold' => 1,
    ],
  ];

  public static function getId($type, $subType) {
    foreach (self::$types as $name => $fields) {
      if ($fields['type'] == $type && $fields['subType'] == $subType) {
        $query = "SELECT id FROM civicrm_mailing_bounce_type WHERE name = %0";
        $params = [[$name, 'String']];
        $result = CRM_Core_DAO::singleValueQuery($query, $params);
        return $result;
      }
    }
  }

}
