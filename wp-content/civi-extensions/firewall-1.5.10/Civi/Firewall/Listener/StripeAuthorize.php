<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */
namespace Civi\Firewall\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StripeAuthorize implements EventSubscriberInterface {

  /**
   * @return array
   */
  public static function getSubscribedEvents() {
    return [
      'civi.stripe.authorize' => [
        // Positive priority is higher (eg. 200 will run before 100)
        ['onStripeAuthorize', 200],
      ],
    ];
  }

  /**
   * Alters APIv4 permissions to allow users with 'administer search_kit' to create/delete a SavedSearch
   *
   * @param \Civi\Stripe\Event\AuthorizeEvent $event
   *   API authorization event.
   */
  public function onStripeAuthorize(\Civi\Stripe\Event\AuthorizeEvent $event) {
    if ($event->getEntityName() !== 'StripePaymentintent') {
      return;
    }
    // 6.7 has API3 process endpoint, 6.8 has API4 processPublic, processMOTO endpoint.
    if (!in_array($event->getActionName(), ['process', 'processPublic'])) {
      return;
    }

    // Check params
    if (empty($event->getParams()['csrfToken'])) {
      $event->setAuthorized(FALSE);
      $event->setReasonDescription(__CLASS__, 'Mising CSRF token in params');
    }

    // Check firewall
    $csrfToken = $event->getParams()['csrfToken'];
    $firewall = new \Civi\Firewall\Firewall();
    if (!$firewall->checkIsCSRFTokenValid(\CRM_Utils_Type::validate($csrfToken, 'String'))) {
      $event->setReasonDescription(__CLASS__, $firewall->getReasonDescription());
      $event->setAuthorized(FALSE);
    }
  }

}
