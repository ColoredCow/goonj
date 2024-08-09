<?php

require_once 'goonjcustom.civix.php';

use CRM_Goonjcustom_ExtensionUtil as E;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;


/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function goonjcustom_civicrm_config( &$config ): void {
	_goonjcustom_civix_civicrm_config( $config );
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function goonjcustom_civicrm_install(): void {
	_goonjcustom_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function goonjcustom_civicrm_enable(): void {
	_goonjcustom_civix_civicrm_enable();
}

/**
 * Add token services to the container.
 *
 * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
 */
function goonjcustom_civicrm_container( ContainerBuilder $container ) {
	$container->addResource( new FileResource( __FILE__ ) );
	$container->findDefinition( 'dispatcher' )->addMethodCall(
		'addListener',
		array( 'civi.token.list', 'goonjcustom_register_tokens' )
	)->setPublic( true );
	$container->findDefinition( 'dispatcher' )->addMethodCall(
		'addListener',
		array( 'civi.token.eval', 'goonjcustom_evaluate_tokens' )
	)->setPublic( true );
}

function goonjcustom_register_tokens( \Civi\Token\Event\TokenRegisterEvent $e ) {
	$e->entity( 'contact' )
	->register( 'inductionDetails', ts( 'Induction details' ) );
}

function goonjcustom_evaluate_tokens( \Civi\Token\Event\TokenValueEvent $e ) {
	foreach ( $e->getRows() as $row ) {
		/** @var TokenRow $row */
		$row->format( 'text/html' );

		$contactId = $row->context['contactId'];

		if ( empty( $contactId ) ) {
			$row->tokens( 'contact', 'inductionDetails', '' );
			continue;
		}

		$contacts = \Civi\Api4\Contact::get( false )
		->addSelect( 'address_primary.state_province_id' )
		->addWhere( 'id', '=', $contactId )
		->setLimit( 25 )
		->execute();

		$stateId = $contacts[0]['address_primary.state_province_id'];

		$processingCenters = \Civi\Api4\EckEntity::get( 'Processing_Center', false )
		->addSelect( '*', 'custom.*' )
		->addWhere( 'Processing_Center.Associated_States', 'IN', array( $stateId ) )
		->execute();

		$inductionDetailsMarkup = 'The next step in your volunteering journey is to get inducted with Goonj.';

		if ( $processingCenters->rowCount > 0 ) {
			$inductionDetailsMarkup .= ' You can visit any of our following center(s) during the time specified to complete your induction:';
			$inductionDetailsMarkup .= '<ol>';

			foreach ( $processingCenters as $processingCenter ) {
				$inductionDetailsMarkup .= '<li><strong>' . $processingCenter['title'] . '</strong>' . $processingCenter['Processing_Center.Induction_Details'] . '</li>';
			}

			$inductionDetailsMarkup .= '</ol>';
		} else {
			$inductionDetailsMarkup .= ' Unfortunately, we don\'t currently have a processing center near to the location you have provided. Someone from our team will reach out and will share the details of induction.';
		}

		$row->tokens( 'contact', 'inductionDetails', $inductionDetailsMarkup );
	}
}

/**
 * Implements hook_civicrm_buildForm().
 *
 * Set a default value for an event price set field.
 *
 * @param string        $formName
 * @param CRM_Core_Form $form
 */
function goonjcustom_civicrm_buildForm( $formName, $form ) {
	if ( $formName == 'CRM_Activity_Form_Activity' ) {
		$activityTypeId = $form->getVar( '_activityTypeId' );
		if ( $activityTypeId === 57 ) { // todo: find better way than hardcoding
			$fieldsToRemove = array(
				'subject',
				'engagement_level',
				'location',
				'duration',
				'priority_id',
			);

			foreach ( $fieldsToRemove as $field ) {
				if ( isset( $form->_elementIndex[ $field ] ) ) {
					$form->removeElement( $field );
				}
			}

			CRM_Core_Region::instance('page-body')->add(array(
				'script' => "
					CRM.$(function($) {
						function updateCustomGroupVisibility() {
							var selectedText = $('#status_id').find('option:selected').text();
							var customGroup = $('.custom-group-INDUCTION_DETAILS');

							if (selectedText === 'Completed') {
								customGroup.show();
								// TODO - need to add logic to handle required fields
							} else {
								customGroup.hide();
								// TODO - need to add logic to handle required fields
							}
						}
						$('#status_id').change(function() {
							updateCustomGroupVisibility();
						});

						// It takes time to load the form fields so adding a delay to run the js once form is loaded.
						setTimeout(updateCustomGroupVisibility, 500);
					});
				",
			));
		}
	}
}

function goonjcustom_civicrm_pageRun( &$page ) {
	// Check if we are on the activity edit form page
	CRM_Core_Region::instance( 'page-footer' )->add(
		array(
			'script' => '
			  (function($) {
				  $(document).ajaxComplete(function(event, xhr, settings) {
					var isInductionActivity = false;
					var isCollectionCampActivity = false;
					var urlParams = new URLSearchParams(settings.url);
					var activityTypeId57 = "57";
					var activityTypeId61 = "61";
					var activityViewTypeId61 = "61";

					if ((urlParams.get("atype") === activityTypeId57 && urlParams.get("action") === "view") ||
						(urlParams.get("subType") === activityTypeId57 && urlParams.get("action") === "2")) {
						isInductionActivity = true;
					}

					if ((urlParams.get("atype") === activityTypeId61 && urlParams.get("action") === "view") ||
						(urlParams.get("subType") === activityTypeId61 && urlParams.get("action") === "2")) {
						isCollectionCampActivity = true;
					}

					if (urlParams.get("subType") === activityViewTypeId61 && urlParams.get("action") === "view") {
						isActivityViewType61 = true;
					}

					if (isInductionActivity || isCollectionCampActivity || isActivityViewType61) {
					  var fieldsToHide = [];

					  if (isInductionActivity) {
						  fieldsToHide = [
							  ".crm-activity-form-block-subject",
							  ".crm-activity-form-block-campaign_id",
							  ".crm-activity-form-block-engagement_level",
							  ".crm-activity-form-block-duration",
							  ".crm-activity-form-block-priority_id",
							  ".crm-activity-form-block-location",
							  ".crm-activity-form-block-attachment",
							  ".crm-activity-form-block-recurring_activity",
							  ".crm-activity-form-block-schedule_followup",
						  ];
					  } else if (isCollectionCampActivity) {
						  fieldsToHide = [
							  ".crm-activity-form-block-subject",
							  ".crm-activity-form-block-engagement_level",
							  ".crm-activity-form-block-duration",
							  ".crm-activity-form-block-priority_id",
							  ".crm-activity-form-block-location",
							  ".crm-activity-form-block-attachment",
							  ".crm-activity-form-block-recurring_activity",
							  ".crm-activity-form-block-schedule_followup",
							  ".crm-activity-form-block-target_contact_id",
							  ".crm-activity-form-block-assignee_contact_id",
							  ".crm-activity-form-block-activity_date_time",
							  ".crm-activity-form-block-details",
						  ];
					  } else if (activityViewTypeId61) {
						  fieldsToHide = [
							  ".crm-activity-form-block-target_contact_id",
							  ".crm-activity-form-block-assignee_contact_id",
							  ".crm-activity-form-block-engagement_level",
							  ".crm-activity-form-block-duration",
							  ".crm-activity-form-block-details",
							  ".crm-activity-form-block-priority_id",
							  ".crm-activity-form-block-subject",
							  ".crm-activity-form-block-location",
						  ];
					  }

						fieldsToHide.forEach(function(field) {
							$(field).css("display", "none");
						});

					  if (isInductionActivity) {
						  var inductionFields = $(".custom-group-Induction_Fields tr");
						  $(".crm-activity-form-block-activity_date_time").after(inductionFields);
						  $(".custom-group-Induction_Fields").remove();
					  }
					}
				  });
			  })(CRM.$);
		  ',
		)
	);
}

function goonjcustom_civicrm_tabset($tabsetName, &$tabs, $context) {
	if ($tabsetName !== 'civicrm/event/manage' || empty($context)) {
		return;
	}

	$url = CRM_Utils_System::url(
		'civicrm/event/manage/qr',
		"reset=1&snippet=5&force=1&id=$eventID&action=update&component=event"
	);

	$tabsToRemove = [
		'event' => [
			'manage' => [
				'fee',
				'registration',
				'friend',
				'pcp',
			],
		],
	];

	foreach ($tabsToRemove['event']['manage'] as $toRemove) {
		unset($tabs[$toRemove]);
	}

	// Add a new QR tab along with URL.
	$tabs['qr'] = [
		'title' => ts('QR Codes'),
		'link' => $url,
		'valid' => 1,
		'active' => 1,
		'current' => false,
	];
}
