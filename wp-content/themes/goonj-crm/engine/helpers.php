<?php

// Function to determine Goonj Office ID based on activity type
function goonj_get_goonj_office_id( $activity ) {
	$activityTypeName = $activity['activity_type_id:name'];
	$officeMapping = array(
		'Material Contribution' => 'Material_Contribution.Goonj_Office',
		'Office visit' => 'Office_Visit.Goonj_Processing_Center',
	);
	$officeCustomFieldName = $officeMapping[$activityTypeName];

	return $activity[$officeCustomFieldName];
	// return array_key_exists( $activityTypeName, $officeMapping ) ? $activity[ $officeMapping[ $activityTypeName ] ] ?? null : null;
}

// Function to generate the redirect URL and button for the next pending activity
function goonj_generate_activity_button( $activity, $office_id, $individual_id ) {
	// Define all activity types to check pending activities
	$activityTypes = array( 'Office visit', 'Material Contribution' );

	// Activity type that contact has already completed
	$completedActivityType = $activity['activity_type_id:name'];

	// Calculate the pending activities by comparing with $activityTypes
	$pendingActivities = array_diff( $activityTypes, array( $completedActivityType ) );

	// Define the mapping for each activity to the corresponding button and redirect info
	$activityMap = array(
		'Office visit' => array(
			'redirectPath' => '/processing-center/office-visit/details/',
			'buttonText' => __( 'Proceed to Processing Center Tour', 'goonj-crm' ),
			'queryParam' => 'Office_Visit.Goonj_Processing_Center',
			'additionalParams' => array(
				'Office_Visit.Entity_Type' => $activity['Material_Contribution.Entity_Type'], // Additional params from contribution activity
				'Office_Visit.Entity_Name' => $activity['Material_Contribution.Entity_Name']
			)
		),
		'Material Contribution' => array(
			'redirectPath' => '/processing-center/material-contribution/details/',
			'buttonText' => __( 'Proceed to Material Contribution', 'goonj-crm' ),
			'queryParam' => 'Material_Contribution.Goonj_Office',
		),
	);

	// Get the next pending activity
	$nextActivity = reset( $pendingActivities );
	$details = $activityMap[ $nextActivity ];

	// Prepare the URL with query params
	$redirectParams = array(
		'source_contact_id' => $individual_id,
		$details['queryParam'] => $office_id,
	);

	// Merge additional params if they exist
	if ( !empty( $details['additionalParams'] ) ) {
		$redirectParams += $details['additionalParams'];
	}

	$redirectPathWithParams = $details['redirectPath'] . '#?' . http_build_query( $redirectParams );

	// Generate and return the button HTML
	return goonj_generate_button_html( $redirectPathWithParams, $details['buttonText'] );
}
