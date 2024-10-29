<?php

add_shortcode( 'goonj_check_user_form', 'goonj_check_user_action' );
add_shortcode( 'goonj_volunteer_message', 'goonj_custom_message_placeholder' );
add_shortcode( 'goonj_contribution_volunteer_signup_button', 'goonj_contribution_volunteer_signup_button' );
add_shortcode( 'goonj_pu_activity_button', 'goonj_pu_activity_button' );
add_shortcode( 'goonj_collection_landing_page', 'goonj_collection_camp_landing_page' );
add_shortcode( 'goonj_collection_camp_past', 'goonj_collection_camp_past_data' );
add_shortcode( 'goonj_induction_slot_details', 'goonj_induction_slot_details' );

function goonj_check_user_action( $atts ) {
	get_template_part( 'templates/form', 'check-user', array( 'purpose' => $atts['purpose'] ) );
	return ob_get_clean();
}

function goonj_generate_button_html( $button_url, $button_text ) {
	ob_start();
	get_template_part(
		'templates/primary-button',
		null,
		array(
			'button_url' => $button_url,
			'button_text' => $button_text,
		)
	);

	return ob_get_clean();
}

function goonj_contribution_volunteer_signup_button() {
	$activity_id = isset( $_GET['activityId'] ) ? intval( $_GET['activityId'] ) : 0;

	if ( empty( $activity_id ) ) {
		\Civi::log()->warning( 'Activity ID is missing' );
		return;
	}

	try {
		$activities = \Civi\Api4\Activity::get( false )
			->addSelect( 'source_contact_id' )
			->addJoin( 'ActivityContact AS activity_contact', 'LEFT' )
			->addWhere( 'id', '=', $activity_id )
			->addWhere( 'activity_type_id:label', '=', 'Material Contribution' )
			->execute();

		if ( $activities->count() === 0 ) {
			\Civi::log()->info( 'No activities found for Activity ID:', array( 'activityId' => $activity_id ) );
			return;
		}

		$activity = $activities->first();
		$individual_id = $activity['source_contact_id'];

		$contact = \Civi\Api4\Contact::get( false )
			->addSelect( 'contact_sub_type' )
			->addWhere( 'id', '=', $individual_id )
			->execute()
			->first();

		if ( empty( $contact ) ) {
			\Civi::log()->info( 'Contact not found', array( 'contact' => $contact['id'] ) );
			return;
		}

		$contactSubTypes = $contact['contact_sub_type'] ?? array();

		// If the individual is already a volunteer, don't show the button
		if ( in_array( 'Volunteer', $contactSubTypes ) ) {
			return;
		}

		$redirectPath = '/volunteer-registration/form-with-details/';
		$redirectPathWithParams = $redirectPath . '#?' . http_build_query(
			array(
				'Individual1' => $individual_id,
				'message' => 'individual-user',
			)
		);
		$buttonText = __( 'Wish to Volunteer?', 'goonj-crm' );

		return goonj_generate_button_html( $redirectPathWithParams, $buttonText );
	} catch ( \Exception $e ) {
		\Civi::log()->error( 'Error in goonj_contribution_volunteer_signup_button: ' . $e->getMessage() );
		return;
	}
}

function goonj_pu_activity_button() {
	if ( ! isset( $_GET['activityId'] ) ) {
		return;
	}

	$activity_id = absint( $_GET['activityId'] );

	try {
		$activity = \Civi\Api4\Activity::get(false)
			->addSelect('source_contact_id', 'Office_Visit.Goonj_Processing_Center', 'Material_Contribution.Goonj_Office', 'activity_type_id:name')
			->addWhere('id', '=', $activity_id)
			->execute()
			->first();
		
		if (!$activity) {
			\Civi::log()->info('No activity found', ['activityId' => $activityId]);
			return;
		}

		$individual_id = $activity['source_contact_id'];

		$office_id = goonj_get_goonj_office_id( $activity );

		if ( ! $office_id ) {
			\Civi::log()->info( 'Goonj Office ID is null for Activity ID:', array( 'activityId' => $activity_id ) );
			return;
		}

		return goonj_generate_activity_button( $activity, $office_id, $individual_id );

	} catch ( \Exception $e ) {
		\Civi::log()->error( 'Error in goonj_pu_activity_button: ' . $e->getMessage() );
		return;
	}
}

function goonj_collection_camp_landing_page() {
	ob_start();
	get_template_part( 'templates/collection-landing-page' );
	return ob_get_clean();
}

function goonj_collection_camp_past_data() {
	ob_start();
	get_template_part( 'templates/collection-camp-data' );
	return ob_get_clean();
}

function goonj_induction_slot_details() {
    $source_contact_id = intval($_GET['source_contact_id'] ?? 0);
    $slot_date = $_GET['slot_date'] ?? '';
    $slot_time = $_GET['slot_time'] ?? '';
    $inductionType = $_GET['induction_type'] ?? '';
    \Civi::log()->info('Induction Type', ['type' => $inductionType]);

    // Fetch the induction activity for the source contact
    $inductionActivity = \Civi\Api4\Activity::get(FALSE)
        ->addSelect('id', 'activity_date_time', 'status_id:name', 'Induction_Fields.Goonj_Office', 'Induction_Fields.Assign')
        ->addWhere('source_contact_id', '=', $source_contact_id)
        ->addWhere('activity_type_id:name', '=', 'Induction')
        ->setLimit(1)
        ->execute()
        ->first();

    // Exit if no activity found or already 'Scheduled' or 'Completed'
    if (!$inductionActivity || in_array($inductionActivity['status_id:name'], ['Scheduled', 'Completed'])) {
        \Civi::log()->info('No valid activity found or already scheduled/completed', ['contact_id' => $source_contact_id]);
        return;
    }

    // Combine slot date (d-m-Y) and slot time (H:i) to form new activity date time
    $newActivityDateTime = DateTime::createFromFormat('d-m-Y H:i', "$slot_date $slot_time");
    if (!$newActivityDateTime) {
        \Civi::log()->error('Invalid date/time format', ['slot_date' => $slot_date, 'slot_time' => $slot_time]);
        return;
    }

    // Update activity date time and status to "Scheduled"
    \Civi\Api4\Activity::update(FALSE)
        ->addValue('activity_date_time', $newActivityDateTime->format('Y-m-d H:i:s'))
        ->addValue('status_id:name', 'Scheduled')
        ->addValue('Induction_Fields.Mode:name', $inductionType)
        ->addWhere('id', '=', $inductionActivity['id'])
        ->execute();
    \Civi::log()->info('Activity updated successfully', [
        'activity_id' => $inductionActivity['id'],
        'new_date_time' => $newActivityDateTime->format('Y-m-d H:i:s')
    ]);

    // Select email template based on induction type
	// Debug log to check the value of inductionType
	\Civi::log()->info('Induction Type Check', ['inductionType' => $inductionType]);

	// Select email template based on induction type
	$templateTitle = ($inductionType == 'Processing_Unit') 
		? 'Acknowledgment_for_Induction_Slot_Booked' 
		: 'Acknowledgment_for_Online_Induction_Slot_Booked';
	\Civi::log()->info('templateTitle', ['templateTitle' => $templateTitle, 'inductionType' => $inductionType]);

	$template = \Civi\Api4\MessageTemplate::get(FALSE)
		->addWhere('msg_title', 'LIKE', $templateTitle . '%') // Adding '%' for right wildcard
		->setLimit(1)
		->execute()
		->single();

	\Civi::log()->info('template', ['template'=>$template]);

    if ($template) {

		$assigneeContact = \Civi\Api4\Contact::get(FALSE)
			->addSelect('email.email')
			->addJoin('Email AS email', 'LEFT')
			->addWhere('id', '=', $inductionActivity['Induction_Fields.Assign'])
			->setLimit(1)
			->execute()->single();
        $emailParams = [
            'contact_id' => $source_contact_id,
            'template_id' => $template['id'],
			'cc'=> $assigneeContact['email.email']
        ];
        $emailResult = civicrm_api3('Email', 'send', $emailParams);
        \Civi::log()->info('Email sent', ['result' => $emailResult]);
    } else {
        \Civi::log()->error('No email template found', ['template_title' => $templateTitle]);
    }
}
