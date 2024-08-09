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
    get_template_part('templates/form', 'check-user');
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
			'contact_id' => $contact['id'],
			'activity_type_id' => 61, // ID for "Collection Camp Intent"
			'status_id' => 10, // Status ID for "Under Authorization"
			'order_by' => 'created_date DESC',
			'limit' => 1,
		]);

		// Recent camp data
		$recentCamp = end($collectionCampResult['values']);

		if (!empty($recentCamp)) {
			// Save the recentCamp data to the session
			$_SESSION['recentCampData'] = $recentCamp;
			$_SESSION['contactId'] = $contact['id'];
			
			wp_redirect(get_home_url() . "/collection-camp-in-past/#?source_contact_id=" . $contact['id'] . '&message=past-collection-data' );
			exit;
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

add_action('after_setup_theme', 'goonj_setup_cron_job');
function goonj_setup_cron_job() {
    if (!wp_next_scheduled('goonj_cron_job')) {
        wp_schedule_event(time(), 'daily', 'goonj_cron_job');
    }
}

// Clear the cron job schedule on theme deactivation
add_action('switch_theme', 'goonj_clear_cron_job');
function goonj_clear_cron_job() {
    $timestamp = wp_next_scheduled('goonj_cron_job');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'goonj_cron_job');
    }
}

// Define the cron job function
add_action('goonj_cron_job', 'goonj_execute_cron_job');
function goonj_execute_cron_job() {
    try {
        if (function_exists('civicrm_initialize')) {
            civicrm_initialize();
        }

        // $currentDate = date('Y-m-d');
        $currentDate = '2024-08-30';

        $contacts = \Civi\Api4\Contact::get(TRUE)
            ->addSelect('id', 'display_name', 'Volunteer_Induction_Summary.Induction_status', 'Volunteer_Induction_Summary.Induction_assignee', 'Volunteer_Induction_Summary.Induction_date')
            ->addWhere('Volunteer_Induction_Summary.Induction_status', '=', 'Scheduled')
            ->addWhere('Volunteer_Induction_Summary.Induction_date', 'LIKE', '%' . $currentDate . '%')
            ->setLimit(25)
            ->execute();
        $assignees_data = [];

        // Collect display names of the assignees_data
        foreach ($contacts as $contact) {
            if (isset($contact['Volunteer_Induction_Summary.Induction_assignee']) && !empty($contact['Volunteer_Induction_Summary.Induction_assignee'])) {
                $assignees_data[] = [
                    'volunteer_id' => $contact['id'],
                    'volunteer_display_name' => $contact['display_name'],
                    'volunteer_induction_status' => $contact['Volunteer_Induction_Summary.Induction_status'],
                    'assignee_display_name' => $contact['Volunteer_Induction_Summary.Induction_assignee'],
                    'volunteer_induction_date' => $contact['Volunteer_Induction_Summary.Induction_date'],
                ];
            }
        }

        // Fetch the IDs for each assignee and update the assignees_data array
        $assigneeIds = \Civi\Api4\Contact::get(TRUE)
            ->addSelect('id', 'display_name')
            ->addWhere('display_name', 'IN', array_column($assignees_data, 'assignee_display_name'))
            ->execute();

        foreach ($assignees_data as &$assignee) {
            foreach ($assigneeIds as $idInfo) {
                if ($assignee['assignee_display_name'] == $idInfo['display_name']) {
                    $assignee['assignee_id'] = $idInfo['id'];
                    break;
                }
            }
        }

        // Extract the IDs from the assignees_data to fetch emails
        $ids = array_column($assignees_data, 'assignee_id');

        // Fetch the emails associated with the assignee IDs
        $assigneeEmails = \Civi\Api4\Email::get(TRUE)
            ->addSelect('contact_id', 'email')
            ->addWhere('contact_id', 'IN', $ids)
            ->execute();

        // Append emails to the corresponding assignee in the assignees_data array
        foreach ($assignees_data as &$assignee) {
            foreach ($assigneeEmails as $emailInfo) {
                if ($assignee['assignee_id'] == $emailInfo['contact_id']) {
                    $assignee['assignee_email'] = $emailInfo['email'];
                    break;
                }
            }
        }

		// foreach ($assignees_data as $assignee) {
        //     if (isset($assignee['assignee_email'])) {
        //         $params = [
        //             'contact_id' => $assignee['assignee_id'],
        //             'subject' => "Induction Scheduled for Today",
        //             'text' => "Dear " . $assignee['volunteer_display_name'] . ",\n\nThis is to inform you that there is an induction scheduled for today, " . $assignee['Volunteer_Induction_Summary.Induction_date']  . ".\n\nBest regards,\nYour Organization",
        //             'from_name' => "Goonj",
        //             'from_email' => "nishant.kumar@coloredcow.in",
        //             'template_id' => 75, // Assuming you have a template ID
        //             'create_activity' => TRUE,
        //             'activity_details' => 'text', // You can change this to 'html' or 'html,text' depending on the message content
        //         ];

		// 		try {
        //             civicrm_api3('Email', 'send', $params);
        //         } catch (CiviCRM_API3_Exception $e) {
        //             error_log('Goonj Cron Job: Error sending email to ' . $assignee['assignee_display_name'] . ' - ' . $e->getMessage());
        //         }
        //     }
        // }
		foreach ($assignees_data as $assignee) {
			if (isset($assignee['assignee_email'])) {
				$params = [
					'contact_id' => $assignee['assignee_id'],
					'template_id' => 76, // Ensure this template has the placeholders
					'from_name' => "Goonj",
					'from_email' => "nishant.kumar@coloredcow.in",
					'merge_vars' => [
						[
							'name' => 'volunteer_display_name',
							'value' => $assignee['volunteer_display_name'],
						],
						[
							'name' => 'volunteer_induction_date',
							'value' => $assignee['volunteer_induction_date'],
						],
					],
					'create_activity' => TRUE,
					'activity_details' => 'text', // Or 'html' depending on your needs
				];
		
				try {
					civicrm_api3('Email', 'send', $params);
				} catch (CiviCRM_API3_Exception $e) {
					error_log('Goonj Cron Job: Error sending email to ' . $assignee['assignee_display_name'] . ' - ' . $e->getMessage());
				}
			}
		}
		
        echo "Assignees_dataEmails:\n";
        print_r($assignees_data);

        error_log('Goonj Cron Job: Job completed successfully');
    } catch (CiviCRM_API3_Exception $e) {
        error_log('Goonj Cron Job: API error - ' . $e->getMessage());
    }
}


// Add a new hook in WordPress to listen for the CiviCRM job trigger
add_action('civi_goonj_cron_job', 'goonj_execute_cron_job');

// Define a simple PHP script to trigger the WordPress cron job
function civi_trigger_goonj_cron_job() {
    // Trigger the custom WordPress cron job
    do_action('civi_goonj_cron_job');
}

// Register this function as a CiviCRM scheduled job
add_action('init', function() {
    if (isset($_GET['civi_cron']) && $_GET['civi_cron'] === 'goonj_cron_job') {
        civi_trigger_goonj_cron_job();
        exit('Goonj Cron Job Triggered');
    }
});