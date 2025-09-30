<?php

require_once __DIR__ . '/engine/helpers.php';
require_once __DIR__ . '/engine/shortcodes.php';

add_action( 'wp_enqueue_scripts', 'goonj_enqueue_scripts' );
function goonj_enqueue_scripts() {
	wp_enqueue_style(
		'goonj-style',
		get_template_directory_uri() . '/style.css',
		array(),
		filemtime( get_template_directory() . '/style.css' )
	);
	wp_enqueue_script(
		'goonj-script',
		get_template_directory_uri() . '/main.js',
		array(),
		filemtime( get_template_directory() . '/main.js' )
	);
	wp_enqueue_script(
		'validation-script',
		get_template_directory_uri() . '/validation.js',
		array( 'jquery' ),
		filemtime( get_template_directory() . '/validation.js' ),
		true
	);
	if (is_page('team-5000')) {
		wp_enqueue_script(
			'team-5000',
			get_template_directory_uri() . '/goonj-team-5000.js',
			array( 'jquery' ),
			filemtime( get_template_directory() . '/goonj-team-5000.js' ),
			true
		);
	}
	if (!is_page('team-5000')) {
		wp_enqueue_script(
			'contribution',
			get_template_directory_uri() . '/goonj-contribution.js',
			array( 'jquery' ),
			filemtime( get_template_directory() . '/goonj-contribution.js' ),
			true
		);
	}
}

add_action( 'admin_enqueue_scripts', 'goonj_enqueue_admin_scripts' );
function goonj_enqueue_admin_scripts() {
	wp_enqueue_style(
		'goonj-admin-style',
		get_template_directory_uri() . '/admin-style.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);
	wp_enqueue_script(
		'goonj-admin-script',
		get_template_directory_uri() . '/admin-script.js',
		array( 'jquery' ),
		wp_get_theme()->get( 'Version' ),
		true
	);
}


add_action( 'after_setup_theme', 'goonj_theme_setup' );
function goonj_theme_setup() {
	add_editor_style( 'style.css' );
}



add_action( 'template_redirect', 'goonj_redirect_logged_in_user_to_civi_dashboard' );
function goonj_redirect_logged_in_user_to_civi_dashboard() {
	if ( is_user_logged_in() && is_front_page() ) {
		wp_redirect( home_url( '/wp-admin/admin.php?page=CiviCRM' ) );
		exit();
	}
}

add_action( 'wp_login_failed', 'goonj_custom_login_failed_redirect' );
function goonj_custom_login_failed_redirect( $username ) {
	// Change the URL to your desired page
	$redirect_url = home_url(); // Change '/login' to your custom login page slug

	// Add a query variable to indicate a login error
	$redirect_url = add_query_arg( 'login', 'failed', $redirect_url );

	wp_redirect( $redirect_url );
	exit;
}

add_filter( 'authenticate', 'goonj_check_empty_login_fields', 30, 3 );
function goonj_check_empty_login_fields( $user, $username, $password ) {
	if ( empty( $username ) || empty( $password ) ) {
		// Change the URL to your desired page
		$redirect_url = home_url(); // Change '/login' to your custom login page slug

		// Add a query variable to indicate empty fields
		$redirect_url = add_query_arg( 'login', 'failed', $redirect_url );

		wp_redirect( $redirect_url );
		exit;
	}
	return $user;
}

add_filter( 'login_form_top', 'goonj_login_form_validation_errors' );
function goonj_login_form_validation_errors( $string ) {
	if ( isset( $_REQUEST['login'] ) && $_REQUEST['login'] === 'failed' ) {
		return '<p class="error">Login failed: Invalid username or password.</p>';
	}

	if ( isset( $_REQUEST['password-reset'] ) && $_REQUEST['password-reset'] === 'success' ) {
		return '<p class="fw-600 fz-16 mb-6">Your password has been set successful</p>
		<p class="fw-400 fz-16 mt-0 mb-24">You can now login to your account using your new password</p>';
	}

	return $string;
}

add_action( 'login_form_rp', 'goonj_custom_reset_password_form' );
function goonj_custom_reset_password_form() {
	get_template_part( 'templates/password-reset' );
}

add_action( 'validate_password_reset', 'goonj_custom_password_reset_redirection', 10, 2 );
function goonj_custom_password_reset_redirection( $errors, $user ) {
	if ( $errors->has_errors() ) {
		return;
	}

	if ( isset( $_POST['pass1'] ) && ! empty( $_POST['pass1'] ) ) {
		reset_password( $user, $_POST['pass1'] );
		$rp_cookie = 'wp-resetpass-' . COOKIEHASH;
		list( $rp_path ) = explode( '?', wp_unslash( $_SERVER['REQUEST_URI'] ) );
		setcookie( $rp_cookie, ' ', time() - YEAR_IN_SECONDS, $rp_path, COOKIE_DOMAIN, is_ssl(), true );
		wp_redirect( add_query_arg( 'password-reset', 'success', home_url() ) );
		exit;
	}
}

add_action('wp_ajax_get_cities_by_state', 'get_cities_by_state');
add_action('wp_ajax_nopriv_get_cities_by_state', 'get_cities_by_state');
function get_cities_by_state() {
  if (empty($_POST['state_name'])) {
    wp_send_json_error(['message' => 'Missing state name']);
  }

  $state_name = trim($_POST['state_name']);

  $sqlState = "
    SELECT id
    FROM civicrm_state_province
    WHERE name = %1
    LIMIT 1
  ";

  $daoState = CRM_Core_DAO::executeQuery($sqlState, [1 => [$state_name, 'String']]);

  if (!$daoState->fetch()) {
    wp_send_json_error(['message' => 'State not found']);
  }

  $state_id = (int) $daoState->id;

  $results = [];

  $sqlCities = "
    SELECT id, name
    FROM civicrm_city
    WHERE state_province_id = %1
    ORDER BY name ASC
  ";

  $daoCities = CRM_Core_DAO::executeQuery($sqlCities, [1 => [$state_id, 'Integer']]);

  while ($daoCities->fetch()) {
    $results[] = [
      'id' => $daoCities->id,
      'name' => $daoCities->name,
    ];
  }

  wp_send_json_success(['cities' => $results]);
}


add_action( 'wp', 'goonj_handle_user_identification_form' );
function goonj_handle_user_identification_form() {
	if ( ! isset( $_POST['action'] ) || ( $_POST['action'] !== 'goonj-check-user' ) ) {
		return;
	}

	$purpose = $_POST['purpose'] ?? 'collection-camp-intent';
	$target_id = $_POST['target_id'] ?? '';
	$source = $_POST['source'] ?? '';

	// Retrieve the email and phone number from the POST data
	$email = $_POST['email'] ?? '';
	$phone = $_POST['phone'] ?? '';
	$state_id = $_POST['state_id'] ?? '';
	$city = $_POST['city'] ?? '';

	$is_purpose_requiring_email = ! in_array( $purpose, array( 'material-contribution', 'processing-center-office-visit', 'processing-center-material-contribution', 'dropping-center-contribution', 'institution-collection-camp', 'institution-dropping-center', 'event-material-contribution', 'goonj-activity-attendee-feedback', 'institute-goonj-activity-attendee-feedback', 'individual-collection-camp') );

	if ( empty( $phone ) || ( $is_purpose_requiring_email && empty( $email ) ) ) {
		return;
	}

	try {
		// Find the contact ID based on email and phone number
		$query = \Civi\Api4\Contact::get( false )
			->addSelect( 'id', 'contact_sub_type', 'display_name' )
			->addWhere( 'phone_primary.phone', '=', $phone )
			->addWhere( 'contact_type', '=', 'Individual' )
			->addWhere( 'is_deleted', '=', 0 );

		if ( ! empty( $email ) ) {
			$query->addWhere( 'email_primary.email', '=', $email );
		}

		// Execute the query with a limit of 1
		$contactResult = $query->setLimit( 1 )->execute();

		$found_contacts = $contactResult->first() ?? null;

		// If the user does not exist in the Goonj database
		// redirect to the volunteer registration form.
		$volunteer_registration_form_path = sprintf(
			'/volunteer-registration/form/#?email=%s&phone=%s&message=%s&Volunteer_fields.Which_activities_are_you_interested_in_=%s&source=%s',
			$email,
			$phone,
			'not-inducted-volunteer',
			'9', // Activity to create collection camp.
			$source
		);

		$individual_volunteer_registration_form_path = sprintf(
			'/individual-registration-with-volunteer-option/#?email=%s&phone=%s&Source_Tracking.Event=%s',
			$email,
			$phone,
			$target_id,
		);

		$dropping_center_volunteer_registration_form_path = sprintf(
			'/volunteer-registration/form/#?email=%s&phone=%s&message=%s',
			$email,
			$phone,
			'not-inducted-for-dropping-center'
		);
		$volunteer_registration_url = sprintf(
			'/volunteer-registration/form/#?email=%s&phone=%s&source=%s',
			$email,
			$phone,
			$source
		);

		if ( empty( $found_contacts ) ) {
			$organizationName = null;
			// If the purpose requires fetching the organization name,
			if ( in_array( $purpose, array( 'processing-center-material-contribution', 'processing-center-office-visit' ) ) ) {
				$organizationName = \Civi\Api4\Organization::get( false )
				->addSelect( 'display_name' )
				->addWhere( 'id', '=', $target_id )
				->execute()->single();
			}
			switch ( $purpose ) {
				// Contact does not exist and the purpose is to do material contribution.
				// Redirect to individual registration with option for volunteering.
				case 'material-contribution':
					$individual_volunteer_registration_form_path = sprintf(
						'/individual-registration-with-volunteer-option/#?email=%s&phone=%s&source=%s&Individual_fields.Creation_Flow=%s&state_province_id=%s&city=%s',
						$email,
						$phone,
						$source,
						'material-contribution',
						sanitize_text_field( $state_id ),
						sanitize_text_field( $city )
					);
					$redirect_url = $individual_volunteer_registration_form_path;
					break;

				// Contact does not exist and the purpose is to create a dropping center.
				// Redirect to individual registration with option for volunteering.
				case 'dropping-center-contribution':
					$individual_registration_form_path = sprintf(
						'/individual-signup-with-volunteering/#?email=%s&phone=%s&source=%s&Individual_fields.Creation_Flow=%s&state_province_id=%s&city=%s',
						$email,
						$phone,
						$source,
						'dropping-center-contribution',
						sanitize_text_field($state_id),
						sanitize_text_field($city)
					);
					$redirect_url = $individual_registration_form_path;
					break;

				case 'institution-collection-camp':
					$individual_registration_form_path = sprintf(
						'/individual-signup-with-volunteering/#?email=%s&phone=%s&source=%s&Individual_fields.Creation_Flow=%s&state_province_id=%s&city=%s',
						$email,
						$phone,
						$source,
						'institution-collection-camp',
						sanitize_text_field($state_id),
						sanitize_text_field($city)
						);
					$redirect_url = $individual_registration_form_path;
					break;

				case 'institution-dropping-center':
					$individual_registration_form_path = sprintf(
						'/individual-signup-with-volunteering/#?email=%s&phone=%s&source=%s&Individual_fields.Creation_Flow=%s&state_province_id=%s&city=%s',
						$email,
						$phone,
						$source,
						'institution-dropping-center',
						sanitize_text_field($state_id),
						sanitize_text_field($city)
						);
					$redirect_url = $individual_registration_form_path;
					break;

				// Contact does not exist and the purpose is to register an institute.
				// Redirect to individual registration.
				case 'institute-registration':
					$redirect_url = $individual_registration_form_path;
					break;

				// Contact does not exist and the purpose is processing center material contribution
				// redirect to individual registration
				case 'processing-center-material-contribution':
					$individual_registration_form_path = sprintf(
						'/processing-center/material-contribution/individual-registration/#?email=%s&phone=%s&source=%s&Individual_fields.Creation_Flow=%s&Individual_fields.Source_Processing_Center=%s',
						$email,
						$phone,
						$organizationName['display_name'],
						'office-visit-contribution',
						$target_id,
					);
					$redirect_url = $individual_registration_form_path;
					break;

				// Contact does not exist and the purpose is processing center office visit
				// redirect to individual registration
				case 'processing-center-office-visit':
					$individual_registration_form_path = sprintf(
						'/processing-center/office-visit/individual-registration/#?email=%s&phone=%s&source=%s&Individual_fields.Creation_Flow=%s&Individual_fields.Source_Processing_Center=%s',
						$email,
						$phone,
						$organizationName['display_name'],
						'office-visit',
						$target_id,
					);
					$redirect_url = $individual_registration_form_path;
					break;

				// Redirect to volunteer registration.
				case 'volunteer-registration':
					$redirect_url = $volunteer_registration_url;
					break;
					
				case 'dropping-center':
					$volunteer_registration_url = sprintf(
						'/volunteer-registration/form/#?email=%s&phone=%s&message=%s&Volunteer_fields.Which_activities_are_you_interested_in_=%s',
						$email,
						$phone,
						'dropping-center',
						'27'
					);
					$redirect_url = $volunteer_registration_url;
					break;
				case 'goonj-activities':
					$volunteer_registration_url = sprintf(
						'/volunteer-registration/form/#?email=%s&phone=%s&message=%s',
						$email,
						$phone,
						'goonj-activities',
					);
					$redirect_url = $volunteer_registration_url;
					break;
				case 'event-material-contribution':
					$individual_volunteer_registration_form_path = sprintf(
						'/individual-registration-with-volunteer-option/#?email=%s&phone=%s&source=%s&Individual_fields.Creation_Flow=%s',
						$email,
						$phone,
						$source,
						'event-material-contribution',
					);
					$redirect_url = $individual_volunteer_registration_form_path;
					break;
				case 'goonj-activity-attendee-feedback':
					$individual_volunteer_registration_form_path = sprintf(
						'/individual-registration-with-volunteer-option/#?email=%s&phone=%s&source=%s&Individual_fields.Creation_Flow=%s',
						$email,
						$phone,
						$source,
						'goonj-activity-attendee-feedback',
					);
					$redirect_url = $individual_volunteer_registration_form_path;
					break;
				case 'institute-goonj-activity-attendee-feedback':
					$individual_volunteer_registration_form_path = sprintf(
						'/individual-registration-with-volunteer-option/#?email=%s&phone=%s&source=%s&Individual_fields.Creation_Flow=%s',
						$email,
						$phone,
						$source,
						'institute-goonj-activity-attendee-feedback',
					);
					$redirect_url = $individual_volunteer_registration_form_path;
					break;
				case 'individual-collection-camp':
					$individual_volunteer_registration_form_path = sprintf(
						'/collection-camp/volunteer-with-intent/',
					);
					$redirect_url = $individual_volunteer_registration_form_path;
					break;
				// Contact does not exist and the purpose is not defined.
				// Redirect to volunteer registration with collection camp activity selected.
				default:
					$redirect_url = $volunteer_registration_form_path;
					break;
			}

			wp_redirect( $redirect_url );
			exit;
		}	

		// If we are here, then it means for sure that the contact exists.	
		if ($purpose === 'material-contribution') {
			$material_contribution_form_Path = sprintf(
				'/material-contribution/#?email=%s&phone=%s&Material_Contribution.Collection_Camp=%s&source_contact_id=%s',
				$email,
				$phone,
				$target_id,
				$found_contacts['id']
			);
			wp_redirect( $material_contribution_form_Path );
			exit;
		}
		if ($purpose === 'dropping-center-contribution') {
			$dropping_center_form_path = sprintf(
				'/dropping-center/material-contribution/#?email=%s&phone=%s&Material_Contribution.Dropping_Center=%s&source_contact_id=%s',
				$email,
				$phone,
				$target_id,
				$found_contacts['id']
			);
			wp_redirect( $dropping_center_form_path );
			exit;
		}

		if ($purpose === 'institution-collection-camp') {
			$institution_collection_camp_form_path = sprintf(
				'/institution-collection-camp/collection-camp-material-contribution/#?email=%s&phone=%s&Material_Contribution.Institution_Collection_Camp=%s&source_contact_id=%s',
				$email,
				$phone,
				$target_id,
				$found_contacts['id']
			);
			wp_redirect( $institution_collection_camp_form_path );
			exit;
		}

		if ($purpose === 'institution-dropping-center') {
			$institution_dropping_center_form_path = sprintf(
				'/institution-dropping-center/dropping-center-material-contribution/#?email=%s&phone=%s&Material_Contribution.Institution_Dropping_Center=%s&source_contact_id=%s',
				$email,
				$phone,
				$target_id,
				$found_contacts['id']
			);
			wp_redirect( $institution_dropping_center_form_path );
			exit;
		}

		if ( 'institute-registration' === $purpose ) {
			$institute_registration_form_path = sprintf(
				'/institute-registration/#?email=%s&phone=%s',
				$email,
				$phone,
			);
			wp_redirect( $institute_registration_form_path );
			exit;
		}

		if ( 'processing-center-material-contribution' === $purpose ) {
			$material_form_path = sprintf(
				'/processing-center/material-contribution/details/#?email=%s&phone=%s&Material_Contribution.Goonj_Office=%s&source_contact_id=%s',
				$email,
				$phone,
				$target_id,
				$found_contacts['id']
			);
			wp_redirect( $material_form_path );
			exit;
		}

		if ( 'processing-center-office-visit' === $purpose ) {
			$office_visit_form_path = sprintf(
				'/processing-center/office-visit/details/#?email=%s&phone=%s&Office_Visit.Goonj_Processing_Center=%s&source_contact_id=%s',
				$email,
				$phone,
				$target_id,
				$found_contacts['id']
			);
			wp_redirect( $office_visit_form_path );
			exit;
		}
		if ( 'event-material-contribution' === $purpose){
			$material_contribution_form_path = sprintf(
				'/events-material-contribution/#?email=%s&phone=%s&Material_Contribution.Event=%s&source_contact_id=%s',
				$email,
				$phone,
				$target_id,
				$found_contacts['id']
			);
			wp_redirect( $material_contribution_form_path );
			exit;
		}

		if ('goonj-activity-attendee-feedback' === $purpose){
			$goonjActivites = \Civi\Api4\EckEntity::get( 'Collection_Camp', FALSE )
			->addSelect('Goonj_Activities.Select_Attendee_feedback_form', 'title')
			->addWhere( 'title', '=', $source )
			->addWhere('subtype:name', '=', 'Goonj_Activities')
			->execute()->first();

			$redirectPath = sprintf(
				'%s#?title=%s&Goonj_Activity_Attendee_Feedbacks.Goonj_Individual_Activity=%s&Goonj_Activity_Attendee_Feedbacks.Filled_By=%s&Eck_Collection_Camp1=%s',
				$goonjActivites['Goonj_Activities.Select_Attendee_feedback_form'],
				$goonjActivites['title'],
				$goonjActivites['id'],
				$found_contacts['id'],
				$goonjActivites['id'],
			);
			wp_redirect( $redirectPath );
			exit;
		}

		if ('institute-goonj-activity-attendee-feedback' === $purpose){
			$goonjActivites = \Civi\Api4\EckEntity::get( 'Collection_Camp', FALSE )
			->addSelect('Institution_Goonj_Activities.Select_Attendee_feedback_form', 'title')
			->addWhere( 'title', '=', $source )
			->addWhere('subtype:name', '=', 'Institution_Goonj_Activities')
			->execute()->first();

			$redirectPath = sprintf(
				'%s#?title=%s&Goonj_Activity_Attendee_Feedbacks.Goonj_Institution_Activity=%s&Goonj_Activity_Attendee_Feedbacks.Filled_By=%s&Eck_Collection_Camp1=%s',
				$goonjActivites['Institution_Goonj_Activities.Select_Attendee_feedback_form'],
				$goonjActivites['title'],
				$goonjActivites['id'],
				$found_contacts['id'],
				$goonjActivites['id'],
			);
			wp_redirect( $redirectPath );
			exit;
		}

		$contactId = $found_contacts['id'];
		$contactSubType = $found_contacts['contact_sub_type'] ?? array();
		// Check if the contact is a volunteer
		$message = ($purpose === 'dropping-center') ? 'dropping-center-individual-user' : 'individual-user';
		if ( empty( $contactSubType ) || ! in_array( 'Volunteer', $contactSubType ) ) {
		if ( isset($purpose) && $purpose === 'individual-collection-camp' ) {
			wp_redirect( '/collection-camp/volunteer-with-intent/' );
			exit;
		} else {
			wp_redirect( '/volunteer-form/#?Individual1=' . $contactId . '&message=' . $message );
			exit;
		}
	}
		if ( goonj_is_volunteer_inducted( $found_contacts ) ) {
			if ( $purpose === 'individual-collection-camp' ) {
				$redirect_url = home_url( '/collection-camp/intent/' );
				wp_redirect( $redirect_url );
				exit;
			}
		}

		// If we are here, then it means Volunteer exists in our system.
		// Now we need to check if the volunteer is inducted or not.
		// If the volunteer is not inducted,
		// 1. Trigger an email for Induction
		// 2. Change volunteer status to "Waiting for Induction"
		if ( ! goonj_is_volunteer_inducted( $found_contacts ) ) {
			if ( $purpose === 'dropping-center' ) {
				$redirect_url = home_url( '/dropping-center/waiting-induction/' );
			} elseif ( $purpose === 'volunteer-registration' ) {
				$redirect_url = home_url( '/volunteer-registration/waiting-induction/' );
			} elseif ( $purpose === 'goonj-activities' ) {
				$redirect_url = home_url( '/goonj-activities/waiting-induction' );
			} elseif ( $purpose === 'individual-collection-camp' ) {
				$volunteer_registration_form_path = sprintf(
					'/collection-camp/intent/#?email=%s&phone=%s&message=%s',
					$email,
					$phone,
					$message='waiting-induction-collection-camp'
					);
					wp_redirect( $volunteer_registration_form_path );
				}
				wp_redirect( $redirect_url );	
				exit;	
			}
		// If we are here, then it means the user exists as an inducted volunteer.
		// Fetch the most recent collection camp activity based on the creation date
		$optionValues = \Civi\Api4\OptionValue::get( false )
		->addWhere( 'option_group_id:name', '=', 'eck_sub_types' )
		->addWhere( 'name', '=', 'Collection_Camp' )
		->addWhere( 'grouping', '=', 'Collection_Camp' )
		->execute()->single();

		$collectionCampSubtype = $optionValues['value'];

		$collectionCampResult = \Civi\Api4\EckEntity::get( 'Collection_Camp', false )
		->addSelect( '*', 'custom.*' )
		->addWhere( 'Collection_Camp_Core_Details.Contact_Id', '=', $found_contacts['id'] )
		->addWhere( 'subtype', '=', $collectionCampSubtype ) // Collection Camp subtype
		->addOrderBy( 'created_date', 'DESC' )
		->setLimit( 1 )
		->execute();

		$display_name = $found_contacts['display_name'];

		if ( $purpose === 'dropping-center' ) {
			wp_redirect( get_home_url() . '/dropping-center/intent/#?Collection_Camp_Core_Details.Contact_Id=' . $found_contacts['id'] . '&Dropping_Centre.Name=' . $display_name . '&Dropping_Centre.Contact_Number=' . $phone);
			exit;
		}

		if ( $purpose === 'goonj-activities' ) {
			wp_redirect( get_home_url() . '/goonj-activities/intent/#?Collection_Camp_Core_Details.Contact_Id=' . $found_contacts['id']. '&Goonj_Activities.Name=' . $display_name . '&Goonj_Activities.Contact_Number=' . $phone);
			exit;
		}

		if ( $purpose === 'volunteer-registration' ) {
			wp_redirect( get_home_url() . '/volunteer-registration/already-inducted/' );
			exit;
		}

		// Recent camp data
		$recentCamp = $collectionCampResult->first() ?? null;

		if ( ! empty( $recentCamp ) ) {
			// Save the recentCamp data to the session
			$_SESSION['recentCampData'] = $recentCamp;
			$_SESSION['contactId'] = $found_contacts['id'];
			$_SESSION['displayName'] = $display_name;
			$_SESSION['contactNumber'] = $phone;

			wp_redirect( get_home_url() . '/collection-camp/choose-from-past/#?Collection_Camp_Core_Details.Contact_Id=' . $found_contacts['id'] . '&message=past-collection-data' );
			exit;
		} else {
			$redirect_url = get_home_url() . '/collection-camp/intent/#?Collection_Camp_Core_Details.Contact_Id=' . $found_contacts['id'] . '&message=collection-camp-page&Collection_Camp_Intent_Details.Name=' . $display_name . '&Collection_Camp_Intent_Details.Contact_Number=' . $phone;
		}
		wp_redirect( $redirect_url );
		exit;
	} catch ( Exception $e ) {
		error_log( 'Error: ' . $e->getMessage() );
		echo 'An error occurred. Please try again later.';
	}
}

function goonj_is_volunteer_inducted( $volunteer ) {
	$optionValue = \Civi\Api4\OptionValue::get( false )
	->addWhere( 'option_group_id:name', '=', 'activity_type' )
	->addWhere( 'label', '=', 'Induction' )
	->execute()->single();

	$activityTypeId = $optionValue['value'];

	$activityResult = \Civi\Api4\Activity::get( false )
	->addSelect( 'id' )
	->addWhere( 'target_contact_id', '=', $volunteer['id'] )
	->addWhere( 'activity_type_id', '=', $activityTypeId )
	->addWhere( 'status_id:label', 'IN', array( 'Completed', 'Unknown' ) )
	->setLimit( 1 )
	->execute();

	$foundCompletedInductionActivities = $activityResult->first() ?? null;

	return ! empty( $foundCompletedInductionActivities );
}

function goonj_custom_message_placeholder() {
	return '<div id="custom-message" class="ml-24"></div>';
}

add_filter( 'query_vars', 'goonj_query_vars' );
function goonj_query_vars( $vars ) {
	$vars[] = 'target_id';
	$vars[] = 'state_province_id';
	$vars[] = 'city';
	return $vars;
}

add_action( 'template_redirect', 'goonj_redirect_after_individual_creation' );
function goonj_redirect_after_individual_creation() {
	if (
		! isset( $_GET['goonjAction'] ) ||
		$_GET['goonjAction'] !== 'individualCreated' ||
		! isset( $_GET['individualId'] )
	) {
		return;
	}

	$individual = \Civi\Api4\Contact::get( false )
		->addSelect( 'source', 'Individual_fields.Creation_Flow', 'Individual_fields.Source_Processing_Center' )
		->addWhere( 'id', '=', absint( $_GET['individualId'] ) )
		->setLimit( 1 )
		->execute()->single();

	$creationFlow = $individual['Individual_fields.Creation_Flow'];
	$source = $individual['source'];
	$sourceProcessingCenter = $individual['Individual_fields.Source_Processing_Center'];

	$redirectPath = '';

	switch ( $creationFlow ) {
		case 'material-contribution':
			if ( ! $source ) {
				\Civi::log()->warning( 'Source is missing for material contribution flow', array( 'individualId' => $_GET['individualId'] ) );
				return;
			}
			// If the individual was created while in the process of material contribution,
			// then we need to find out from WHERE was she trying to contribute.

			// First, we check if the source of Individual is Collection Camp (or Dropping Center).
			$collectionCamp = \Civi\Api4\EckEntity::get( 'Collection_Camp', FALSE )
				->addWhere( 'title', '=', $source )
				->addWhere('subtype:name', '=', 'Collection_Camp')
				->execute()->first();

			if ( ! empty( $collectionCamp['id'] ) ) {
				$redirectPath = sprintf(
					'/material-contribution/#?Material_Contribution.Collection_Camp=%s&source_contact_id=%s',
					$collectionCamp['id'],
					$individual['id']
				);
			}
			break;
			case 'dropping-center-contribution':
				if ( ! $source ) {
					\Civi::log()->warning('Source is missing for material contribution flow', ['individualId' => $_GET['individualId']]);
					return;
				}
				// If the individual was created during a material contribution process,
				// We need to determine from where they were attempting to contribute.
	
				// First, we check if the source of Individual is Dropping Center.
				$droppingCenter = \Civi\Api4\EckEntity::get( 'Collection_Camp', false )
					->addWhere( 'title', '=', $source )
					->addWhere('subtype:name', '=', 'Dropping_Center')
					->execute()->first();
	
				if ( ! empty( $droppingCenter['id'] ) ) {
					$redirectPath = sprintf(
						'/dropping-center/material-contribution/#?Material_Contribution.Dropping_Center=%s&source_contact_id=%s',
						$droppingCenter['id'],
						$individual['id']
					);
				}
				break;
			case 'institution-dropping-center':
				if ( ! $source ) {
					\Civi::log()->info('Source is missing for material contribution flow', ['individualId' => $_GET['individualId']]);
					return;
				}
				// If the individual was created during a material contribution process,
				// We need to determine from where they were attempting to contribute.
		
				// First, we check if the source of Individual is Institution Dropping Center.
				$institutionDroppingCenter = \Civi\Api4\EckEntity::get( 'Collection_Camp', false )
					->addWhere( 'title', '=', $source )
					->addWhere('subtype:name', '=', 'Institution_Dropping_Center')
					->execute()->first();
		
				if ( ! empty( $institutionDroppingCenter['id'] ) ) {
					$redirectPath = sprintf(
						'/institution-dropping-center/dropping-center-material-contribution/#?Material_Contribution.Institution_Dropping_Center=%s&source_contact_id=%s',
						$institutionDroppingCenter['id'],
						$individual['id']
					);
				}
				break;
			case 'institution-collection-camp':
				if ( ! $source ) {
					\Civi::log()->info('Source is missing for material contribution flow', ['individualId' => $_GET['individualId']]);
					return;
				}
				// If the individual was created during a material contribution process,
				// We need to determine from where they were attempting to contribute.
		
				// First, we check if the source of Individual is Institution Collection Camp.
				$institutionCollectionCamp = \Civi\Api4\EckEntity::get( 'Collection_Camp', false )
					->addWhere( 'title', '=', $source )
					->addWhere('subtype:name', '=', 'Institution_Collection_Camp')
					->execute()->first();
		
				if ( ! empty( $institutionCollectionCamp['id'] ) ) {
					$redirectPath = sprintf(
						'/institution-collection-camp/collection-camp-material-contribution/#?Material_Contribution.Institution_Collection_Camp=%s&source_contact_id=%s',
						$institutionCollectionCamp['id'],
						$individual['id']
					);
				}
				break;

		case 'office-visit':
			$sourceProcessingCenter = $individual['Individual_fields.Source_Processing_Center'];
			$redirectPath = sprintf(
				'/processing-center/office-visit/details/#?Office_Visit.Goonj_Processing_Center=%s&source_contact_id=%s',
				$sourceProcessingCenter,
				$individual['id']
			);
			break;
		case 'office-visit-contribution':
			$sourceProcessingCenter = $individual['Individual_fields.Source_Processing_Center'];
			$redirectPath = sprintf(
				'/processing-center/material-contribution/details/#?Material_Contribution.Goonj_Office=%s&source_contact_id=%s',
				$sourceProcessingCenter,
				$individual['id']
			);
			break;
		case 'event-material-contribution':
			$sourceProcessingCenter = $individual['Individual_fields.Source_Processing_Center'];
			$events = \Civi\Api4\Event::get(TRUE)
				->addWhere('title', '=', $source)
				->execute()->first();
			$redirectPath = sprintf(
				'/events-material-contribution/#?Material_Contribution.Event=%s&source_contact_id=%s',
				$events['id'],
				$individual['id']
			);
			break;
		case 'goonj-activity-attendee-feedback':
			$goonjActivites = \Civi\Api4\EckEntity::get( 'Collection_Camp', FALSE )
			->addSelect('Goonj_Activities.Select_Attendee_feedback_form', 'title')
			->addWhere( 'title', '=', $source )
			->addWhere('subtype:name', '=', 'Goonj_Activities')
			->execute()->first();
			$redirectPath = sprintf(
				'%s#?title=%s&Goonj_Activity_Attendee_Feedbacks.Goonj_Individual_Activity=%s&Goonj_Activity_Attendee_Feedbacks.Filled_By=%s&Eck_Collection_Camp1=%s',
				$goonjActivites['Goonj_Activities.Select_Attendee_feedback_form'],
				$goonjActivites['title'],
				$goonjActivites['id'],
				$individual['id'],
				$goonjActivites['id'],
			);
			break;
		
		case 'institute-goonj-activity-attendee-feedback':
			$goonjActivites = \Civi\Api4\EckEntity::get( 'Collection_Camp', FALSE )
			->addSelect('Institution_Goonj_Activities.Select_Attendee_feedback_form', 'title')
			->addWhere( 'title', '=', $source )
			->addWhere('subtype:name', '=', 'Institution_Goonj_Activities')
			->execute()->first();

			$redirectPath = sprintf(
				'%s#?title=%s&Goonj_Activity_Attendee_Feedbacks.Goonj_Institution_Activity=%s&Goonj_Activity_Attendee_Feedbacks.Filled_By=%s&Eck_Collection_Camp1=%s',
				$goonjActivites['Institution_Goonj_Activities.Select_Attendee_feedback_form'],
				$goonjActivites['title'],
				$goonjActivites['id'],
				$individual['id'],
				$goonjActivites['id'],
			);
			break;
	}

	if ( empty( $redirectPath ) ) {
		return;
	}

	wp_safe_redirect( $redirectPath );
}

add_filter( 'get_custom_logo', 'goonj_remove_logo_href', 10, 2 );
/**
 * Remove the href attribute from the custom logo HTML.
 */
function goonj_remove_logo_href( $html, $blog_id ) {
    $html = preg_replace( '/<a([^>]*?) href="[^"]*"/', '<a\1', $html );
    return $html;
}
/**
* Prevent user switching to different roles.
*
* @param array   $allcaps Array of key/value pairs where keys represent a capability name.
* @param array   $cap     Required primitive capabilities for the requested capability.
* @param array   $args    Arguments that accompany the requested capability check.
* @param WP_User $user    The user object.
* @return array Modified capabilities array.
*/
add_filter('user_has_cap', function($allcaps, $cap, $args, $user) {
    if (!defined('ROLE_SWITCH_RESTRICTIONS')) {
        return $allcaps;
    }
    $restrictions = unserialize(ROLE_SWITCH_RESTRICTIONS);

    foreach ((array) $user->roles as $role) {
        if (isset($restrictions[$role])) {

            if (isset($args[0]) && $args[0] === 'switch_to_user') {
                $target_user_id = $args[2];
                $target_user = get_userdata($target_user_id);

                if ($target_user) {
                    $target_roles = $target_user->roles;

                    foreach ($restrictions[$role] as $blocked_role) {
                        if (in_array($blocked_role, $target_roles)) {
                            $allcaps[$cap[0]] = false;
                        }
                    }
                }
            }
        }
    }

    return $allcaps;

}, 10, 4);
