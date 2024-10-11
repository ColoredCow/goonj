<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

require_once __DIR__ . '/functions.php';
$target        = get_query_var( 'target' );
$action_target = get_query_var( 'action_target' );

$headings = array(
    'collection-camp' => 'Collection Camp',
    'dropping-center' => 'Dropping Center',
    'processing-center' => 'Processing Center',
);

$heading_text = $headings[ $target ];

$register_link = sprintf(
	'/volunteer-registration/form/#?source=%s&state_province_id=%s&city=%s',
	$action_target['title'],
	$action_target['Collection_Camp_Intent_Details.State'],
	$action_target['Collection_Camp_Intent_Details.City'],
);

$material_contribution_link = sprintf(
	'/collection-camp-contribution?source=%s&target_id=%s&state_province_id=%s&city=%s',
	$action_target['title'],
	$action_target['id'],
	$action_target['Collection_Camp_Intent_Details.State'],
	$action_target['Collection_Camp_Intent_Details.City'],
);

$dropping_center_material_contribution_link = sprintf(
    '/dropping-center-contribution?source=%s&target_id=%s&state_province_id=%s&city=%s',
    $action_target['title'],
    $action_target['id'],
	$action_target['Dropping_Centre.State'],
	$action_target['Dropping_Centre.District_City'],
);

$pu_visit_check_link = sprintf(
	'/processing-center/office-visit/?target_id=%s',
	$action_target['id']
);

$pu_material_contribution_check_link = sprintf(
	'/processing-center/material-contribution/?target_id=%s',
	$action_target['id']
);

$target_data = [
	'dropping-center' => [
		'start_time' => 'Dropping_Centre.Start_Time',
		'end_time' => 'Dropping_Centre.End_Time',
		'address' => 'Dropping_Centre.Where_do_you_wish_to_open_dropping_center_Address_',
		'contribution_link' => $dropping_center_material_contribution_link,
	],
	'collection-camp' => [
		'start_time' => 'Collection_Camp_Intent_Details.Start_Date',
		'end_time' => 'Collection_Camp_Intent_Details.End_Date',
		'address' => 'Collection_Camp_Intent_Details.Location_Area_of_camp',
		'contribution_link' => $material_contribution_link,
	],
];

if ( in_array( $target, array( 'collection-camp', 'dropping-center' ) ) ) :
    $target_info = $target_data[$target];
    
    $start_date = new DateTime($action_target[$target_info['start_time']]);
    $end_date = new DateTime($action_target[$target_info['end_time']]);
    $address = $action_target[$target_info['address']];
    $contribution_link = $target_info['contribution_link'];

	?>
	<div class="wp-block-gb-heading-wrapper">
		<h2 class="wp-block-gb-heading"><?php echo esc_html($heading_text); ?></h2>
	</div>
    <table class="wp-block-gb-table">
        <tbody>
            <tr class="wp-block-gb-table-row">
                <td class="wp-block-gb-table-cell wp-block-gb-table-header">From</td>
                <td class="wp-block-gb-table-cell"><?php echo gb_format_date($start_date); ?></td>
            </tr>
            <tr class="wp-block-gb-table-row">
                <td class="wp-block-gb-table-cell wp-block-gb-table-header">To</td>
                <td class="wp-block-gb-table-cell"><?php echo gb_format_date($end_date); ?></td>
            </tr>
            <tr class="wp-block-gb-table-row">
                <td class="wp-block-gb-table-cell wp-block-gb-table-header">Time</td>
                <td class="wp-block-gb-table-cell"><?php echo gb_format_time_range($start_date, $end_date); ?></td>
            </tr>
            <tr class="wp-block-gb-table-row">
                <td class="wp-block-gb-table-cell wp-block-gb-table-header">Address of the camp</td>
                <td class="wp-block-gb-table-cell"><?php echo esc_html($address); ?></td>
            </tr>
        </tbody>
    </table>
	<div <?php echo get_block_wrapper_attributes(); ?>>
		<a href="<?php echo esc_url( $register_link ); ?>" class="wp-block-gb-action-button">
			<?php esc_html_e( 'Volunteer with Goonj', 'goonj-blocks' ); ?>
		</a>
		<a href="<?php echo esc_url( $contribution_link ); ?>" class="wp-block-gb-action-button">
			<?php esc_html_e( 'Record your Material Contribution', 'goonj-blocks' ); ?>
		</a>
	</div>
	<?php elseif ( 'processing-center' === $target ) : ?>
		<table class="wp-block-gb-table">
			<tbody>
				<tr class="wp-block-gb-table-row">
					<td class="wp-block-gb-table-cell wp-block-gb-table-header">Address</td>
					<td class="wp-block-gb-table-cell"><?php echo CRM_Utils_Address::format( $action_target['address'] ); ?></td>
				</tr>
			</tbody>
		</table>
		<div <?php echo get_block_wrapper_attributes(); ?>>
			<a href="<?php echo esc_url( $pu_visit_check_link ); ?>" class="wp-block-gb-action-button">
				<?php esc_html_e( 'Office Visit', 'goonj-blocks' ); ?>
			</a>
			<a href="<?php echo esc_url( $pu_material_contribution_check_link ); ?>" class="wp-block-gb-action-button">
				<?php esc_html_e( 'Material Contribution', 'goonj-blocks' ); ?>
			</a>
		</div>
<?php endif;
