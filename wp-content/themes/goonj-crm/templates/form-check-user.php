<?php
/**
 * Theme file to Design User Identification form
 */

$purpose = $args['purpose'];
$target_id = get_query_var('target_id', '');
$source = get_query_var('source', '');

$is_purpose_requiring_email = !in_array($purpose, ['material-contribution', 'processing-center-office-visit', 'processing-center-material-contribution']);
?>

<div class="text-center w-xl-520 m-auto">
    <form class="logged-out wp-block-loginout ml-30 mr-30 ml-md-0 mr-md-0" action="<?php echo home_url(); ?>" method="POST">
        <!-- Hidden input field with conditional action value -->
        <input type="hidden" name="action" value="goonj-check-user" />
        <input type="hidden" name="purpose" value="<?php echo esc_attr($purpose); ?>" />
        <input type="hidden" name="target_id" value="<?php echo esc_attr($target_id); ?>" />
        <input type="hidden" name="source" value="<?php echo esc_attr($source); ?>" />
            <div class="d-grid">
                <label class="font-sans" for="email">Email <?php if ($is_purpose_requiring_email) : ?><span class="required-indicator">*</span><?php endif; ?></label>
                <input type="email" id="email" name="email" <?php echo $is_purpose_requiring_email ? 'required' : ''; ?> value="<?php echo isset($_POST['email']) ? esc_attr(sanitize_email($_POST['email'])) : ''; ?>">
            </div>
            <br>
            <div class="d-grid">
                <label class="font-sans" for="phone">Contact Number <span class="required-indicator">*</span></label>
                <input type="tel" id="phone" name="phone" required value="<?php echo isset($_POST['phone']) ? esc_attr(sanitize_text_field($_POST['phone'])) : ''; ?>">
            </div>
            <br>
            <p class="login-submit">
                <input type="submit" class="button button-primary w-100p" value="Continue">
            </p>
    </form>
</div>