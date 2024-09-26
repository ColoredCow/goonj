<?php

/**
 * @file
 */

require_once 'goonjcustom.civix.php';

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
    }
  }
}
