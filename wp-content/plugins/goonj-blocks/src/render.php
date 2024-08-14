<?php
/**
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

$target        = get_query_var( 'target' );
$action_target = get_query_var( 'action_target' );

$headings = array(
    'collection-camp' => 'Collection Camp',
    'dropping-center' => 'Dropping Center',
    'processing-center' => 'Processing Center',
);

$heading_text = $headings[ $target ];

$register_link = sprintf(
	'/individual-registration-with-volunteer-option/#?Source_Tracking.Event=%s',
	$action_target['id'],
);

$material_contribution_link = sprintf(
	'/collection-camp-contribution/#?Source_Tracking.Event=%s',
	$action_target['id'],
);

if ( in_array( $target, array( 'collection-camp', 'dropping-center' ) ) ) :
	$start_date = new DateTime( $action_target['start_date'] );
	$end_date   = new DateTime( $action_target['end_date'] );
	?>
	<div class="wp-block-gb-heading-wrapper">
		<h2 class="wp-block-gb-heading"><?php echo esc_html($heading_text); ?></h2>
	</div>
	<table class="wp-block-gb-table">
		<tbody>
			<tr class="wp-block-gb-table-row">
				<td class="wp-block-gb-table-cell wp-block-gb-table-header">Start date</td>
				<td class="wp-block-gb-table-cell"><?php echo $start_date->format( 'd-m-Y h:i A' ); ?></td>
			</tr>
			<tr class="wp-block-gb-table-row">
				<td class="wp-block-gb-table-cell wp-block-gb-table-header">End date</td>
				<td class="wp-block-gb-table-cell"><?php echo $end_date->format( 'd-m-Y h:i A' ); ?></td>
			</tr>
		</tbody>
	</table>
	<div <?php echo get_block_wrapper_attributes(); ?>>
		<a href="<?php echo esc_url( $register_link ); ?>" class="wp-block-gb-action-button">
			<?php esc_html_e( 'Register', 'goonj-blocks' ); ?>
		</a>
		<a href="<?php echo esc_url( $material_contribution_link ); ?>" class="wp-block-gb-action-button">
			<?php esc_html_e( 'Material Contribution', 'goonj-blocks' ); ?>
		</a>
		<a href="" class="wp-block-gb-action-button">
			<?php esc_html_e( 'Donate', 'goonj-blocks' ); ?>
		</a>
	</div>
	<?php elseif ( 'processing-center' === $target ) : ?>
		<div <?php echo get_block_wrapper_attributes(); ?>>
			<a href="" class="wp-block-gb-action-button">
				<?php esc_html_e( 'Contribution', 'goonj-blocks' ); ?>
			</a>
			<a href="" class="wp-block-gb-action-button">
				<?php esc_html_e( 'Contribution Monetary', 'goonj-blocks' ); ?>
			</a>
			<a href="" class="wp-block-gb-action-button">
				<?php esc_html_e( 'Goonj Visit', 'goonj-blocks' ); ?>
			</a>
		</div>
<?php endif;
