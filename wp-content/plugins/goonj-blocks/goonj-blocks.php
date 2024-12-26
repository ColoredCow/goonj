<?php
/**
 * Plugin Name:       Goonj Blocks
 * Description:       WordPress blocks for Goonj
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Version:           0.1.0
 * Author:            ColoredCow
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       goonj-blocks
 *
 * @package Gb
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function gb_goonj_blocks_block_init() {
	register_block_type( __DIR__ . '/build' );
}
add_action( 'init', 'gb_goonj_blocks_block_init' );

add_action( 'init', 'gb_goonj_blocks_custom_rewrite_rules' );
function gb_goonj_blocks_custom_rewrite_rules() {
	$actions = array('collection-camp', 'dropping-center', 'processing-center', 'induction-schedule', 'institution-collection-camp', 'goonj-activities', 'institution-dropping-center', 'institution-goonj-activities', 'events');
	foreach ( $actions as $action ) {
		add_rewrite_rule(
			'^actions/' . $action . '/([0-9]+)/?',
			'index.php?pagename=actions&target=' . $action . '&id=$matches[1]',
			'top'
		);
	}
}

add_filter( 'query_vars', 'gb_goonj_blocks_query_vars' );
function gb_goonj_blocks_query_vars( $vars ) {
	$vars[] = 'target';
	$vars[] = 'id';
	$vars[] = 'source';
	$vars[] = 'source_contact_id';
	return $vars;
}

add_action( 'template_redirect', 'gb_goonj_blocks_check_action_target_exists' );
function gb_goonj_blocks_check_action_target_exists() {
	global $wp_query;

	if (
		! is_page( 'actions' ) ||
		! get_query_var( 'target' ) ||
		! get_query_var( 'id' )
	) {
		return;
	}

	$target = get_query_var( 'target' );
	$source_contact_id = get_query_var( 'source_contact_id' );

	$id = intval( get_query_var( 'id' ) );

	// Load CiviCRM.
	if ( function_exists( 'civicrm_initialize' ) ) {
		civicrm_initialize();
	}

	$is_404 = false;

	$entity_fields = array(
		'id',
		'title',
		'Collection_Camp_Intent_Details.Start_Date',
		'Collection_Camp_Intent_Details.End_Date',
		'Collection_Camp_Intent_Details.Location_Area_of_camp',
		'Collection_Camp_Intent_Details.City',
		'Collection_Camp_Intent_Details.State',
		'Dropping_Centre.Where_do_you_wish_to_open_dropping_center_Address_',
		'Dropping_Centre.State',
		'Dropping_Centre.District_City',
		'Collection_Camp_Core_Details.Contact_Id.display_name',
		'Goonj_Activities.State',
		'Goonj_Activities.District_City',
		'Goonj_Activities.Start_Date',
		'Goonj_Activities.End_Date',
		'Goonj_Activities.Where_do_you_wish_to_organise_the_activity_',
		'Goonj_Activities.Include_Attendee_Feedback_Form',
		'Goonj_Activities.Select_Attendee_feedback_form',
		'Institution_Dropping_Center_Intent.State',
		'Institution_Dropping_Center_Intent.City',
		'Institution_Dropping_Center_Intent.Institution_POC.display_name',
		'Institution_Dropping_Center_Intent.Dropping_Center_Address',
		'Institution_Goonj_Activities.State',
		'Institution_Goonj_Activities.City',
		'Institution_Goonj_Activities.Start_Date',
		'Institution_Goonj_Activities.End_Date',
		'Institution_Goonj_Activities.Where_do_you_wish_to_organise_the_activity_',
		'Institution_Goonj_Activities.Include_Attendee_Feedback_Form',
		'Institution_Goonj_Activities.Select_Attendee_feedback_form',
	);

	switch ( $target ) {
		case 'induction-schedule':
			$result = \Civi\Api4\Contact::get(FALSE)
				->addWhere('id', '=', $id)
				->setLimit(1)
				->execute();

			if ( $result->count()===0 ) {
				$is_404 = true;
			} else {
				$wp_query->set( 'action_target', $result->first() );
			}
			break;
		case 'collection-camp':
		case 'institution-collection-camp':
		case 'institution-dropping-center';
		case 'goonj-activities':
		case 'institution-goonj-activities':
		case 'dropping-center':
			$result = \Civi\Api4\EckEntity::get( 'Collection_Camp', false )
				->selectRowCount()
				->addSelect( ...$entity_fields )
				->addWhere( 'id', '=', $id )
				->setLimit( 1 )
				->execute();

			if ( $result->count() === 0 ) {
				$is_404 = true;
			} else {
				$wp_query->set( 'action_target', $result->first() );
			}
			break;
		case 'processing-center':
			$result = \Civi\Api4\Organization::get( false )
				->addWhere( 'id', '=', $id )
				->setLimit( 1 )
				->execute();

			if ( $result->count() === 0 ) {
				$is_404 = true;
			} else {
				$addresses = \Civi\Api4\Address::get( false )
					->addWhere( 'contact_id', '=', $id )
					->addWhere( 'is_primary', '=', true )
					->setLimit( 1 )
					->execute();

				$processing_center = $result->first();

				$processing_center['address'] = $addresses->count() > 0 ? $addresses->first() : null;
				$wp_query->set( 'action_target', $processing_center );
			}
			break;
		case 'events':
			$result = \Civi\Api4\Event::get(TRUE)
			->addSelect('*', 'loc_block_id.address_id')
			->addWhere('id', '=', $id)
			->setLimit(1)
			->execute();

			if ( $result->count() === 0 ) {
				$is_404 = true;
			} else {
				$event = $result->first();
				$addresses = \Civi\Api4\Address::get( false )
					->addWhere( 'id', '=', $event['loc_block_id.address_id'])
					->addWhere( 'is_primary', '=', true )
					->setLimit( 1 )
					->execute();

				$event['address'] = $addresses->count() > 0 ? $addresses->first() : null;
				$wp_query->set( 'action_target', $event );
			}
			break;
		default:
			$is_404 = true;
	}

	if ( $is_404 ) {
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		include get_query_template( '404' );
		exit;
	}
}
