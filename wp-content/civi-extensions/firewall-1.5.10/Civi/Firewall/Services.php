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
namespace Civi\Firewall;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Services
 *
 * Define the services
 */
class Services {

  public static function registerServices(ContainerBuilder $container) {
    $container->addResource(new \Symfony\Component\Config\Resource\FileResource(__FILE__));
    $container
      ->setDefinition('civi.firewall.formprotection', new Definition('\Civi\Firewall\Listener\FormProtection'))
      ->addTag('kernel.event_subscriber')
      ->setPublic(TRUE);
    $container
      ->setDefinition('civi.firewall.declinedcard', new Definition('\Civi\Firewall\Listener\DeclinedCard'))
      ->addTag('kernel.event_subscriber')
      ->setPublic(TRUE);
    $container
      ->setDefinition('civi.firewall.fraudulentrequest', new Definition('\Civi\Firewall\Listener\FraudulentRequest'))
      ->addTag('kernel.event_subscriber')
      ->setPublic(TRUE);
    $container
      ->setDefinition('civi.firewall.invalidcsrfrequest', new Definition('\Civi\Firewall\Listener\InvalidCSRFRequest'))
      ->addTag('kernel.event_subscriber')
      ->setPublic(TRUE);
    $container
      ->setDefinition('civi.firewall.stripeauthorize', new Definition('Civi\Firewall\Listener\StripeAuthorize'))
      ->addTag('kernel.event_subscriber')
      ->setPublic(TRUE);
  }

}
