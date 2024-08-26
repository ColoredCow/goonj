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
    ob_start();
    $message = '';
    if (isset($_GET['message'])) {
        if ($_GET['message'] === 'waiting-induction') {
            $message = '
				<p class="fw-600 fz-20 mb-6">Your induction is pending</p>
				<p class="fw-400 fz-16 mt-0 mb-24">
					We noticed that you\'ve already submitted your volunteer registration form. Just one more step to go before you can start your collection camp. Please finish your induction to move forward.
				</p>
				<div class="contact-info">
				<div class="contact-item">
					<img src="' . get_template_directory_uri() . '/images/email-icon.png" alt="Email Icon" class="icon">
					<a href="mailto:mail@goonj.org" class="contact-link">mail@goonj.org</a>
				</div>
				<div class="contact-item">
					<img src="' . get_template_directory_uri() . '/images/phone-icon.png" alt="Phone Icon" class="icon">
					<a href="tel:01141401216" class="contact-link">011-41401216</a>
				</div>
			</div>';
        }
    }

    // Pass the message to the template
    set_query_var('goonj_pending_induction_message', $message);
    get_template_part('templates/form', 'check-user', [ 'purpose' => $atts['purpose'] ]);
    return ob_get_clean();

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

        $foundContacts = $contactResult->first() ?? null;

        // If the user does not exist in the Goonj database
        // redirect to the volunteer registration form.
        $volunteer_registration_form_path = sprintf(
            '/volunteer-registration/#?email=%s&phone=%s&message=%s&Volunteer_fields.Which_activities_are_you_interested_in_=%s',
            $email,
            $phone,
            'not-inducted-volunteer',
            '9'
        );

        $individual_volunteer_registration_form_path = sprintf(
            '/individual-registration-with-volunteer-option/#?email=%s&phone=%s&Source_Tracking.Event=%s',
            $email,
            $phone,
            $target_id,
        );

        $material_contribution_form_path = sprintf(
            '/material-contribution/#?email=%s&phone=%s&Source_Tracking.Event=%s',
            $email,
            $phone,
            $target_id,
        );

        if (empty($foundContacts)) {
            if ($purpose == 'material-contribution') {
                wp_redirect($individual_volunteer_registration_form_path);
                exit;
            }
            // We are currently hardcoding the path of the volunteer registration page.
            // If this path changes, then this code needs to be updated.
            wp_redirect($volunteer_registration_form_path);
            exit;
        }

		if ( $foundContacts && $purpose == 'material-contribution' ) {
			wp_redirect($material_contribution_form_path);
			exit;
		}

		$contactId = $foundContacts['id'];
		$contactSubType = $foundContacts['contact_sub_type'] ?? []; 
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
		if ( ! goonj_is_volunteer_inducted( $foundContacts ) ) {
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
		$collectionCampResult = \Civi\Api4\EckEntity::get('Collection_Camp', FALSE)
		->addSelect('*', 'custom.*')
		->addWhere('Status.Contact_Id', '=', $foundContacts['id'])
		->addWhere('subtype', '=', 4) // Collection Camp subtype
		->addOrderBy('created_date', 'DESC')
		->setLimit(1)
		->execute();


		// Recent camp data
		$recentCamp = $collectionCampResult->first() ?? null;
		$display_name = $foundContacts['display_name'];

		if (!empty($recentCamp)) {
			// Save the recentCamp data to the session
			$_SESSION['recentCampData'] = $recentCamp;
			$_SESSION['contactId'] = $foundContacts['id'];
			$_SESSION['displayName'] = $display_name;
			$_SESSION['contactNumber'] = $phone;

			wp_redirect(get_home_url() . "/collection-camp-in-past/#?Status.Contact_Id=" . $foundContacts['id'] . '&message=past-collection-data' );
			exit;
		} else {
			$redirect_url = get_home_url() . "/collection-camp-intent/#?Status.Contact_Id=" . $foundContacts['id'] . '&message=collection-camp-page&Collection_Camp_Intent_Details.Name=' . $display_name . '&Collection_Camp_Intent_Details.Contact_Number='. $phone;
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

	$event_fields = array(
		'id',
		'title',
		'summary',
		'description',
		'start_date',
		'end_date',
	);

	switch ( $target ) {
		case 'collection-camp':
			$result = \Civi\Api4\Event::get( false )
				->selectRowCount()
				->addSelect( ...$event_fields )
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
