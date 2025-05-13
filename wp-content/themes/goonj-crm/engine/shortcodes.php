<?php

/**
 * @file
 */

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Event;
use Civi\Api4\MessageTemplate;

add_shortcode('goonj_check_user_form', 'goonj_check_user_action');
add_shortcode('goonj_volunteer_message', 'goonj_custom_message_placeholder');
add_shortcode('goonj_contribution_volunteer_signup_button', 'goonj_contribution_volunteer_signup_button');
add_shortcode('goonj_monetary_contribution_button', 'goonj_monetary_contribution_button');
add_shortcode('goonj_event_monetary_contribution_button', 'goonj_event_monetary_contribution_button');
add_shortcode('goonj_material_contribution_button', 'goonj_material_contribution_button');
add_shortcode('goonj_event_material_contribution_button', 'goonj_event_material_contribution_button');
add_shortcode('goonj_pu_activity_button', 'goonj_pu_activity_button');
add_shortcode('goonj_collection_landing_page', 'goonj_collection_camp_landing_page');
add_shortcode('goonj_collection_camp_past', 'goonj_collection_camp_past_data');
add_shortcode('goonj_induction_slot_details', 'goonj_induction_slot_details');

/**
 *
 */
function goonj_check_user_action($atts) {
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
function goonj_get_contribution_source_field_id() {
  $sourceField = CustomField::get(FALSE)
    ->addSelect('id')
    ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
    ->addWhere('name', '=', 'Source')
    ->execute()->single();

  if ($sourceField) {
    return 'custom_' . $sourceField['id'];
  }
}

/**
 *
 */
function goonj_generate_checksum($individualId) {
  return Contact::getChecksum(FALSE)
    ->setContactId($individualId)
    ->setTtl(7 * 24 * 60 * 60)
    ->execute()
    ->first()['checksum'];
}

/**
 *
 */
function goonj_generate_monetary_button($individualId, $collectionCampId) {
  if (empty($collectionCampId)) {
    return;
  }

  $sourceFieldId = goonj_get_contribution_source_field_id();
  $checksum = goonj_generate_checksum($individualId);

  $monetaryUrl = "/contribute/?$sourceFieldId=$collectionCampId&cid=$individualId&cs=$checksum";
  $buttonText = __('Monetary Contribution', 'goonj-crm');

  return goonj_generate_button_html($monetaryUrl, $buttonText);
}

/**
 *
 */
function goonj_generate_material_contribution_button($individualId, $url = '') {
  if (empty($url)) {
    return;
  }

  $checksum = goonj_generate_checksum($individualId);
  $separator = (strpos($url, '?') !== FALSE) ? '&' : '?';
  $fullUrl = $url . $separator . http_build_query(['cs' => $checksum]);

  $buttonText = __('Material Contribution', 'goonj-crm');

  return goonj_generate_button_html($fullUrl, $buttonText);
}

/**
 *
 */
function getContactSource($individualId) {
  return Contact::get(FALSE)
    ->addSelect('source')
    ->addWhere('id', '=', $individualId)
    ->execute()
    ->first();
}

/**
 *
 */
function getEventIdByTitle($title) {
  $event = Event::get(FALSE)
    ->addSelect('id')
    ->addWhere('title', '=', $title)
    ->execute()
    ->first();
  return $event['id'] ?? NULL;
}

/**
 *
 */
function getCollectionCampIdByTitle($title) {
  $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect('id')
    ->addWhere('title', '=', $title)
    ->execute()
    ->first();
  return $collectionCamp['id'] ?? NULL;
}

/**
 *
 */
function getIndividualIdFromActivity($activityId) {
  $activity = Activity::get(FALSE)
    ->addSelect('source_contact_id')
    ->addWhere('id', '=', $activityId)
    ->addWhere('activity_type_id:name', 'IN', ['Office visit', 'Material Contribution'])
    ->execute()
    ->first();
  return $activity['source_contact_id'] ?? NULL;
}

/**
 *f
 */
function getCollectionCampIdFromActivity($activityId) {
  $activities = Activity::get(FALSE)
    ->addSelect(
            'Material_Contribution.Collection_Camp',
            'Material_Contribution.Institution_Collection_Camp',
            'Material_Contribution.Dropping_Center',
            'Material_Contribution.Institution_Dropping_Center',
            'Material_Contribution.Goonj_Office',
			'Office_Visit.Goonj_Processing_Center',
            'Material_Contribution.Event'
        )
    ->addWhere('id', '=', $activityId)
    ->execute()
    ->first();

  if (empty($activities)) {
    return;
  }

  foreach ($activities as $key => $value) {
    if ($key === 'id') {
      continue;
    }
    if (!empty($value)) {
      return $value;
    }
  }
  return;
}

/**
 *
 */
function appendQueryParams($url, $params) {
  $separator = strpos($url, '?') !== FALSE ? '&' : '?';
  return $url . $separator . http_build_query($params);
}

/**
 *
 */
function goonj_event_monetary_contribution_button() {
  $individualId = isset($_GET['individualId']) ? intval($_GET['individualId']) : 0;

  if (empty($individualId)) {
    return;
  }

  $contactData = getContactSource($individualId);
  if (empty($contactData['source'])) {
    \Civi::log()->info("No source title found for contact ID: $individualId");
    return;
  }

  $title = $contactData['source'];
  $eventId = getEventIdByTitle($title);
  if (empty($eventId)) {
    \Civi::log()->info("No event found for source title: $title");
    return;
  }

  return goonj_generate_monetary_button($individualId, $eventId);
}

/**
 *
 */
function goonj_monetary_contribution_button() {
  $activityId = isset($_GET['activityId']) ? intval($_GET['activityId']) : 0;
  $individualId = isset($_GET['individualId']) ? intval($_GET['individualId']) : 0;

  if (empty($individualId) && empty($activityId)) {
    return;
  }

  try {
    if (!empty($individualId)) {
      $contactData = getContactSource($individualId);
      if (empty($contactData['source'])) {
        \Civi::log()->info("No source title found for contact ID: $individualId");
        return;
      }
      $title = $contactData['source'];
      $collectionCampId = getCollectionCampIdByTitle($title);
      if (empty($collectionCampId)) {
        \Civi::log()->info("No collection camp id found for title: $title");
        return;
      }
      return goonj_generate_monetary_button($individualId, $collectionCampId);
    }

    if (!empty($activityId)) {
      $individualId = getIndividualIdFromActivity($activityId);
      if (empty($individualId)) {
        \Civi::log()->info("No source_contact_id found for activity ID: $activityId");
        return;
      }
      $collectionCampId = getCollectionCampIdFromActivity($activityId);
      if (empty($collectionCampId)) {
        \Civi::log()->info("No collection camp id found for activity ID: $activityId");
        return;
      }
      return goonj_generate_monetary_button($individualId, $collectionCampId);
    }
  }
  catch (\Exception $e) {
    \Civi::log()->error("Error in goonj_monetary_contribution_button: {$e->getMessage()}");
    return;
  }
  return;
}

/**
 *
 */
function goonj_event_material_contribution_button() {
  $individualId = isset($_GET['individualId']) ? intval($_GET['individualId']) : 0;

  if (empty($individualId)) {
    return;
  }

  try {
    $contactData = getContactSource($individualId);
    $title = $contactData['source'] ?? '';
    $eventId = getEventIdByTitle($title);
    if (empty($eventId)) {
      \Civi::log()->info("No event found for source title: $title");
      return;
    }

    $url = "/events-material-contribution/#?Material_Contribution.Event=$eventId";
    $url = appendQueryParams($url, ['source_contact_id' => $individualId]);

    return goonj_generate_material_contribution_button($individualId, $url);
  }
  catch (\Exception $e) {
    \Civi::log()->error("Error in goonj_event_material_contribution_button: {$e->getMessage()}");
    return;
  }
}

/**
 *
 */
function goonj_material_contribution_button() {
  $individualId = isset($_GET['individualId']) ? intval($_GET['individualId']) : 0;

  if (empty($individualId)) {
    return;
  }

  try {
    $contactData = getContactSource($individualId);
    $title = $contactData['source'] ?? '';

    $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('subtype:name')
      ->addWhere('title', '=', $title)
      ->execute()
      ->first();

    $subtype = $collectionCamp['subtype:name'] ?? '';
    $collectionCampId = $collectionCamp['id'];
    $subtypeToUrlMap = [
      'Collection_Camp' => "/material-contribution/#?Material_Contribution.Collection_Camp={$collectionCampId}",
      'Dropping_Center' => "/dropping-center/material-contribution/#?Material_Contribution.Dropping_Center={$collectionCampId}",
      'Institution_Collection_Camp' => "/collection-camp-material-contribution/#?Material_Contribution.Institution_Collection_Camp={$collectionCampId}",
      'Institution_Dropping_Center' => "/dropping-center-material-contribution/#?Material_Contribution.Institution_Dropping_Center={$collectionCampId}",
    ];

    $url = $subtypeToUrlMap[$subtype] ?? '';
    if ($url) {
      $url = appendQueryParams($url, ['source_contact_id' => $individualId]);
    }

    return goonj_generate_material_contribution_button($individualId, $url);
  }
  catch (\Exception $e) {
    \Civi::log()->error('Error in goonj_material_contribution_button: ' . $e->getMessage());
    return '';
  }
}

/**
 *
 */
function goonj_contribution_volunteer_signup_button() {
  $activity_id = isset($_GET['activityId']) ? intval($_GET['activityId']) : 0;

  if (empty($activity_id)) {
    \Civi::log()->warning('Activity ID is missing');
    return;
  }

  try {
    $activities = Activity::get(FALSE)
      ->addSelect('source_contact_id')
      ->addJoin('ActivityContact AS activity_contact', 'LEFT')
      ->addWhere('id', '=', $activity_id)
      ->addWhere('activity_type_id:label', '=', 'Material Contribution')
      ->execute();

    if ($activities->count() === 0) {
      \Civi::log()->info('No activities found for Activity ID:', ['activityId' => $activity_id]);
      return;
    }

    $activity = $activities->first();
    $individual_id = $activity['source_contact_id'];

    $contact = Contact::get(FALSE)
      ->addSelect('contact_sub_type')
      ->addWhere('id', '=', $individual_id)
      ->execute()
      ->first();

    if (empty($contact)) {
      \Civi::log()->info('Contact not found', ['contact' => $contact['id']]);
      return;
    }

    $contactSubTypes = $contact['contact_sub_type'] ?? [];

    // If the individual is already a volunteer, don't show the button.
    if (in_array('Volunteer', $contactSubTypes)) {
      return;
    }

    $redirectPath = '/volunteer-registration/form-with-details/';
    $redirectPathWithParams = $redirectPath . '#?' . http_build_query(
    [
      'Individual1' => $individual_id,
      'message' => 'individual-user',
    ]
    );
    $buttonText = __('Wish to Volunteer?', 'goonj-crm');

    return goonj_generate_button_html($redirectPathWithParams, $buttonText);
  }
  catch (\Exception $e) {
    \Civi::log()->error('Error in goonj_contribution_volunteer_signup_button: ' . $e->getMessage());
    return;
  }
}

/**
 *
 */
function goonj_pu_activity_button() {
  if (!isset($_GET['activityId'])) {
    return;
  }

  $activity_id = isset($_GET['activityId']) ? intval($_GET['activityId']) : 0;

  try {
    $activity = Activity::get(FALSE)
      ->addSelect('custom.*', 'source_contact_id', 'Office_Visit.Goonj_Processing_Center', 'Material_Contribution.Goonj_Office', 'activity_type_id:name')
      ->addWhere('id', '=', $activity_id)
      ->execute()
      ->first();

    if (!$activity) {
      \Civi::log()->info('No activity found', ['activityId' => $activity_id]);
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

    // Fetch the email template.
    $template = MessageTemplate::get(FALSE)
      ->addWhere('msg_title', 'LIKE', $templateTitle . '%')
      ->execute()->single();

    // If a template is found, send the email notification.
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
