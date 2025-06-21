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

class InvalidCSRFRequest implements EventSubscriberInterface {

  /**
   * @return array
   */
  public static function getSubscribedEvents() {
    return [
      'civi.firewall.invalidcsrfrequest' => [
        // Positive priority is higher (eg. 200 will run before 100)
        ['onTrigger', 2000],
      ],
    ];
  }

  public function onTrigger(\Civi\Firewall\Event\InvalidCSRFEvent $event) {
    // Add to firewall ip address log table with timestamp + event type
    \Civi\Api4\FirewallIpaddress::create()
      ->setCheckPermissions(FALSE)
      ->addValue('ip_address', $event->ipAddress)
      ->addValue('source', $event->source)
      ->addValue('event_type', $event->eventType)
      ->execute();
  }

}
