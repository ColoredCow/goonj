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

    return $string;
}

add_action('login_form_rp', 'goonj_custom_reset_password_form');
function goonj_custom_reset_password_form() {
    get_template_part('templates/password-reset');
}

add_filter('login_message', 'goonj_custom_reset_password_messagee');
function goonj_custom_reset_password_messagee() {
    if (isset($_GET['action']) && $_GET['action'] === 'resetpass' && !isset($_GET['error'])) {
        get_template_part('templates/password-confirmation');
    }

}

add_action( 'login_head', 'custom_login_header_image' );
function custom_login_header_image() {
    ?>
    <style type="text/css">
        .login h1 a {
            background-image: url('<?php echo get_stylesheet_directory_uri(); ?>/images/goonj-logo.png');
            width: 150px;
            background-size: contain;
            padding-bottom: 100px;
        }
    </style>
    <?php
}
