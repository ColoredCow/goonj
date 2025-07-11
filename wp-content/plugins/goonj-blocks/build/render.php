<?php

/**
 * @file
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

require_once __DIR__ . '/functions.php';
use Civi\Api4\CustomField;

$target            = get_query_var('target');
$action_target     = get_query_var('action_target');
$source_contact_id = $action_target['id'] ?? NULL;

if ($target === 'induction-schedule') {
  $slots = generate_induction_slots($source_contact_id);
}


$headings = [
  'collection-camp' => 'Collection Camp',
  'dropping-center' => 'Dropping Center',
  'institution-collection-camp' => 'Collection Camp',
  'institution-dropping-center' => 'Institution Dropping Center',
  'processing-center' => 'Processing Center',
  'induction-schedule' => 'Induction Schedule',
  'goonj-activities' => 'Goonj Activities',
  'institution-goonj-activities' => 'Institution Goonj Activities',
  'events' => 'Goonj Events',
];

$heading_text = $headings[$target];

$collection_camp_register_link = sprintf(
    '/volunteer-registration/form/#?source=%s&state_province_id=%s&city=%s',
    $action_target['title'],
    $action_target['Collection_Camp_Intent_Details.State'],
    $action_target['Collection_Camp_Intent_Details.City'],
);

$dropping_center_register_link = sprintf(
    '/volunteer-registration/form/#?source=%s&state_province_id=%s&city=%s',
    $action_target['title'],
    $action_target['Dropping_Centre.State'],
    $action_target['Dropping_Centre.District_City'],
);

$goonj_activities_register_link = sprintf(
    '/goonj-activities-volunteer-check/?source=%s',
    $action_target['title'],
);

$attendee_activity_feedback_link = sprintf(
    '/goonj-activities-attendee-check/?source=%s',
    $action_target['title'],
);

$institution_goonj_activities_register_link = sprintf(
    '/institution-goonj-activities-volunteer-check/?source=%s',
    $action_target['title'],
);
$events_registration_link = sprintf(
    '/goonj-inititated-events-volunteer-check/?source=%s',
    $action_target['title'],
);
$participant_registration_link = sprintf(
    '/civicrm/event/register/?reset=1&id=%s',
    $action_target['id'],
);


$institution_collection_camp_register_link = sprintf(
    '/institution-collection-camp-individual-volunteer-check/?source=%s',
    $action_target['title'],
);

$institution_dropping_center_register_link = sprintf(
    '/volunteer-registration/form/#?source=%s&state_province_id=%s&city=%s',
    $action_target['title'],
    $action_target['Institution_Dropping_Center_Intent.State'],
    $action_target['Institution_Dropping_Center_Intent.District_City'],
);

$material_contribution_link = sprintf(
    '/collection-camp-contribution?source=%s&target_id=%s&state_province_id=%s&city=%s',
    $action_target['title'],
    $action_target['id'],
    $action_target['Collection_Camp_Intent_Details.State'],
    $action_target['Collection_Camp_Intent_Details.City'],
);

$event_material_contribution_link = sprintf(
    '/events-contribution-verification/?source=%s&target_id=%s',
    $action_target['title'],
    $action_target['id'],
);


$institution_collection_camp_material_contribution_link = sprintf(
    '/institution-collection-camp-contribution?source=%s&target_id=%s&state_province_id=%s&city=%s',
    $action_target['title'],
    $action_target['id'],
    $action_target['Institution_Collection_Camp_Intent.State'],
    $action_target['Institution_Collection_Camp_Intent.District_City'],
);

$institution_dropping_center_material_contribution_link = sprintf(
    '/institution-dropping-center-contribution?source=%s&target_id=%s&state_province_id=%s&city=%s',
    $action_target['title'],
    $action_target['id'],
    $action_target['Institution_Dropping_Center_Intent.State'],
    $action_target['Institution_Dropping_Center_Intent.District_City'],
);

$institution_dropping_center_register_link = sprintf(
    '/institution-dropping-center-individual-volunteer-check/?source=%s',
    $action_target['title'],
);

$sourceField = CustomField::get(FALSE)
  ->addSelect('id')
  ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
  ->addWhere('name', '=', 'Source')
  ->execute()->single();

$sourceFieldId = 'custom_' . $sourceField['id'];

$base_donation_link = home_url('/contribute');
$donation_link = $base_donation_link . '?' . $sourceFieldId . '=' . $source_contact_id;

$dropping_center_material_contribution_link = sprintf(
    '/dropping-center-contribution?source=%s&target_id=%s&state_province_id=%s&city=%s',
    $action_target['title'],
    $action_target['id'],
    $action_target['Dropping_Centre.State'],
    $action_target['Dropping_Centre.District_City'],
);

$pu_visit_check_link = sprintf(
    '/processing-center/office-visit/?target_id=%s',
    $action_target['id']
);

$pu_material_contribution_check_link = sprintf(
    '/processing-center/material-contribution/?target_id=%s',
    $action_target['id']
);

$institution_attendee_activity_feedback_link = sprintf(
    '/institution-goonj-activities-attendee-check-user/?source=%s',
    $action_target['title']
);

$puSourceField = CustomField::get(FALSE)
  ->addSelect('id')
  ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
  ->addWhere('name', '=', 'PU_Source')
  ->execute()->single();

$puSourceFieldId = 'custom_' . $puSourceField['id'];

$base_pu_donation_link = home_url('/contribute');
$pu_donation_link = $base_pu_donation_link . '?' . $puSourceFieldId . '=' . $source_contact_id;

$eventSourceField = CustomField::get(FALSE)
  ->addSelect('id')
  ->addWhere('custom_group_id:name', '=', 'Contribution_Details')
  ->addWhere('name', '=', 'Events')
  ->execute()->single();

$eventSourceFieldId = 'custom_' . $eventSourceField['id'];
$base_event_donation_link = home_url('/contribute');
$event_donation_link = $base_event_donation_link . '?' . $eventSourceFieldId . '=' . $source_contact_id;


$target_data = [
  'dropping-center' => [
    'volunteer_name' => 'Collection_Camp_Core_Details.Contact_Id.display_name',
    'address' => 'Dropping_Centre.Where_do_you_wish_to_open_dropping_center_Address_',
    'address_label' => 'Goonj volunteer run dropping center (Address)',
    'contribution_link' => $dropping_center_material_contribution_link,
    'donation_link' => $donation_link,
    'register_link' => $dropping_center_register_link,
  ],
  'collection-camp' => [
    'start_time' => 'Collection_Camp_Intent_Details.Start_Date',
    'end_time' => 'Collection_Camp_Intent_Details.End_Date',
    'address' => 'Collection_Camp_Intent_Details.Location_Area_of_camp',
    'address_label' => 'Address of the camp',
    'contribution_link' => $material_contribution_link,
    'donation_link' => $donation_link,
    'register_link' => $collection_camp_register_link,
  ],
  'institution-collection-camp' => [
    'start_time' => 'Institution_Collection_Camp_Intent.Collections_will_start_on_Date_',
    'end_time' => 'Institution_Collection_Camp_Intent.Collections_will_end_on_Date_',
    'address' => 'Institution_Collection_Camp_Intent.Collection_Camp_Address',
    'address_label' => 'Address of the camp',
    'contribution_link' => $institution_collection_camp_material_contribution_link,
    'donation_link' => $donation_link,
    'register_link' => $institution_collection_camp_register_link,
    'name_of_institute' => 'Institution_Collection_Camp_Intent.Organization_Name.display_name',
  ],
  'goonj-activities' => [
    'start_time' => 'Goonj_Activities.Start_Date',
    'end_time' => 'Goonj_Activities.End_Date',
    'address' => 'Goonj_Activities.Where_do_you_wish_to_organise_the_activity_',
    'address_label' => 'Address of the Activity',
    'donation_link' => $donation_link,
    'register_link' => $goonj_activities_register_link,
    'include_attendee_feedback_link' => $attendee_activity_feedback_link,
    'should_include_attendee_feedback' => $action_target['Goonj_Activities.Include_Attendee_Feedback_Form'],
  ],
  'institution-dropping-center' => [
    'volunteer_name' => 'Institution_Dropping_Center_Intent.Institution_POC.display_name',
    'address' => 'Institution_Dropping_Center_Intent.Dropping_Center_Address',
    'address_label' => 'Goonj volunteer run dropping center (Address)',
    'contribution_link' => $institution_dropping_center_material_contribution_link,
    'donation_link' => $donation_link,
    'register_link' => $institution_dropping_center_register_link,
    'name_of_institute' => 'Institution_Dropping_Center_Intent.Organization_Name.display_name',
  ],
  'institution-goonj-activities' => [
    'start_time' => 'Institution_Goonj_Activities.Start_Date',
    'end_time' => 'Institution_Goonj_Activities.End_Date',
    'address' => 'Institution_Goonj_Activities.Where_do_you_wish_to_organise_the_activity_',
    'address_label' => 'Address of the Activity',
    'donation_link' => $donation_link,
    'register_link' => $institution_goonj_activities_register_link,
    'include_attendee_feedback_link' => $institution_attendee_activity_feedback_link,
    'should_include_attendee_feedback' => $action_target['Institution_Goonj_Activities.Include_Attendee_Feedback_Form'],
    'name_of_institute' => 'Institution_Goonj_Activities.Organization_Name.display_name',
  ],
  'events' => [
    'start_time' => 'start_date',
    'end_time' => 'end_date',
    'address' => 'address',
    'address_label' => 'Address of the Event',
    'contribution_link' => $event_material_contribution_link,
    'donation_link' => $event_donation_link,
    'register_link' => $events_registration_link,
    'event_registration' => $participant_registration_link,
  ],
];

if (in_array($target, ['collection-camp', 'institution-collection-camp', 'dropping-center', 'goonj-activities', 'institution-dropping-center', 'institution-goonj-activities', 'events'])) :
  $target_info = $target_data[$target];

  try {
    $start_date = new DateTime($action_target[$target_info['start_time']]);
    $end_date = new DateTime($action_target[$target_info['end_time']]);
    $activity_date = new DateTime($action_target[$target_info['activity_date']]);
  }
  catch (Exception $e) {
    Civi::log()->error("Error creating DateTime object: " . $e->getMessage());
    // Default to current date/time.
    $start_date = $end_date = new DateTime();
  }

  $address = $action_target[$target_info['address']];
  $contribution_link = $target_info['contribution_link'];
  $address_label = $target_info['address_label'];
  $volunteer_name = $action_target[$target_info['volunteer_name']];
  $donation_link = $target_info['donation_link'];
  $register_link = $target_info['register_link'];
  $participant_registration_link = $target_info['event_registration'];

  $name_of_institute = $action_target[$target_info['name_of_institute']];

  $include_attendee_feedback_link = $target_info['include_attendee_feedback_link'];
  $should_include_attendee_feedback = $target_info['should_include_attendee_feedback'];

  ?>
    <div class="wp-block-gb-heading-wrapper">
        <h2 class="wp-block-gb-heading">
            <?php echo ($target === 'events') ? esc_html($action_target['title']) : ''; ?>
        </h2>
    </div>
    <table class="wp-block-gb-table">
        <tbody>
        <?php if ($target === 'dropping-center' || $target === 'institution-dropping-center') : ?>
            <?php if ($target === 'institution-dropping-center' && !empty($name_of_institute)) : ?>
        <tr class="wp-block-gb-table-row">
            <td class="wp-block-gb-table-cell wp-block-gb-table-header">Name of Institute</td>
            <td class="wp-block-gb-table-cell"><?php echo esc_html($name_of_institute); ?></td>
        </tr>
        <?php endif; ?>
        <tr class="wp-block-gb-table-row">
            <td class="wp-block-gb-table-cell wp-block-gb-table-header">
                <?php echo ($target === 'institution-dropping-center') ? 'Coordinator Name' : 'Volunteer Name'; ?>
            </td>
            <td class="wp-block-gb-table-cell">
                <?php echo esc_html($volunteer_name); ?>
            </td>
        </tr>
   
        <?php endif; ?>
  <?php if ($target === 'collection-camp' || $target === 'institution-collection-camp' || $target === 'goonj-activities' || $target === 'institution-goonj-activities' || $target === 'events') : ?>
    <tr class="wp-block-gb-table-row">
        <td class="wp-block-gb-table-cell wp-block-gb-table-header">From</td>
        <td class="wp-block-gb-table-cell"><?php echo gb_format_date($start_date); ?></td>
    </tr>
    <tr class="wp-block-gb-table-row">
        <td class="wp-block-gb-table-cell wp-block-gb-table-header">To</td>
        <td class="wp-block-gb-table-cell"><?php echo gb_format_date($end_date); ?></td>
    </tr>
    <tr class="wp-block-gb-table-row">
        <td class="wp-block-gb-table-cell wp-block-gb-table-header">Time</td>
        <td class="wp-block-gb-table-cell"><?php echo gb_format_time_range($start_date, $end_date); ?></td>
    </tr>
    <?php if (($target === 'institution-collection-camp' || $target === 'institution-goonj-activities') && !empty($name_of_institute)) : ?>
        <tr class="wp-block-gb-table-row">
            <td class="wp-block-gb-table-cell wp-block-gb-table-header">Name of Institute</td>
            <td class="wp-block-gb-table-cell"><?php echo esc_html($name_of_institute); ?></td>
        </tr>
    <?php endif; ?>
  <?php endif; ?>
            <tr class="wp-block-gb-table-row">
                <td class="wp-block-gb-table-cell wp-block-gb-table-header"><?php echo esc_html($address_label); ?></td>
                <td class="wp-block-gb-table-cell">
                    <?php
                    if ($target === 'events') {
                      echo CRM_Utils_Address::format($address);
                    }
                    else {
                      echo esc_html($address);
                    }
                    ?>
                </td>
            </tr>
        </tbody>
    </table>
    <div <?php echo get_block_wrapper_attributes(); ?>>
        <a href="<?php echo esc_url($register_link); ?>" class="wp-block-gb-action-button gb-button-spacing">
            <?php esc_html_e('Volunteer with Goonj', 'goonj-blocks'); ?>
        </a>
        <?php if ($target === 'events') : ?>
            <a href="<?php echo esc_url($participant_registration_link); ?>" class="wp-block-gb-action-button gb-button-spacing">
                <?php esc_html_e('Event Registration', 'goonj-blocks'); ?>
            </a>
        <?php endif; ?>
        <?php if (!in_array($target, ['goonj-activities', 'institution-goonj-activities'])) : ?>
            <a href="<?php echo esc_url($contribution_link ?? '#'); ?>" class="wp-block-gb-action-button gb-button-spacing">
                <?php esc_html_e('Record your Material Contribution', 'goonj-blocks'); ?>
            </a>
        <?php endif; ?>
        <?php if (in_array($target, ['events', 'collection-camp', 'dropping-center', 'institution-collection-camp', 'institution-dropping-center', 'institution-goonj-activities', 'goonj-activities'])) : ?>
            <a href="<?php echo esc_url($donation_link); ?>" class="wp-block-gb-action-button gb-button-spacing">
                <?php esc_html_e('Monetary Contribution', 'goonj-blocks'); ?>
            </a>
        <?php endif; ?>
        <?php if ($should_include_attendee_feedback): ?>
            <a href="<?php echo esc_url($include_attendee_feedback_link ?? '#'); ?>" class="wp-block-gb-action-button gb-button-spacing">
                <?php esc_html_e('Record Attendee Feedback', 'goonj-blocks'); ?>
            </a>
        <?php endif; ?>
    </div>
  <?php elseif ('processing-center' === $target) : ?>
        <table class="wp-block-gb-table">
            <tbody>
                <tr class="wp-block-gb-table-row">
                    <td class="wp-block-gb-table-cell wp-block-gb-table-header">Address</td>
                    <td class="wp-block-gb-table-cell"><?php echo CRM_Utils_Address::format($action_target['address']); ?></td>
                </tr>
            </tbody>
        </table>
        <div <?php echo get_block_wrapper_attributes(); ?>>
            <a href="<?php echo esc_url($pu_visit_check_link); ?>" class="wp-block-gb-action-button">
                <?php esc_html_e('Learning Journey', 'goonj-blocks'); ?>
            </a>
            <a href="<?php echo esc_url($pu_material_contribution_check_link); ?>" class="wp-block-gb-action-button">
                <?php esc_html_e('Material Contribution', 'goonj-blocks'); ?>
            </a>
            <a href="<?php echo esc_url($pu_donation_link); ?>" class="wp-block-gb-action-button">
                <?php esc_html_e('Monetary Contribution', 'goonj-blocks'); ?>
            </a>
        </div>
  <?php elseif ('induction-schedule' === $target) : ?>
    <div class="wp-block-gb-slots-wrapper">
        <?php if (!empty($slots) && in_array($slots['status'], ['Scheduled', 'Completed', 'Cancelled'])) : ?>
            <h2 class="wp-block-gb-heading"><?php esc_html_e('Your Induction Status', 'goonj-blocks'); ?></h2>
            <p>
                <?php
                echo esc_html__('Induction Status: ', 'goonj-blocks') . esc_html($slots['status']);
                echo '<br>';
                echo esc_html__('Induction Date and Time: ', 'goonj-blocks') . esc_html($slots['date']);
                ?>
            </p>
        <?php elseif ($slots['status'] === 'No_show') : ?>
            <p>To schedule your Induction/Orientation please get in touch with the Goonj team on :</p>
                <div class="contact-info">
                    <div class="contact-item">
                        <a href="mailto:mail@goonj.org" class="contact-link">mail@goonj.org</a>
                    </div>
                    <div class="contact-item">
                        <a href="tel:01141401216" class="contact-link">011-41401216</a>
                    </div>
                </div>
            <p>Warm regards,</p>
            <p> Team Goonj..</p>
        <?php else : ?>
            <h2 class="wp-block-gb-heading"><?php esc_html_e('Available Induction Slots', 'goonj-blocks'); ?></h2>
            <div class="wp-block-gb-slots-grid">
                <?php foreach ($slots as $slot) : ?>
                    <div class="wp-block-gb-slot-box">
                        <h4 class="wp-block-gb-slot-day"><?php echo esc_html($slot['day']); ?></h4>
                        <p class="wp-block-gb-slot-date"><?php echo esc_html($slot['date']); ?></p>
                        <p class="wp-block-gb-slot-time"><?php echo esc_html($slot['time']); ?></p>

                        <?php
                        $book_slot_link = sprintf(
                            '/induction-schedule/success/?source_contact_id=%s&slot_date=%s&slot_time=%s&induction_type=%s',
                            $action_target['id'],
                            urlencode($slot['date']),
                            urlencode($slot['time']),
                            urldecode($slot['induction_type']),
                        );

                        // Mark slot as disabled if full.
                        $is_disabled = ($slot['activity_count'] > 20);
                        ?>

                        <a href="<?php echo esc_url($is_disabled ? '#' : $book_slot_link); ?>"
                        class="wp-block-gb-action-button <?php echo $is_disabled ? 'disabled' : ''; ?>">
                            <?php echo $is_disabled ? esc_html__('Slot Full', 'goonj-blocks') : esc_html__('Book Slot', 'goonj-blocks'); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
  <?php endif;
