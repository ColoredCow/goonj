<?php

add_action('wp_enqueue_scripts', 'goonj_enqueue_scripts');
function goonj_enqueue_scripts() {
    wp_enqueue_style(
        'goonj-style',
        get_template_directory_uri() . '/style.css',
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
            $message = '<p class="fw-600 fz-16 mb-6">Your induction process is pending.</p>
                        <p class="fw-400 fz-16 mt-0 mb-24">Please check your email for further instructions.</p>';
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
        // If the user does not exist in the Goonj database then
        // redirect to the volunteer registration form.
        $volunteer_registration_form_path = sprintf(
            '/volunteer-registration/#?email=%1$s&phone=%2$s',
            $email,
            $phone,
        );


        if ( empty( $foundContacts ) ) {
            // We are currently hardcoding the path of the volunteer registration page.
            // If this path changes, then this code needs to be updated.
            wp_redirect( $volunteer_registration_form_path );
            exit;
        }

        $contact = $foundContacts[0];

        // If we are here, then it means the contact of type "Individual" exists.
        // We need to now check if the contact sub_type is "Volunteer".
        // If the Individual is not a Volunteer, then again we redirect it to
        // volunteer registration form.
        if ( ! in_array( 'Volunteer', $contact['contact_sub_type'] ) ) {
            wp_redirect( $volunteer_registration_form_path );
            exit;
        }

        // If we are here, then it means Volunteer exists in our system.
        // Now we need to check if the volunteer is inducted or not.
        // If the volunteer is not inducted,
        //   1. Trigger an email for Induction 
        //   2. Change volunteer status to "Waiting for Induction"
        if ( ! goonj_is_volunteer_inducted( $contact ) ) {
            // Use CiviCRM email API to send the induction email.
            // Use CiviCRM contact API to update the contact status (custom data).
            // Redirect back to the same page with a message.
            wp_redirect( wp_get_referer() . '?message=waiting-induction' );
            exit;
        }

        // If we are here, then it means the user exists as an inducted volunteer.
        wp_redirect(get_home_url() . "/collection-camp-form/#?Collection_Camp_Intent.Contact_Id=" . $contact['id'] );
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
