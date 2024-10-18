<?php

add_shortcode( 'goonj_check_user_form', 'goonj_check_user_action' );
add_shortcode( 'goonj_volunteer_message', 'goonj_custom_message_placeholder' );
add_shortcode( 'goonj_contribution_volunteer_signup_button', 'goonj_contribution_volunteer_signup_button' );
add_shortcode( 'goonj_pu_activity_button', 'goonj_pu_activity_button' );
add_shortcode( 'goonj_collection_landing_page', 'goonj_collection_camp_landing_page' );
add_shortcode( 'goonj_collection_camp_past', 'goonj_collection_camp_past_data' );

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
