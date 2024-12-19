<?php

namespace Civi;

use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class UrbanPlannedVisits extends AutoSubscriber {
  private static $individualId = NULL;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => [
        ['assignChapterGroupToIndividualForUrbanPlannedVisit'],
        ['individualCreated'],

      ],
    ];
  }

}
