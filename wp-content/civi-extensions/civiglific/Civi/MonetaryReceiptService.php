<?php

namespace Civi;

use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class MonetaryReceiptService extends AutoSubscriber {

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => [],
    ];
  }

}
