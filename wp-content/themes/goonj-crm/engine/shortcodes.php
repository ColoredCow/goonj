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

	$source_contact_id = isset($_GET['source_contact_id']) ? intval($_GET['source_contact_id']) : 0;
	$slot_date = isset($_GET['slot_date']) ? $_GET['slot_date'] : 0;
	$slot_time = isset($_GET['slot_time']) ? $_GET['slot_time'] : 0;

	// Fetch the induction activity for the source contact
	$activities = \Civi\Api4\Activity::get(FALSE)
		->addSelect('id', 'activity_date_time', 'status_id', 'status_id:name')
		->addWhere('source_contact_id', '=', $source_contact_id)
		->addWhere('activity_type_id:name', '=', 'Induction')
		->execute();
	\Civi::log()->info('activites', ['activities'=>$activities]);

	// If no activities found, exit
	if ($activities->count() === 0) {
		\Civi::log()->info('No activity found for contact', ['source_contact_id' => $source_contact_id]);
		return;
	}

	$inductionActivity = $activities->first();

	// If no induction activity is found, exit
	if ($inductionActivity === null) {
		\Civi::log()->info('No induction activity found for contact', ['source_contact_id' => $source_contact_id]);
		return;
	}

	$contactInductionStatus = $inductionActivity['status_id:name'];


	// If the induction status is 'Scheduled', return
	if (in_array($contactInductionStatus, ['Scheduled', 'Completed'])) {
		return;
	}

	
	// Combine slot date (d-m-Y) and slot time (H:i) to form the new activity date time
	$newActivityDateTime = DateTime::createFromFormat('d-m-Y H:i', $slot_date . ' ' . $slot_time);
	
	if ($newActivityDateTime === false) {
		\Civi::log()->error('Invalid date/time format', ['slot_date' => $slot_date, 'slot_time' => $slot_time]);
		return;
	}
	
	// Update the activity with the new date time and set status to "Scheduled" (status_id = 1)
	$result = \Civi\Api4\Activity::update(FALSE)
		->addValue('activity_date_time', $newActivityDateTime->format('Y-m-d H:i:s'))
		->addValue('status_id:name', 'Scheduled')
		->addWhere('id', '=', $inductionActivity['id']) // Update the fetched activity status to scheduled
		->execute();
	
	// Log the successful update
	\Civi::log()->info('Activity updated successfully', [
		'result'=>$result,
		'activity_id' => $inductionActivity['id'],
		'new_date_time' => $newActivityDateTime->format('Y-m-d H:i:s'),
		'new_status_id' => 1
	]);
	
}