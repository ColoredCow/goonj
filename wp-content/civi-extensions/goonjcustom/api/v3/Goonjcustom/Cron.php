<?php

/**
 * @file
 */

/**
 * Civirules.Cron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call
 *
 * @return void
 */

use Civi\Api4\Activity;
use Civi\Api4\Contact;

/**
 *
 */
function _civicrm_api3_goonjcustom_cron_spec(&$spec) {
  // There are no parameters for the civirules cron.
}

/**
 * Civirules.Cron API.
 *
 * @param array $params
 *
 * @return array API result descriptor
 *
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_goonjcustom_cron($params) {
  $returnValues = [];

  try {
    // Activity Assignees.
    $assigneeRecordTypeId = 1;
    $startOfDay = new DateTime('today midnight');
    $endOfDay = new DateTime('tomorrow midnight -1 second');

    $activityAssignees = Activity::get(TRUE)
      ->addSelect('target_contact_id', 'activity_contact.contact_id', 'activity_date_time')
      ->addJoin('ActivityContact AS activity_contact', 'LEFT', ['activity_contact.record_type_id', '=', $assigneeRecordTypeId])
      ->addWhere('activity_type_id:name', '=', 'Induction')
      ->addWhere('status_id:name', '=', 'Scheduled')
      ->addWhere('activity_date_time', '>=', $startOfDay->format('Y-m-d H:i:s'))
      ->addWhere('activity_date_time', '<=', $endOfDay->format('Y-m-d H:i:s'))
      ->execute();

    $groupedResults = [];

    foreach ($activityAssignees as $activity) {
      $assigneeContactId = $activity['activity_contact.contact_id'];
      $targetContactId = $activity['target_contact_id'][0];
      $activityDateTime = $activity['activity_date_time'];

      // Get details for the assignee contact.
      $assigneeDetails = Contact::get(TRUE)
        ->addSelect('email.email', 'display_name')
        ->addJoin('Email AS email', 'LEFT')
        ->addWhere('id', '=', $assigneeContactId)
        ->execute();

      // Get details for the target contact.
      $volunteerDetails = Contact::get(TRUE)
        ->addSelect('email.email', 'phone.phone', 'display_name')
        ->addJoin('Email AS email', 'LEFT')
        ->addJoin('Phone AS phone', 'LEFT')
        ->addWhere('id', '=', $targetContactId)
        ->execute();

      // If the contact_id is not in the grouped results, initialize it.
      if (!isset($groupedResults[$assigneeContactId])) {
        $groupedResults[$assigneeContactId] = [
          'activity_contact.contact_id' => $assigneeContactId,
          'assignee_display_name' => $assigneeDetails[0]['display_name'],
          'assignee_email' => $assigneeDetails[0]['email.email'],
          'target_contact_details' => [],
        ];
      }

      $groupedResults[$assigneeContactId]['target_contact_details'][] = [
        'id' => $targetContactId,
        'activity_date_time' => $activityDateTime,
        "volunteer_display_name" => $volunteerDetails[0]['display_name'],
        'email' => $volunteerDetails[0]['email.email'] ?? '',
        'phone' => $volunteerDetails[0]['phone.phone'] ?? '',
      ];
    }

    // Convert the grouped results to a list of arrays.
    $inductionConductors = array_values($groupedResults);

    [$defaultFromName, $defaultFromEmail] = CRM_Core_BAO_Domain::getNameAndEmail();
    $from = "\"$defaultFromName\" <$defaultFromEmail>";

    foreach ($inductionConductors as $assignee) {

      $mailParams = [
        'groupName' => 'Mailing Event',
        'subject' => 'Reminder: Volunteer Inductions Scheduled for Today',
        'from' => $from,
        'toEmail' => $assignee['assignee_email'],
        'toName' => $assignee['assignee_display_name'],
        'replyTo' => $from,
        'html' => goonjcustom_get_induction_scheduled_email_html($assignee),
        // 'messageTemplateID' => 76, // Uncomment if using a message template
      ];

      try {
        $result = CRM_Utils_Mail::send($mailParams);
      }
      catch (CiviCRM_API3_Exception $e) {
        error_log('Goonj Cron Job: API error - ' . $e->getMessage());
      }
    }
  }
  catch (CiviCRM_API3_Exception $e) {
    error_log('Goonj Cron Job: API error - ' . $e->getMessage());
  }
  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'cron');
}

/**
 *
 */
function goonjcustom_get_induction_scheduled_email_html($assignee) {
  $assigneeContactId = $assignee['activity_contact.contact_id'];
  $assigneeName = $assignee['assignee_display_name'];
  $assigneeEmail = $assignee['assignee_email'];

  $volunteerDetailsHtml = '';

  foreach ($assignee['target_contact_details'] as $target) {
    $volunteerName = $target['volunteer_display_name'];

    $activityDateTime = $target['activity_date_time'];
    $formattedDateTime = new DateTime($activityDateTime);
    $inductionTime = $formattedDateTime->format('F jS, Y g:i A');

    // Append each volunteer's details to the email body.
    $volunteerDetailsHtml .= "
    <li><strong>Name:</strong> $volunteerName<br>
      <strong>Email:</strong> {$target['email']}<br>
      <strong>Phone:</strong> {$target['phone']}<br>
      <strong>Scheduled At:</strong> $inductionTime
    </li><br>";
  }

  $html = "
  <p>Dear $assigneeName,</p>
  <p>This is a friendly reminder that you have volunteer inductions scheduled for today.</p>
  <p>Here are the details for the volunteers being inducted:</p>
  <ul>
    $volunteerDetailsHtml
  </ul>
  <p>Best regards,<br>Goonj</p>";

  return $html;
}
