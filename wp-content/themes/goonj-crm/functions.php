<?php

add_action('wp_enqueue_scripts', 'goonj_enqueue_scripts');
function goonj_enqueue_scripts() {
	wp_enqueue_style(
		'goonj-style',
		get_template_directory_uri() . '/style.css',
		array(),
		wp_get_theme()->get('Version')
	);
	wp_enqueue_script(
		'goonj-script',
		get_template_directory_uri() . '/main.js',
		array(),
		wp_get_theme()->get('Version')
	);
	wp_enqueue_script(
		'validation-script',
		get_template_directory_uri() . '/validation.js',
		array('jquery'),
		wp_get_theme()->get('Version'),
		true
	);
}

add_action('admin_enqueue_scripts', 'goonj_enqueue_admin_scripts');
function goonj_enqueue_admin_scripts() {
	wp_enqueue_style(
		'goonj-admin-style',
		get_template_directory_uri() . '/admin-style.css',
		array(),
		wp_get_theme()->get('Version')
	);
}


add_action('after_setup_theme', 'goonj_theme_setup');
function goonj_theme_setup() {
	add_editor_style('style.css');
}



add_action('template_redirect', 'goonj_redirect_logged_in_user_to_civi_dashboard');
function goonj_redirect_logged_in_user_to_civi_dashboard() {
	if (is_user_logged_in() && is_front_page()) {
		wp_redirect(home_url('/wp-admin/admin.php?page=CiviCRM'));
		exit();
	}
}

add_action( 'wp_login_failed', 'goonj_custom_login_failed_redirect' );
function goonj_custom_login_failed_redirect( $username ) {
	// Change the URL to your desired page
	$redirect_url = home_url( ); // Change '/login' to your custom login page slug

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
	if ( isset( $_REQUEST['login'] ) && $_REQUEST['login'] === 'failed'  ) {
		return '<p class="error">Login failed: Invalid username or password.</p>';
	}

	if ( isset( $_REQUEST['password-reset'] ) && $_REQUEST['password-reset'] === 'success'  ) {
		return
		'<p class="fw-600 fz-16 mb-6">Your password has been set successful</p>
		<p class="fw-400 fz-16 mt-0 mb-24">You can now login to your account using your new password</p>';
	}

	return $string;
}

add_action('login_form_rp', 'goonj_custom_reset_password_form');
function goonj_custom_reset_password_form() {
	get_template_part('templates/password-reset');
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

add_shortcode( 'goonj_check_user_form', 'goonj_check_user_action' );

function goonj_check_user_action($atts)
{
	get_template_part('templates/form', 'check-user', [ 'purpose' => $atts['purpose'] ]);
	return ob_get_clean();

}


add_action('wp', 'goonj_handle_user_identification_form');
function goonj_handle_user_identification_form() {
	if ( ! isset( $_POST['action'] ) || ( $_POST['action'] !== 'goonj-check-user' ) ) {
		return;
	}

	$purpose = $_POST['purpose'] ?? 'collection-camp-intent';
	$target_id = $_POST['target_id'] ?? '';

	// Retrieve the email and phone number from the POST data
	$email = $_POST['email'] ?? '';
	$phone = $_POST['phone'] ?? '';

	$is_material_contribution = $purpose !== 'material-contribution';

	if ( empty( $phone ) || ( $is_material_contribution && empty( $email ) ) ) {
		return;
	}

	try {
		// Find the contact ID based on email and phone number
		$query = \Civi\Api4\Contact::get(FALSE)
			->addSelect('id', 'contact_sub_type', 'display_name')
			->addWhere('phone_primary.phone', '=', $phone)
			->addWhere('contact_type', '=', 'Individual')
			->addWhere('is_deleted', '=', 0);

		if ( !empty( $email ) ) {
			$query->addWhere('email_primary.email', '=', $email);
		}

		// Execute the query with a limit of 1
		$contactResult = $query->setLimit(1)->execute();

		$found_contacts = $contactResult->first() ?? null;

		// If the user does not exist in the Goonj database
		// redirect to the volunteer registration form.
		$volunteer_registration_form_path = sprintf(
			'/volunteer-registration/#?email=%s&phone=%s&message=%s&Volunteer_fields.Which_activities_are_you_interested_in_=%s',
			$email,
			$phone,
			'not-inducted-volunteer',
			'9', // Activity to create collection camp.
		);

		$individual_volunteer_registration_form_path = sprintf(
			'/individual-registration-with-volunteer-option/#?email=%s&phone=%s&Source_Tracking.Event=%s',
			$email,
			$phone,
			$target_id,
		);

		$dropping_center_volunteer_registration_form_path = sprintf(
			'/volunteer-registration/#?email=%s&phone=%s&message=%s',
			$email,
			$phone,
			'not-inducted-for-dropping-center'
		);

		if ( empty( $found_contacts ) ) {
			switch ( $purpose ) {
				// Contact does not exist and the purpose is to do material contribution.
				// Redirect to individual registration with option for volunteering.
				case 'material-contribution':
					$redirect_url = $individual_volunteer_registration_form_path;
					break;

				// Contact does not exist and the purpose is to create a dropping center.
				// Redirect to volunteer registration.
				case 'dropping-center':
					$redirect_url = $dropping_center_volunteer_registration_form_path;
					break;

				// Contact does not exist and the purpose is to register an institute.
				// Redirect to individual registration.
				case 'institute-registration':
					$redirect_url = $individual_registration_form_path;
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

		if ( 'material-contribution' === $purpose ) {
			$material_contribution_form_path = sprintf(
				'/material-contribution/#?email=%s&phone=%s&Source_Tracking.Event=%s',
				$email,
				$phone,
				$target_id,
			);
			wp_redirect( $material_contribution_form_path );
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

		$contactId = $found_contacts['id'];
		$contactSubType = $found_contacts['contact_sub_type'] ?? []; 
		// Check if the contact is a volunteer
		if ( empty( $contactSubType ) || !in_array( 'Volunteer', $contactSubType ) ) {
			wp_redirect('/volunteer-form/#?Individual1=' . $contactId . '&message=individual-user');
			exit;
		}

		// If we are here, then it means Volunteer exists in our system.
		// Now we need to check if the volunteer is inducted or not.
		// If the volunteer is not inducted,
		//   1. Trigger an email for Induction
		//   2. Change volunteer status to "Waiting for Induction"
		if ( ! goonj_is_volunteer_inducted( $found_contacts ) ) {
			$redirect_url = ($purpose === 'dropping-center')
			? home_url('/dropping-centre-waiting-induction/')
			: home_url('/collection-camp/waiting-induction/');
	
			wp_redirect($redirect_url);
			exit;
		}

		// If we are here, then it means the user exists as an inducted volunteer.
		// Fetch the most recent collection camp activity based on the creation date
		$collectionCampResult = \Civi\Api4\EckEntity::get('Collection_Camp', FALSE)
		->addSelect('*', 'custom.*')
		->addWhere('Collection_Camp_Core_Details.Contact_Id', '=', $found_contacts['id'])
		->addWhere('subtype', '=', 4) // Collection Camp subtype
		->addOrderBy('created_date', 'DESC')
		->setLimit(1)
		->execute();

		if ($purpose === 'dropping-center') {
			wp_redirect(get_home_url() . "/dropping-center/#?Collection_Camp_Core_Details.Contact_Id=" . $found_contacts['id']);
			exit;
		}

		// Recent camp data
		$recentCamp = $collectionCampResult->first() ?? null;
		$display_name = $found_contacts['display_name'];

		if (!empty($recentCamp)) {
			// Save the recentCamp data to the session
			$_SESSION['recentCampData'] = $recentCamp;
			$_SESSION['contactId'] = $found_contacts['id'];
			$_SESSION['displayName'] = $display_name;
			$_SESSION['contactNumber'] = $phone;

			wp_redirect(get_home_url() . "/collection-camp/choose-from-past/#?Collection_Camp_Core_Details.Contact_Id=" . $found_contacts['id'] . '&message=past-collection-data' );
			exit;
		} else {
			$redirect_url = get_home_url() . "/collection-camp/intent/#?Collection_Camp_Core_Details.Contact_Id=" . $found_contacts['id'] . '&message=collection-camp-page&Collection_Camp_Intent_Details.Name=' . $display_name . '&Collection_Camp_Intent_Details.Contact_Number='. $phone;
		}
		wp_redirect($redirect_url);
		exit;
	} catch (Exception $e) {
		error_log("Error: " . $e->getMessage());
		echo "An error occurred. Please try again later.";
	}
}

function goonj_is_volunteer_inducted( $volunteer ) {
	$activityResult = \Civi\Api4\Activity::get(FALSE)
	->addSelect('id')
	->addWhere('target_contact_id', '=', $volunteer['id'])
	->addWhere('activity_type_id', '=', 57) // hardcode ID of activity type "Induction"
	->addWhere('status_id', '=', 2) // hardcode ID of activity status "Completed"
	->setLimit(1)
	->execute();

	$foundCompletedInductionActivities= $activityResult->first() ?? null;

	return ! empty( $foundCompletedInductionActivities );
}

function goonj_custom_message_placeholder() {
	return '<div id="custom-message" class="ml-24"></div>';
}
add_shortcode('goonj_volunteer_message', 'goonj_custom_message_placeholder');

function goonj_collection_camp_landing_page() {
	ob_start();
	get_template_part('templates/collection-landing-page');
	return ob_get_clean();
}
add_shortcode( 'goonj_collection_landing_page', 'goonj_collection_camp_landing_page' );

add_action( 'init', 'goonj_custom_rewrite_rules' );
function goonj_custom_rewrite_rules() {
	add_rewrite_rule(
		'^actions/collection-camp/([0-9]+)/?',
		'index.php?pagename=actions&target=collection-camp&id=$matches[1]',
		'top'
	);

	add_rewrite_rule(
		'^actions/dropping-center/([0-9]+)/?',
		'index.php?pagename=actions&target=dropping-center&id=$matches[1]',
		'top'
	);

	add_rewrite_rule(
		'^actions/processing-center/([0-9]+)/?',
		'index.php?pagename=actions&target=processing-center&id=$matches[1]',
		'top'
	);
}

add_filter( 'query_vars', 'goonj_query_vars' );
function goonj_query_vars( $vars ) {
	$vars[] = 'target';
	$vars[] = 'id';
	$vars[] = 'target_id';
	return $vars;
}

add_action( 'template_redirect', 'goonj_check_action_target_exists' );
function goonj_check_action_target_exists() {
	global $wp_query;

	if (
		! is_page( 'actions' ) ||
		! get_query_var( 'target' ) ||
		! get_query_var( 'id' )
	) {
		return;
	}

	$target = get_query_var( 'target' );
	$id = intval( get_query_var( 'id' ) );

	// Load CiviCRM.
	if ( function_exists( 'civicrm_initialize' ) ) {
		civicrm_initialize();
	}

	$is_404 = false;

	$entity_fields = array(
		'id',
		'title',
		'Collection_Camp_Intent_Details.Start_Date',
		'Collection_Camp_Intent_Details.End_Date',
	);

	switch ( $target ) {
		case 'collection-camp':
			$result = \Civi\Api4\EckEntity::get('Collection_Camp', TRUE)
				->selectRowCount()
				->addSelect( ...$entity_fields )
				->addWhere( 'id', '=', $id )
				->setLimit( 1 )
				->execute();

			if ( $result->count() === 0 ) {
				$is_404 = true;
			} else {
				$wp_query->set( 'action_target', $result->first() );
			}
			break;
		case 'dropping-center':
			// TBA.
			break;
		case 'processing-center':
			// TBA.
			break;
		default:
			$is_404 = true;
	}

	if ( $is_404 ) {
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		include get_query_template( '404' );
		exit;
	}
}

add_shortcode( 'goonj_collection_camp_past', 'goonj_collection_camp_past_data' );
function goonj_collection_camp_past_data() {
	ob_start();
	get_template_part( 'templates/collection-camp-data' );
	return ob_get_clean();
}
