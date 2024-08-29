<?php

namespace Civi;

use Civi\Afform\Event\AfformValidateEvent;
use Civi\Api4\Contact;
use Civi\Core\Service\AutoSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 *
 */
class GoonjUniqueUserValidation extends AutoSubscriber implements EventSubscriberInterface {

  /**
   * @return array
   */
  public static function getSubscribedEvents() {
    return [
      'civi.afform.validate' => ['onAfformValidate'],
    ];
  }

  /**
   *
   */
  public static function onAfformValidate(AfformValidateEvent $event) {
    \Civi::log()->info('Validation event triggered');
    $event->setError('The field "some_field" cannot be empty'); 
    }
  }
