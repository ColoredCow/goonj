<?php
/**
 * Theme file to Redesign Password Confirmation User Interface.
 */
?>
<link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/assets/password-reset.css">
<style>
#login {
	width: 500px !important;
}
</style>
<div class="w-500">
	<div class="w-150 m-auto mb-35">
		<a>
			<img class="w-100" src="<?php echo get_stylesheet_directory_uri(); ?>/images/password-confirmation.png" alt="Goonj-logo">
		</a>
	</div>
	<div class="text-center m-auto">
		<p class="fw-400 fz-20 leading-27 theme-black">Your password has been set successful</p>
		<p class="fw-400 fz-16 leading-21 theme-black mt-6">You can now login to your account using your new password</p>
	</div>
	<p class="mt-40">
		<a href="<?php echo esc_url(home_url()); ?>" class="button button-primary button-large fw-400 theme-black fz-16 leading-21 login-account">
			<?php esc_html_e( 'Login to your account' ); ?>
		</a>
	</p>

</div>
