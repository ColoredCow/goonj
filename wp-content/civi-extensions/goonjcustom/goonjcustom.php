<?php

/**
 * @file
 */

require_once 'goonjcustom.civix.php';

use Civi\Api4\Contact;
use Civi\Api4\EckEntity;
use Civi\Api4\Event;
use Civi\Token\Event\TokenRegisterEvent;
use Civi\Token\Event\TokenValueEvent;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function goonjcustom_civicrm_config(&$config): void {
  _goonjcustom_civix_civicrm_config($config);
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
function goonjcustom_civicrm_container(ContainerBuilder $container) {
  $container->addResource(new FileResource(__FILE__));
  $container->findDefinition('dispatcher')->addMethodCall(
        'addListener',
        ['civi.token.list', 'goonjcustom_register_tokens']
  )->setPublic(TRUE);
  $container->findDefinition('dispatcher')->addMethodCall(
        'addListener',
        ['civi.token.eval', 'goonjcustom_evaluate_tokens']
  )->setPublic(TRUE);
}

/**
 *
 */
function goonjcustom_register_tokens(TokenRegisterEvent $e) {
  $e->entity('contact')
    ->register('inductionDetails', ts('Induction details'));
}

/**
 *
 */
function goonjcustom_evaluate_tokens(TokenValueEvent $e) {
  foreach ($e->getRows() as $row) {
    /** @var TokenRow $row */
    $row->format('text/html');

    $contactId = $row->context['contactId'];

    if (empty($contactId)) {
      $row->tokens('contact', 'inductionDetails', '');
      continue;
    }

    $contacts = Contact::get(FALSE)
      ->addSelect('address_primary.state_province_id')
      ->addWhere('id', '=', $contactId)
      ->setLimit(25)
      ->execute();

    $stateId = $contacts[0]['address_primary.state_province_id'];

    $processingCenters = EckEntity::get('Processing_Center', FALSE)
      ->addSelect('*', 'custom.*')
      ->addWhere('Processing_Center.Associated_States', 'IN', [$stateId])
      ->execute();

    $inductionDetailsMarkup = 'The next step in your volunteering journey is to get inducted with Goonj.';

    if ($processingCenters->rowCount > 0) {
      $inductionDetailsMarkup .= ' You can visit any of our following center(s) during the time specified to complete your induction:';
      $inductionDetailsMarkup .= '<ol>';

      foreach ($processingCenters as $processingCenter) {
        $inductionDetailsMarkup .= '<li><strong>' . $processingCenter['title'] . '</strong>' . $processingCenter['Processing_Center.Induction_Details'] . '</li>';
      }

      $inductionDetailsMarkup .= '</ol>';
    }
    else {
      $inductionDetailsMarkup .= ' Unfortunately, we don\'t currently have a processing center near to the location you have provided. Someone from our team will reach out and will share the details of induction.';
    }

    $row->tokens('contact', 'inductionDetails', $inductionDetailsMarkup);
  }
}

/**
 * Implements hook_civicrm_buildForm().
 *
 * Set a default value for an event price set field.
 *
 * @param string $formName
 * @param CRM_Core_Form $form
 */
function goonjcustom_civicrm_buildForm($formName, $form) {
  if ($formName == 'CRM_Activity_Form_Activity') {
    $activityTypeId = $form->getVar('_activityTypeId');
    // @todo find better way than hardcoding
    if ($activityTypeId === 57) {
      $fieldsToRemove = [
        'subject',
        'engagement_level',
        'location',
        'duration',
        'priority_id',
      ];

      foreach ($fieldsToRemove as $field) {
        if (isset($form->_elementIndex[$field])) {
          $form->removeElement($field);
        }
      }

      CRM_Core_Region::instance('page-body')->add([
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
      ]);
    }
  }
}

/**
 *
 */
function goonjcustom_civicrm_pageRun(&$page) {
  // Check if we are on the activity edit form page.
  CRM_Core_Region::instance('page-footer')->add(
        [
          'script' => '
			  (function($) {
				  $(document).ajaxComplete(function(event, xhr, settings) {
					var isInductionActivity = false;
					var isCollectionCampActivity = false;
					var isActivityViewType61 = false;
					var urlParams = new URLSearchParams(settings.url);
					var activityTypeId57 = "57";
					var activityTypeId61 = "61";

					if ((urlParams.get("atype") === activityTypeId57 && urlParams.get("action") === "view") ||
						(urlParams.get("subType") === activityTypeId57 && urlParams.get("action") === "2")) {
						isInductionActivity = true;
					}

					if ((urlParams.get("atype") === activityTypeId61 && urlParams.get("action") === "view") ||
						(urlParams.get("subType") === activityTypeId61 && urlParams.get("action") === "2")) {
						isCollectionCampActivity = true;
					}

					if (urlParams.get("subType") === activityTypeId61 && urlParams.get("action") === "view") {
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
						} else if (isActivityViewType61) {
							fieldsToHide = [
								".crm-activity-form-block-target_contact_id",
								".crm-activity-form-block-assignee_contact_id",
								".crm-activity-form-block-engagement_level",
								".crm-activity-form-block-duration",
								".crm-activity-form-block-details",
								".crm-activity-form-block-priority_id",
								".crm-activity-form-block-subject",
								".crm-activity-form-block-location",
								".crm-activity-form-block-campaign_id",
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
        ]
  );
}

/**
 *
 */
function goonjcustom_civicrm_tabset($tabsetName, &$tabs, $context) {
  if ($tabsetName !== 'civicrm/event/manage' || empty($context)) {
    return;
  }

  $eventID = $context['event_id'];

  $url = CRM_Utils_System::url(
        'civicrm/event/manage/qr',
        "reset=1&snippet=5&force=1&id=$eventID&action=update&component=event"
  );

  $intentId = Event::get(FALSE)
    ->addSelect('*', 'Event_Volunteers.Collection_Camp_Intent')
    ->addWhere('id', '=', $eventID)
    ->setLimit(1)
    ->execute();

  $collectionCampIntentId = $intentId->first()['Event_Volunteers.Collection_Camp_Intent'] ?? NULL;
  $collectionCampEventTypeId = $intentId->first()['event_type_id'] ?? NULL;

  // URL for the Intent tab.
  $intentUrl = CRM_Utils_System::url(
        "/wp-admin/admin.php?page=CiviCRM&q=civicrm%2Factivity%2Fadd&reset=1&type=Event&subType=$collectionCampEventTypeId&action=view&id=$collectionCampIntentId"
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

  // Add the Intent tab.
  $tabs['intent'] = [
    'title' => ts('Intent'),
    'link' => $intentUrl,
    'valid' => 1,
    'active' => 1,
    'current' => FALSE,
  ];

  // Add a new QR tab along with URL.
  $tabs['qr'] = [
    'title' => ts('QR Codes'),
    'link' => $url,
    'valid' => 1,
    'active' => 1,
    'current' => FALSE,
  ];
  // Hide the Intent fields by adding JavaScript specifically to the Intent page.
  if ((isset($_GET['selectedChild']) && $_GET['selectedChild'] === 'intent') || (isset($_GET['action']) && $_GET['action'] === 'update' && isset($_GET['component']) && $_GET['component'] === 'event')) {
    CRM_Core_Region::instance('page-footer')->add(
    [
      'script' => '
				(function($) {
					$(document).ajaxComplete(function(event, xhr, settings) {
						var urlParams = new URLSearchParams(settings.url);
						var currentUrl = window.location.href;
						var isEventSubType = urlParams.get("subType") === "7";

						if (isEventSubType) {
							var fieldsToHide = [
								".crm-activity-form-block-target_contact_id",
								".crm-activity-form-block-assignee_contact_id",
								".crm-activity-form-block-engagement_level",
								".crm-activity-form-block-duration",
								".crm-activity-form-block-details",
								".crm-activity-form-block-priority_id",
								".crm-activity-form-block-subject",
								".crm-activity-form-block-location",
								".crm-activity-form-block-status_id",
								".crm-activity-form-block-activity_date_time",
								".crm-activity-form-block-activity_date_time",
								".crm-accordion-bold summary",
								".crm-activity-form-block-source_contact_id",
								".crm-activity-form-block-campaign_id"
							];

							fieldsToHide.forEach(function(field) {
								$(field).css("display", "none");
							});
						}
					});
				})(CRM.$);
				',
    ]
    );
  }
}
