<?php

function goonj_fetch_pu_activity_details( $activityId ) {
	return \Civi\Api4\Activity::get( false )
		->addSelect( 'source_contact_id', 'Office_Visit.Goonj_Processing_Center', 'Material_Contribution.Goonj_Office', 'activity_type_id:name' )
		->addWhere( 'id', '=', $activityId )
		->execute()
		->first();
}

// Function to determine Goonj Office ID based on activity type
function goonj_get_goonj_office_id( $activity ) {
	$activityTypeLabel = $activity['activity_type_id:name'];
	$officeMapping = array(
		'Material Contribution' => 'Material_Contribution.Goonj_Office',
		'Office visit' => 'Office_Visit.Goonj_Processing_Center',
	);

	return array_key_exists( $activityTypeLabel, $officeMapping ) ? $activity[ $officeMapping[ $activityTypeLabel ] ] ?? null : null;
}

// Function to fetch user's activities for today
function fetch_contact_pu_activities_for_today( $individual_id ) {

	$timezone = new \DateTimeZone( 'UTC' );
	$today = new \DateTime( 'now', $timezone );
	$startOfDay = $today->setTime( 0, 0 )->format( 'Y-m-d H:i:s' );
	$endOfDay = $today->setTime( 23, 59, 59 )->format( 'Y-m-d H:i:s' );

    return \Civi\Api4\Activity::get( false )
		->addSelect( 'Office_Visit.Goonj_Processing_Center', 'activity_type_id:name', 'Material_Contribution.Goonj_Office' )
		->addWhere( 'source_contact_id', '=', $individual_id )
		->addWhere( 'activity_type_id:name', 'IN', array( 'Office visit', 'Material Contribution' ) )
		->addWhere( 'created_date', '>=', $startOfDay )
		->addWhere( 'created_date', '<=', $endOfDay )
		->execute();
}

// Function to process activities and track office visits and material contributions
function goonj_process_pu_activities( $activities ) {
	$office_activities = array();

    foreach ( $activities as $activity ) {
		$officeId = $activity['Office_Visit.Goonj_Processing_Center'] ?: $activity['Material_Contribution.Goonj_Office'];
		$activityType = $activity['activity_type_id:name'];

		if ( $officeId && in_array( $activityType, array( 'Office visit', 'Material Contribution' ) ) ) {
			$office_activities[ $officeId ][] = $activityType;
		}
	}

    return $office_activities;
}

// Function to check if both activity types exist for any office
function goonj_check_if_both_pu_activity_types_exist( $office_activities ) {
	foreach ( $office_activities as $activityTypes ) {
		if ( count( array_unique( $activityTypes ) ) === 2 ) {
			return true;
		}
	}
	return false;
}

// Function to generate the redirect URL and button for the next pending activity
function goonj_generate_activity_button( $office_activities, $office_id, $individual_id ) {
	$activityTypes = array( 'Office visit', 'Material Contribution' );

	// Get the list of completed activities for the specified office
	// If no activities are found for this office, an empty array is returned
	$completedActivities = $office_activities[ $office_id ] ?? array();

	$pendingActivities = array_diff( $activityTypes, $completedActivities );

	if ( empty( $pendingActivities ) ) {
		return;
	}

	$activityMap = array(
		'Office visit' => array(
			'redirectPath' => '/processing-center/office-visit/details/',
			'buttonText' => __( 'Proceed to Office Visit', 'goonj-crm' ),
			'queryParam' => 'Office_Visit.Goonj_Processing_Center',
		),
		'Material Contribution' => array(
			'redirectPath' => '/processing-center/material-contribution/details/',
			'buttonText' => __( 'Proceed to Material Contribution', 'goonj-crm' ),
			'queryParam' => 'Material_Contribution.Goonj_Office',
		),
	);

	$nextActivity = reset( $pendingActivities );
	$details = $activityMap[ $nextActivity ];

	$redirectParams = array(
		'source_contact_id' => $individual_id,
		$details['queryParam'] => $office_id,
	);

	$redirectPathWithParams = $details['redirectPath'] . '#?' . http_build_query( $redirectParams );

	return goonj_generate_button_html( $redirectPathWithParams, $details['buttonText'] );
}
