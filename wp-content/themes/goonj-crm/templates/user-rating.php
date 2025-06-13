<?php

/**
 * @file
 * Template: user-rating.php.
 */

use Civi\Api4\Activity;

// Description: Content for user rating dropdown, used by [goonj_user_rating] shortcode.
// Fetch activity ID from URL query parameter.
$activity_id = isset($_GET['activityId']) ? intval($_GET['activityId']) : 0;
$entity_id = 0;

if (empty($activity_id)) {
  \Civi::log()->warning('Activity ID is missing');
  echo '<p style="color: red;">Error: Activity ID is missing.</p>';
}
else {
  try {
    // Fetch activity details.
    $activities = Activity::get(FALSE)
      ->addSelect(
              'Material_Contribution.Collection_Camp',
              'Material_Contribution.Institution_Collection_Camp',
              'Material_Contribution.Dropping_Center',
              'Material_Contribution.Institution_Dropping_Center',
              'Material_Contribution.Goonj_Office',
              'Office_Visit.Goonj_Processing_Center',
              'Material_Contribution.Event'
          )
      ->addWhere('id', '=', $activity_id)
      ->execute()
      ->first();

    error_log('Activities: ' . print_r($activities, TRUE));

    // Check for non-null camp ID.
    $fields = [
      'Material_Contribution.Collection_Camp',
      'Material_Contribution.Institution_Collection_Camp',
      'Material_Contribution.Dropping_Center',
      'Material_Contribution.Institution_Dropping_Center',
      'Material_Contribution.Goonj_Office',
      'Office_Visit.Goonj_Processing_Center',
      'Material_Contribution.Event',
    ];

    foreach ($fields as $field) {
      if (!empty($activities[$field])) {
        $entity_id = intval($activities[$field]);
        break;
      }
    }

    if (empty($entity_id)) {
      \Civi::log()->warning('No valid camp ID found for Activity ID: ' . $activity_id);
      echo '<p style="color: red;">Error: No valid camp ID found.</p>';
    }
  }
  catch (Exception $e) {
    \Civi::log()->warning('Error fetching activity: ' . $e->getMessage());
    echo '<p style="color: red;">Error: Failed to fetch activity details.</p>';
  }
}
error_log('Entity ID: ' . $entity_id);

// Only display the dropdown if a valid entity_id is found.
if ($entity_id > 0) :
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
        entity_id: <?php echo $entity_id; ?> // Dynamically set from activity
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

<?php endif;
