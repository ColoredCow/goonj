<?php

/**
 * @file
 */

require_once 'goonjcustom.civix.php';

use Civi\InductionService;
use Civi\Api4\Address;
use Civi\Api4\Contact;
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

  \Civi::dispatcher()->addSubscriber(new CRM_Goonjcustom_Token_CollectionCamp());
  \Civi::dispatcher()->addSubscriber(new CRM_Goonjcustom_Token_DroppingCenter());
  \Civi::dispatcher()->addSubscriber(new CRM_Goonjcustom_Token_InstitutionCollectionCamp());
  \Civi::dispatcher()->addSubscriber(new CRM_Goonjcustom_Token_InstitutionDroppingCenter());
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
    ->register('inductionDetails', ts('Induction details'))
    ->register('inductionDate', ts('Induction Scheduled Date'))
    ->register('inductionTime', ts('Induction Scheduled Time'))
    ->register('inductionOnlineMeetlink', ts('Induction Online Meet link'))
    ->register('inductionOfficeAddress', ts('Induction Office Address'))
    ->register('inductionOfficeCity', ts('Induction Office City'))
    ->register('urbanOpsName', ts('Urban Ops Name'))
    ->register('urbanOpsPhone', ts('Urban Ops Phone'));
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
      ->execute();

    $statedata = $contacts->first();
    $stateId = $statedata['address_primary.state_province_id'];

    $processingCenters = Contact::get(FALSE)
      ->addSelect('Goonj_Office_Details.Days_for_Induction', '*', 'custom.*', 'addressee_id', 'id')
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('Goonj_Office_Details.Induction_Catchment', 'CONTAINS', $stateId)
      ->execute();

    $inductionDetailsMarkup = 'The next step in your volunteering journey is to get inducted with Goonj.';

    if ($processingCenters->rowCount > 0) {
      if (TRUE) {
        $inductionDetailsMarkup .= ' You can visit any of our following center(s) during the time specified to complete your induction:';
        $inductionDetailsMarkup .= '<ol>';

        foreach ($processingCenters as $processingCenter) {
          $centerID = $processingCenter['id'];
          // Fetch the primary address for the current center.
          $addressesData = Address::get(FALSE)
            ->addWhere('contact_id', '=', $centerID)
            ->addWhere('is_primary', '=', TRUE)
            ->setLimit(1)
            ->execute();
          $address = $addressesData->first();
          $contactAddress = $address['street_address'];

          $inductionDetailsMarkup .= '<li><strong>' . $processingCenter['organization_name'] . '</strong><br /><span style="margin-top: 10px; display: block;">' . $contactAddress . '</span> ' . $processingCenter['Goonj_Office_Details.Days_for_Induction'] . '</li>';

        }

        $inductionDetailsMarkup .= '</ol>';
      }
      else {
        $inductionDetailsMarkup .= ' Unfortunately, we don\'t currently have a processing center near to the location you have provided. Someone from our team will reach out and will share the details of induction.';
      }

      $row->tokens('contact', 'inductionDetails', $inductionDetailsMarkup);
			// Fetch induction activity details
			$inductionActivities = \Civi\Api4\Activity::get(FALSE)
				->addSelect('activity_date_time', 'Induction_Fields.Goonj_Office')
				->addWhere('activity_type_id:name', '=', 'Induction')
				->addWhere('source_contact_id', '=', $contactId)
				->execute();

			if ($inductionActivities->count() === 0) {
				$row->tokens('contact', 'inductionDate', 'Not Scheduled');
        $row->tokens('contact', 'inductionTime', 'Not Scheduled');
				$row->tokens('contact', 'inductionOnlineMeetlink', '');
				return;
			}

			$inductionActivity = $inductionActivities->first();
			$inductionDateTimeString = $inductionActivity['activity_date_time'] ?? 'Not Scheduled';

      if ($inductionDateTimeString !== 'Not Scheduled') {
        $dateTime = new DateTime($inductionDateTimeString);
        $inductionDate = $dateTime->format('Y-m-d');
        $inductionTime = $dateTime->format('g:i A');
      } else {
          $inductionDate = 'Not Scheduled';
          $inductionTime = 'Not Scheduled';
      }

			$inductionGoonjOffice = $inductionActivity['Induction_Fields.Goonj_Office'] ?? '';

			// Fetch office online meet link details if induction office is specified
			$inductionOnlineMeetlink = '';
      $completeAddress = '';
      $inductionOfficeCity = '';
      $urbanOpsName = '';
      $urbanOpsPhone = '';


			if ($inductionGoonjOffice) {
				$officeDetails = \Civi\Api4\Contact::get(FALSE)
					->addSelect('Goonj_Office_Details.Induction_Meeting_Access_Link', 'address_primary.city')
					->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
					->addWhere('id', '=', $inductionGoonjOffice)
					->execute()->single();

				$inductionOnlineMeetlink = $officeDetails['Goonj_Office_Details.Induction_Meeting_Access_Link'] ?? '';
        $inductionOfficeCity = $officeDetails['address_primary.city'] ?? '';

        $addresses = \Civi\Api4\Address::get( false )
          ->addWhere( 'contact_id', '=', $officeDetails['id'] )
          ->addWhere( 'is_primary', '=', true )
          ->setLimit( 1 )
          ->execute();

        $address = $addresses->count() > 0 ? $addresses->first() : null;
        $completeAddress = CRM_Utils_Address::format($address);

			}

      $office = InductionService::findOfficeForState($stateId);

      $coordinatorId = InductionService::findCoordinatorForOffice($office['id']);

  
      $coordinatorDetails = Contact::get(FALSE)
        ->addSelect('phone.phone', 'display_name')
        ->addJoin('Phone AS phone', 'LEFT')
        ->addWhere('id', '=', $coordinatorId)
        ->execute();
      $coordinatorDetail = $coordinatorDetails->first();

      $urbanOpsName = $coordinatorDetail['display_name'] ?? '';
      $urbanOpsPhone = $coordinatorDetail['phone.phone'] ?? '';

			// Assign tokens
			$row->tokens('contact', 'inductionDate', $inductionDate);
      $row->tokens('contact', 'inductionTime', $inductionTime);
			$row->tokens('contact', 'inductionOnlineMeetlink', $inductionOnlineMeetlink);
      $row->tokens('contact', 'inductionOfficeAddress', $completeAddress);
      $row->tokens('contact', 'inductionOfficeCity', $inductionOfficeCity);
      $row->tokens('contact', 'urbanOpsName', $urbanOpsName);
      $row->tokens('contact', 'urbanOpsPhone', $urbanOpsPhone);
    }
  }
}
