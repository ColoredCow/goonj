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
function goonj_check_user_action() {
	ob_start();
	$message = '';
	if (isset($_GET['message'])) {
		if ($_GET['message'] === 'waiting-induction') {
			$message = '<p class="fw-600 fz-16 mb-6">Your induction is pending</p>
						<p class="fw-400 fz-16 mt-0 mb-24">Just one more step to go before you can start your collection camp. Please finish your induction to move forward.</p>
						<p class="fw-400 fz-16 mt-0 mb-24">
							Please reach out to <a href="mailto:mail@goonj.org">mail@goonj.org</a> in case there are any queries.
						</p>';
		}
	}

	// Pass the message to the template
	set_query_var('goonj_pending_induction_message', $message);
	get_template_part( 'templates/form', 'check-user' );
	return ob_get_clean();
}

add_action('wp', 'goonj_handle_user_identification_form');
function goonj_handle_user_identification_form() {
	if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'goonj-check-user' ) {
		return;
	}

	// Retrieve the email and phone number from the POST data
	$email = $_POST['email'] ?? '';
	$phone = $_POST['phone'] ?? '';

	if ( empty( $phone ) || empty( $email ) ) {
		return;
	}

	try {
		// Find the contact ID based on email and phone number
		$contactResult = civicrm_api3('Contact', 'get', [
			'sequential' => 1,
			'return' => ['id', 'contact_sub_type'],
			'email' => $email,
			'phone' => $phone,
			'is_deleted' => 0,
			'contact_type' => 'Individual',
		]);

		$foundContacts = $contactResult['values'];

		// If the user does not exist in the Goonj database
		// redirect to the volunteer registration form.
		$volunteer_registration_form_path = sprintf(
			'/volunteer-registration/#?email=%s&phone=%s&message=%s&Volunteer_fields.Which_activities_are_you_interested_in_=%s',
			$email,
			$phone,
			'not-inducted-volunteer',
			'9'
		);

		if ( empty( $foundContacts ) ) {
			// We are currently hardcoding the path of the volunteer registration page.
			// If this path changes, then this code needs to be updated.
			wp_redirect( $volunteer_registration_form_path );
			exit;
		}

		$contact = $foundContacts[0];
		// Check if the contact is a volunteer
		if ( empty( $contact['contact_sub_type'] ) || !in_array( 'Volunteer', $contact['contact_sub_type'] ) ) {
			wp_redirect( '/volunteer-form/#?Individual1=' . $contact['id'] . '&message=individual-user' );
			exit;
		}

		// If we are here, then it means Volunteer exists in our system.
		// Now we need to check if the volunteer is inducted or not.
		// If the volunteer is not inducted,
		//   1. Trigger an email for Induction
		//   2. Change volunteer status to "Waiting for Induction"
		if ( ! goonj_is_volunteer_inducted( $contact ) ) {
			$referer_url = wp_get_referer();
			$parsed_url = parse_url($referer_url);
			$query_params = [];

			// If there is a query string, parse it
			if (isset($parsed_url['query'])) {
				parse_str($parsed_url['query'], $query_params);
			}

			// Set the message parameter
			$query_params['message'] = 'waiting-induction';

			// Build and redirect to the new URL
			$redirect_url = $parsed_url['path'] . '?' . http_build_query($query_params);
			wp_redirect($redirect_url);
			exit;
		}

		// If we are here, then it means the user exists as an inducted volunteer.
		// Fetch the most recent collection camp activity based on the creation date
		$collectionCampResult = civicrm_api3('Activity', 'get', [
			'sequential' => 1,
			'return' => [
				'Collection_Camp_Intent.District',
				'Collection_Camp_Intent.State',
				'Collection_Camp_Intent.Start_Date',
				'Collection_Camp_Intent.End_Date',
				'Collection_Camp_Intent.Location_Area_of_camp',
				'created_date'
			],
			'contact_id' => $contact['id'],
			'activity_type_id' => ['IN' => [61]], // ID for "Collection Camp Intent"
			'status_id' => ['IN' => [10]], // Status ID for "Under Authorization"
			'order_by' => 'created_date DESC',
			'limit' => 1,
		]);

		// Recent camp data
		$recentCamp = end($collectionCampResult['values']);

		if ($recentCamp) {
			$redirect_url = get_home_url() . "/collection-camp-form/#?" . http_build_query([
				'source_contact_id' => $contact['id'],
				'Collection_Camp_Intent.District' => $recentCamp['custom_72'] ?? '',
				'Collection_Camp_Intent.State' => $recentCamp['custom_71'] ?? '',
				'Collection_Camp_Intent.Start_Date' => $recentCamp['custom_73'] ?? '',
				'Collection_Camp_Intent.End_Date' => $recentCamp['custom_74'] ?? '',
				'Collection_Camp_Intent.Location_Area_of_camp' => $recentCamp['custom_69'] ?? '',
				'message' => 'past-collection-data'
			]);
		} else {
			$redirect_url = get_home_url() . "/collection-camp-form/#?source_contact_id=" . $contact['id'];
		}
		wp_redirect($redirect_url);
		exit;
	} catch (CiviCRM_API3_Exception $e) {
		$error = $e->getMessage();
		echo "API error: $error";
	}
}

function goonj_is_volunteer_inducted( $volunteer ) {
	$activityResult = civicrm_api3('Activity', 'get', [
		'sequential' => 1,
		'return' => ['id'],
		'contact_id' => $volunteer['id'],
		'activity_type_id' => ['IN' => [57]], // hardcode ID of activity type "Induction"
		'status_id' => ['IN' => [2]], // hardcode ID of activity status "Completed"
	]);

	$foundCompletedInductionActivities = $activityResult['values'];

	return ! empty( $foundCompletedInductionActivities );
}

function goonj_custom_message_placeholder() {
	return '<div id="custom-message" class="ml-24"></div>';
}
add_shortcode('goonj_volunteer_message', 'goonj_custom_message_placeholder');

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
	return $vars;
}
