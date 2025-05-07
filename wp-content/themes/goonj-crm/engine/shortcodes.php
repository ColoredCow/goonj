<?php

/**
 * @file
 */

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\MessageTemplate;

add_shortcode('goonj_check_user_form', 'goonj_check_user_action');
add_shortcode('goonj_volunteer_message', 'goonj_custom_message_placeholder');
add_shortcode('goonj_contribution_volunteer_signup_button', 'goonj_contribution_volunteer_signup_button');
add_shortcode('goonj_contribution_monetary_button', 'goonj_contribution_monetary_button');
add_shortcode('goonj_pu_activity_button', 'goonj_pu_activity_button');
add_shortcode('goonj_collection_landing_page', 'goonj_collection_camp_landing_page');
add_shortcode('goonj_collection_camp_past', 'goonj_collection_camp_past_data');
add_shortcode('goonj_induction_slot_details', 'goonj_induction_slot_details');

/**
 *
 */
function goonj_check_user_action($atts) {
  ob_start();
  get_template_part('templates/form', 'check-user', ['purpose' => $atts['purpose']]);
  return ob_get_clean();
}

/**
 *
 */
function goonj_generate_button_html($button_url, $button_text) {
  ob_start();
  get_template_part(
        'templates/primary-button',
        NULL,
        [
          'button_url' => $button_url,
          'button_text' => $button_text,
        ]
  );

  return ob_get_clean();
}

/**
 *
 */

/**
 * Common helper to fetch activity with contact ID.
 */
function goonj_get_activity_with_contact($activity_id) {
  return Activity::get(FALSE)
    ->addSelect('source_contact_id')
    ->addJoin('ActivityContact AS activity_contact', 'LEFT')
    ->addWhere('id', '=', $activity_id)
    ->execute()
    ->first();
}

/**
 * Common helper to get source field ID.
 */
function goonj_get_contribution_source_field_id() {
  $sourceField = CustomField::get(FALSE)
    ->addSelect('id')
    ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
    ->addWhere('name', '=', 'Source')
    ->execute()
    ->single();
  return 'custom_' . $sourceField['id'];
}

/**
 * Common helper to generate checksum for contact.
 */
function goonj_generate_checksum($contact_id) {
  return Contact::getChecksum(FALSE)
    ->setContactId($contact_id)
    ->setTtl(7 * 24 * 60 * 60)
    ->execute()
    ->first()['checksum'];
}

/**
 * Generate Monetary Contribution button if source is present.
 */
function goonj_generate_monetary_button($contact_id, $selectedValue) {
  if (!$selectedValue) {
    return '';
  }

  $sourceFieldId = goonj_get_contribution_source_field_id();
  $checksum = goonj_generate_checksum($contact_id);

  $monetaryUrl = "/contribute/?$sourceFieldId=$selectedValue&cid=$contact_id&cs=$checksum";
  $buttonText = __('Monetary Contribution', 'goonj-crm');

  return goonj_generate_button_html($monetaryUrl, $buttonText);
}

/**
 * Monetary contribution button shortcode.
 */
function goonj_contribution_monetary_button() {
  $activity_id = isset($_GET['activityId']) ? intval($_GET['activityId']) : 0;

  if (empty($activity_id)) {
    \Civi::log()->warning('Activity ID is missing');
    return;
  }

  try {
    $activity = goonj_get_activity_with_contact($activity_id);

    if (empty($activity)) {
      \Civi::log()->info('No activities found for Activity ID:', ['activityId' => $activity_id]);
      return;
    }

    $contactData = Contact::get(FALSE)
      ->addSelect('source')
      ->addWhere('id', '=', $activity_id)
      ->setLimit(1)
      ->execute()
      ->first();

    $selectedValue = NULL;
    if (!empty($contactData['source'])) {
      $parts = explode('/', $contactData['source']);
      $selectedValue = end($parts);
    }

    return goonj_generate_monetary_button($activity_id, $selectedValue);
  }
  catch (\Exception $e) {
    \Civi::log()->error('Error in goonj_contribution_monetary_button: ' . $e->getMessage());
    return '';
  }
}

/**
 * Volunteer signup + monetary contribution button shortcode.
 */
function goonj_contribution_volunteer_signup_button() {
  $activity_id = isset($_GET['activityId']) ? intval($_GET['activityId']) : 0;

  if (empty($activity_id)) {
    \Civi::log()->warning('Activity ID is missing');
    return;
  }

  try {
    $activity = Activity::get(FALSE)
      ->addSelect('source_contact_id')
      ->addJoin('ActivityContact AS activity_contact', 'LEFT')
      ->addWhere('id', '=', $activity_id)
      ->addWhere('activity_type_id:label', '=', 'Material Contribution')
      ->execute()
      ->first();

    if (empty($activity)) {
      \Civi::log()->info('No activities found for Activity ID:', ['activityId' => $activity_id]);
      return;
    }

    $individual_id = $activity['source_contact_id'];

    $contact = Contact::get(FALSE)
      ->addSelect('contact_sub_type')
      ->addWhere('id', '=', $individual_id)
      ->execute()
      ->first();

    if (empty($contact)) {
      \Civi::log()->info('Contact not found', ['contact' => $individual_id]);
      return;
    }

    $contactSubTypes = $contact['contact_sub_type'] ?? [];
    $buttonsHtml = '';

    if (!in_array('Volunteer', $contactSubTypes)) {
      $redirectPath = '/volunteer-registration/form-with-details/';
      $redirectPathWithParams = $redirectPath . '#?' . http_build_query([
        'Individual1' => $individual_id,
        'message' => 'individual-user',
      ]);
      $buttonText = __('Wish to Volunteer?', 'goonj-crm');
      $buttonsHtml .= goonj_generate_button_html($redirectPathWithParams, $buttonText);
    }

    $activityDetails = Activity::get(FALSE)
      ->addSelect(
          'Material_Contribution.Collection_Camp',
          'Material_Contribution.Dropping_Center',
          'Material_Contribution.Institution_Collection_Camp',
          'Material_Contribution.Institution_Dropping_Center'
    )
      ->addWhere('id', '=', $activity_id)
      ->execute()
      ->first();

    $fields = [
      'Material_Contribution.Collection_Camp',
      'Material_Contribution.Dropping_Center',
      'Material_Contribution.Institution_Collection_Camp',
      'Material_Contribution.Institution_Dropping_Center',
    ];

    $selectedValue = NULL;
    foreach ($fields as $field) {
      if (!empty($activityDetails[$field])) {
        $selectedValue = $activityDetails[$field];
        break;
      }
    }

    $buttonsHtml .= goonj_generate_monetary_button($individual_id, $selectedValue);

    return $buttonsHtml;
  }
  catch (\Exception $e) {
    \Civi::log()->error('Error in goonj_contribution_volunteer_signup_button: ' . $e->getMessage());
    return '';
  }
}

/**
 *
 */
function goonj_pu_activity_button() {
  if (!isset($_GET['activityId'])) {
    return;
  }

  $activity_id = absint($_GET['activityId']);

  try {
    $activity = Activity::get(FALSE)
      ->addSelect('custom.*', 'source_contact_id', 'Office_Visit.Goonj_Processing_Center', 'Material_Contribution.Goonj_Office', 'activity_type_id:name')
      ->addWhere('id', '=', $activity_id)
      ->execute()
      ->first();

    if (!$activity) {
      \Civi::log()->info('No activity found', ['activityId' => $activityId]);
      return;
    }

    $individual_id = $activity['source_contact_id'];

    $office_id = goonj_get_goonj_office_id($activity);

    if (!$office_id) {
      \Civi::log()->info('Goonj Office ID is null for Activity ID:', ['activityId' => $activity_id]);
      return;
    }

    return goonj_generate_activity_button($activity, $office_id, $individual_id);

  }
  catch (\Exception $e) {
    \Civi::log()->error('Error in goonj_pu_activity_button: ' . $e->getMessage());
    return;
  }
}

/**
 *
 */
function goonj_collection_camp_landing_page() {
  ob_start();
  get_template_part('templates/collection-landing-page');
  return ob_get_clean();
}

/**
 *
 */
function goonj_collection_camp_past_data() {
  ob_start();
  get_template_part('templates/collection-camp-data');
  return ob_get_clean();
}

/**
 *
 */
function goonj_induction_slot_details() {
  // Retrieve parameters from the GET request.
  $source_contact_id = intval($_GET['source_contact_id'] ?? 0);
  $slot_date = $_GET['slot_date'] ?? '';
  $slot_time = $_GET['slot_time'] ?? '';
  $inductionType = $_GET['induction_type'] ?? '';

  try {
    // Fetch the induction activity for the specified source contact.
    $inductionActivity = Activity::get(FALSE)
      ->addSelect('id', 'activity_date_time', 'status_id:name', 'Induction_Fields.Goonj_Office', 'Induction_Fields.Assign')
      ->addWhere('source_contact_id', '=', $source_contact_id)
      ->addWhere('activity_type_id:name', '=', 'Induction')
      ->execute()->single();

    // Exit if no activity found or the status is not "To be Scheduled".
    if (!$inductionActivity || $inductionActivity['status_id:name'] !== 'To be scheduled') {
      \Civi::log()->info('No valid activity found or status is not To be Scheduled', ['contact_id' => $source_contact_id]);
      return;
    }
    // Combine slot date (d-m-Y) and slot time (h:i A) to form new activity date and time.
    $newActivityDateTime = DateTime::createFromFormat('d-m-Y h:i A', "$slot_date $slot_time");
    if (!$newActivityDateTime) {
      \Civi::log()->error('Invalid date/time format', ['slot_date' => $slot_date, 'slot_time' => $slot_time]);
      return;
    }

    // Update activity date and time and set the status to "Scheduled".
    Activity::update(FALSE)
      ->addValue('activity_date_time', $newActivityDateTime->format('Y-m-d H:i:s'))
      ->addValue('status_id:name', 'Scheduled')
      ->addValue('Induction_Fields.Mode:name', $inductionType)
      ->addWhere('id', '=', $inductionActivity['id'])
      ->execute();

    \Civi::log()->info('Activity updated successfully', [
      'activity_id' => $inductionActivity['id'],
      'new_date_time' => $newActivityDateTime->format('Y-m-d H:i:s'),
    ]);

    // Select the email template based on the induction type.
    $templateTitle = ($inductionType == 'Processing_Unit')
            ? 'Acknowledgment_for_Induction_Slot_Booked'
            : 'Acknowledgment_for_Online_Induction_Slot_Booked';

    // If a template is found, send the email notification.
    $template = MessageTemplate::get(FALSE)
      ->addWhere('msg_title', 'LIKE', $templateTitle . '%')
      ->execute()->single();

    if ($template) {
      $assigneeContact = Contact::get(FALSE)
        ->addSelect('email.email')
        ->addJoin('Email AS email', 'LEFT')
        ->addWhere('id', '=', $inductionActivity['Induction_Fields.Assign'])
        ->execute()->single();

      // Prepare email parameters.
      $emailParams = [
        'contact_id' => $source_contact_id,
        'template_id' => $template['id'],
        'cc' => $assigneeContact['email.email'],
      ];

      // Send the email.
      $emailResult = civicrm_api3('Email', 'send', $emailParams);
    }
  }
  catch (\Exception $e) {
    \Civi::log()->error('Error processing induction slot details', [
      'contact_id' => $source_contact_id,
      'error' => $e->getMessage(),
    ]);
  }
}
