<?php
class CRM_Goonjcustom_CivirulesAction_CreateUserFromContact extends CRM_Civirules_Action {

	/**
	 * Method processAction to execute the action
	 *
	 * @param CRM_Civirules_TriggerData_TriggerData $triggerData
	 * @access public
	 */
	public function processAction( CRM_Civirules_TriggerData_TriggerData $triggerData ) {
		$contactId = $triggerData->getContactId();

		try {
			$contact = civicrm_api3( 'Contact', 'getsingle', array( 'id' => $contactId ) );

			if ( ! $contact || empty( $contact['email'] ) ) {
				return false;
			}
		} catch ( \CiviCRM_API3_Exception $e ) {
			return false;
		}

		$defaultPassword =  wp_generate_password();

		$userParams = array(
			'user_login'   => $contact['email'],
			'user_email'   => $contact['email'],
			'user_pass'    => $defaultPassword,
			'nickname'     => $contact['email'],
			'first_name'   => $contact['first_name'],
			'last_name'    => $contact['last_name'],
			'display_name' => $contact['display_name'],
			'role'         => 'lead_volunteer',
		);

		$existingUserID = username_exists( $contact['email'] );
		if ( $existingUserID != false ) {
			$userParams['ID'] = $existingUserID;
		}

		try {
			$user_id = wp_insert_user( $userParams );

			if ( empty( $user_id ) || $user_id instanceof \WP_Error ) {
				return false;
			}

			wp_new_user_notification( $user_id, null, 'user' );
		} catch ( \Exception $e ) {
			return false;
		}

		return true;
	}

	/**
	 * Method to return the url for additional form processing for action
	 * and return false if none is needed
	 *
	 * @param int $ruleActionId
	 * @return bool
	 * @access public
	 */
	public function getExtraDataInputUrl( $ruleActionId ) {
		return false;
	}
}

if ( function_exists( 'add_filter' ) ) {
	/**
	 * Filters the contents of the new user notification email sent to the new user.
	 *
	 * This function customizes the new user notification email to include a personalized message
	 *
	 * @param array   $wp_new_user_notification_email {
	 *     Used to build wp_mail().
	 *
	 *     @type string $to      The intended recipient - New user email address.
	 *     @type string $subject The subject of the email.
	 *     @type string $message The body of the email.
	 *     @type string $headers The headers of the email.
	 * }
	 * @param WP_User $user     User object for the new user.
	 * @param string  $blogname The site title.
	 *
	 * @return array Filtered email content and headers for new user notification email.
	 */
	add_filter( 'wp_new_user_notification_email', 'goonj_custom_new_user_notification_email', 10, 3 );
	function goonj_custom_new_user_notification_email( $wp_new_user_notification_email, $user, $blogname ) {
		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			return $wp_new_user_notification_email;
		}

		$reset_link = network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' );

		$message  = __( 'Your user account has been created on Goonj CRM. You can use the following username and password reset link to set the desired password.' ) . "<br><br>";
		$message .= sprintf( __( 'User email: %s' ), $user->user_email ) . "<br>";
		$message .= sprintf( __( 'Password reset link: <a href="%s" target="_blank">%s</a>' ), $reset_link, $reset_link ) . "<br>";
		$message .= sprintf( __( 'CRM login link: <a href="%s" target="_blank">%s</a>' ), wp_login_url(), wp_login_url() ) . "<br><br>";
		$message .= __( 'Once the password is reset, you can log in with your email address and set the password.' ) . "<br><br>";
		$message .= __( 'Regards,' ) . "<br>";
		$message .= __( 'Goonj Admin' ) . "<br>";

		$wp_new_user_notification_email['message'] = $message;
		$wp_new_user_notification_email['headers'] = array('Content-Type: text/html; charset=UTF-8');

		return $wp_new_user_notification_email;
	}

}
