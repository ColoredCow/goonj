<?php
/**
 * Theme file to Design User Identification form
 */

$purpose = $args['purpose'];
$target_id = get_query_var('target_id', '');
$source = get_query_var('source', '');
$state_id = get_query_var('state_province_id', '');
$city = get_query_var('city', '');

$is_purpose_requiring_email = !in_array($purpose, [
    'material-contribution',
    'processing-center-office-visit',
    'processing-center-material-contribution',
    'dropping-center-contribution',
    'institution-collection-camp',
    'institution-dropping-center',
    'event-material-contribution',
    'goonj-activity-attendee-feedback',
    'institute-goonj-activity-attendee-feedback',
    'individual-collection-camp'
]);

$is_individual_collection_camp = ($purpose === 'individual-collection-camp');
?>

<div class="text-center w-xl-520 m-auto">
    <form class="logged-out wp-block-loginout ml-30 mr-30 ml-md-0 mr-md-0" action="<?php echo home_url(); ?>" method="POST">
        <!-- Hidden input field with conditional action value -->
        <input type="hidden" name="action" value="goonj-check-user" />
        <input type="hidden" name="purpose" value="<?php echo esc_attr($purpose); ?>" />
        <input type="hidden" name="target_id" value="<?php echo esc_attr($target_id); ?>" />
        <input type="hidden" name="source" value="<?php echo esc_attr($source); ?>" />
        <input type="hidden" name="state_id" value="<?php echo esc_attr($state_id); ?>" />
        <input type="hidden" name="city" value="<?php echo esc_attr($city); ?>" />

        <?php if ($is_individual_collection_camp) : ?>
            <div class="d-grid">
                <label class="font-sans" for="first_name">First Name <span class="required-indicator">*</span></label>
                <input type="text" id="first_name" name="first_name" required value="<?php echo isset($_POST['first_name']) ? esc_attr(sanitize_text_field($_POST['first_name'])) : ''; ?>">
            </div>
            <br>
            <div class="d-grid">
                <label class="font-sans" for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo isset($_POST['last_name']) ? esc_attr(sanitize_text_field($_POST['last_name'])) : ''; ?>">
            </div>
            <br>
        <?php endif; ?>

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
        <p class="login-submit" data-test=submitButton>
            <input type="submit" class="button button-primary w-100p" value="Continue">
        </p>
    </form>
</div>