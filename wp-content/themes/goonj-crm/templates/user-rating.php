<?php

/**
 * @file
 * Template: user-rating.php.
 */

// Description: Content for user rating dropdown, used by [goonj_user_rating] shortcode.
?>

<div id="user-rating-container">
    <h2>Provide Your Rating</h2>
    <label for="user_rating">Gave us rating:</label>
    <select id="user_rating" name="user_rating">
        <option value="">Select a rating</option>
        <?php for ($i = 1; $i <= 10; $i++) : ?>
            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
        <?php endfor; ?>
    </select>
    <div id="rating-message"></div>
</div>

<script>
document.getElementById('user_rating').addEventListener('change', function() {
    var rating = this.value;
    if (!rating) return; // Exit if no rating is selected

    // Prepare data for AJAX request
    var data = {
        action: 'update_user_rating',
        rating: rating,
        entity_id: 7323 // Hardcoded ID as per requirement
    };

    // AJAX request to call the API
    jQuery.ajax({
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        type: 'POST',
        data: data,
        success: function(response) {
            if (response.success) {
                document.getElementById('rating-message').innerHTML = '<p style="color: green;">Rating updated successfully!</p>';
            } else {
                document.getElementById('rating-message').innerHTML = '<p style="color: red;">Error: ' + response.data.message + '</p>';
            }
        },
        error: function() {
            document.getElementById('rating-message').innerHTML = '<p style="color: red;">An error occurred while updating the rating.</p>';
        }
    });
});
</script>
