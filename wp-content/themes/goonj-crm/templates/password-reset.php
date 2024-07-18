<?php
/**
 * Theme file to Redesign Password Reset User Interface
 */
?>
<link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/assets/password-reset.css">

<?php
list( $rp_path ) = explode( '?', wp_unslash( $_SERVER['REQUEST_URI'] ) );
$rp_cookie       = 'wp-resetpass-' . COOKIEHASH;

if ( isset( $_GET['key'] ) && isset( $_GET['login'] ) ) {
	$value = sprintf( '%s:%s', wp_unslash( $_GET['login'] ), wp_unslash( $_GET['key'] ) );
	setcookie( $rp_cookie, $value, 0, $rp_path, COOKIE_DOMAIN, is_ssl(), true );

	wp_safe_redirect( remove_query_arg( array( 'key', 'login' ) ) );
	exit;
}

if ( isset( $_COOKIE[ $rp_cookie ] ) && 0 < strpos( $_COOKIE[ $rp_cookie ], ':' ) ) {
	list( $rp_login, $rp_key ) = explode( ':', wp_unslash( $_COOKIE[ $rp_cookie ] ), 2 );

	$user = check_password_reset_key( $rp_key, $rp_login );

	if ( isset( $_POST['pass1'] ) && ! hash_equals( $rp_key, $_POST['rp_key'] ) ) {
		$user = false;
	}
} else {
	$user = false;
}

if ( ! $user || is_wp_error( $user ) ) {
	setcookie( $rp_cookie, ' ', time() - YEAR_IN_SECONDS, $rp_path, COOKIE_DOMAIN, is_ssl(), true );

	if ( $user && $user->get_error_code() === 'expired_key' ) {
		wp_redirect( site_url( 'wp-login.php?action=lostpassword&error=expiredkey' ) );
	} else {
		wp_redirect( site_url( 'wp-login.php?action=lostpassword&error=invalidkey' ) );
	}

	exit;
}

$errors = new WP_Error();

// Check if password is one or all empty spaces.
if ( ! empty( $_POST['pass1'] ) ) {
	$_POST['pass1'] = trim( $_POST['pass1'] );

	if ( empty( $_POST['pass1'] ) ) {
		$errors->add( 'password_reset_empty_space', __( 'The password cannot be a space or all spaces.' ) );
	}
}

// Check if password fields do not match.
if ( ! empty( $_POST['pass1'] ) && trim( $_POST['pass2'] ) !== $_POST['pass1'] ) {
	$errors->add( 'password_reset_mismatch', __( '<strong>Error:</strong> The passwords do not match.' ) );
}

/**
 * Fires before the password reset procedure is validated.
 *
 * @since 3.5.0
 *
 * @param WP_Error         $errors WP Error object.
 * @param WP_User|WP_Error $user   WP_User object if the login and reset key match. WP_Error object otherwise.
 */
do_action( 'validate_password_reset', $errors, $user );

if ( ( ! $errors->has_errors() ) && isset( $_POST['pass1'] ) && ! empty( $_POST['pass1'] ) ) {
	reset_password( $user, $_POST['pass1'] );
	setcookie( $rp_cookie, ' ', time() - YEAR_IN_SECONDS, $rp_path, COOKIE_DOMAIN, is_ssl(), true );
	login_header(
		__( 'Password Reset' ),
		wp_get_admin_notice(
			__( 'Your password has been reset.' ) . ' <a href="' . esc_url( wp_login_url() ) . '">' . __( 'Log in' ) . '</a>',
			array(
				'type'               => 'info',
				'additional_classes' => array( 'message', 'reset-pass' ),
			)
		)
	);
	login_footer();
	exit;
}

wp_enqueue_script( 'utils' );
wp_enqueue_script( 'user-profile' );

?>
<form name="resetpassform" id="resetpassform" action="<?php echo esc_url( network_site_url( 'wp-login.php?action=resetpass', 'login_post' ) ); ?>" method="post" autocomplete="off">
	<div class="w-150 m-auto mb-35">
		<a href="<?php echo home_url(); ?>">
			<img class="w-150" src="<?php echo get_stylesheet_directory_uri(); ?>/images/goonj-logo.png" alt="Goonj-logo">
		</a>
	</div>
	<div class="text-center w-388 m-auto">
		<p class="fw-400 fz-20 leading-27 theme-black text-capatalize">Set Account Password</p>
		<p class="fw-400 fz-16 leading-21 theme-black mt-6 mb-35">Please set a password for your account</p>
	</div>
	
	<input type="hidden" id="user_login" value="<?php echo esc_attr( $rp_login ); ?>" autocomplete="off" />

	<div class="user-pass1-wrap mb-35">
		<p>
			<label for="pass1"><?php _e( 'New Password' ); ?></label>
		</p>

		<div class="wp-pwd">
			<input type="password" name="pass1" id="pass1" class="input password-input" size="24" value="" autocomplete="new-password" spellcheck="false" data-reveal="1" data-pw="<?php echo esc_attr( wp_generate_password( 16 ) ); ?>" aria-describedby="pass-strength-result" />

			<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Hide password' ); ?>">
				<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
			</button>
		</div>
		<div class="pw-weak">
			<input type="checkbox" name="pw_weak" id="pw-weak" class="pw-checkbox" checked="true"/>
			<label class="fw-400 theme-black fz-16 leading-21" for="pw-weak"><?php _e( 'Confirm use of weak password' ); ?></label>
		</div>
	</div>

	<p class="user-pass2-wrap">
		<label for="pass2"><?php _e( 'Confirm new password' ); ?></label>
		<input type="password" name="pass2" id="pass2" class="input" size="20" value="" autocomplete="new-password" spellcheck="false" />
	</p>

	<?php

	/**
	 * Fires following the 'Strength indicator' meter in the user password reset form.
	 *
	 * @since 3.9.0
	 *
	 * @param WP_User $user User object of the user whose password is being reset.
	 */
	do_action( 'resetpass_form', $user );

	?>
	<input type="hidden" name="rp_key" value="<?php echo esc_attr( $rp_key ); ?>" />
	<p class="submit reset-pass-submit">
		<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large fw-400 theme-black fz-16 leading-21" value="<?php esc_attr_e( 'Set Account Password >' ); ?>" />
	</p>
</form>
