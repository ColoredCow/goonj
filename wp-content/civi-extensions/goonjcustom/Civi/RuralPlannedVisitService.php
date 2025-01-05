<?php

namespace Civi;

use Civi\Core\Service\AutoSubscriber;
use Civi\Api4\Event;

/**
 *
 */
class RuralPlannedVisitService extends AutoSubscriber {

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_tabset' => 'ruralPlannedVisitTabset',
    ];
  }

  /**
   *
   */
  public static function ruralPlannedVisitTabset($tabsetName, &$tabs, $context) {

    if ($tabsetName !== 'civicrm/event/manage') {
      return;
    }

    if (!isset($context['event_id'])) {
      return;
    }

    $eventId = $context['event_id'];

    $event = Event::get(FALSE)
      ->addSelect('event_type_id:name')
      ->addWhere('id', '=', $eventId)
      ->setLimit(1)
      ->execute()
      ->first();

    if (!$event) {
      \Civi::log()->error('Event not found', ['EventId' => $eventId]);
    }

    $eventType = $event['event_type_id:name'];

    if ($eventType !== 'Rural Planned Visit') {
      return;
    }

    $restrictedRoles = ['account_team', 'ho_account'];

    $isAdmin = \CRM_Core_Permission::check('admin');

    $hasRestrictedRole = !$isAdmin && \CRM_Core_Permission::checkAnyPerm($restrictedRoles);

    if ($hasRestrictedRole) {
      unset($tabs['view']);
      unset($tabs['edit']);
    }

    if (empty($context['event_id'])) {
      \Civi::log()->debug('No Event ID Found in Context');
      return;
    }

    $eventID = $context['event_id'];

    $tabConfigs = [
      'logistics' => [
        'id' => 'logistics',
        'title' => ts('Logistics'),
        'active' => 1,
        'module' => 'afsearchRuralLogisticsDetails',
        'directive' => 'afsearch-rural-logistics-details',
        'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'report' => [
        'id' => 'outcome report',
        'title' => ts('Outcome Report'),
        'active' => 1,
        'module' => 'afsearchRuralPlannedVisitsOutcomeReportsDetails',
        'directive' => 'afsearch-rural-planned-visits-outcome-reports-details',
        'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'outcome' => [
        'id' => 'outcome',
        'title' => ts('Outcome'),
        'active' => 1,
        'module' => 'afsearchRuralPlannedVisitOutcomeDetails',
        'directive' => 'afsearch-rural-planned-visit-outcome-details',
        'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'feedback' => [
        'id' => 'feedback',
        'title' => ts('Feedback'),
        'active' => 1,
        'module' => 'afsearchRuralPlannedVisitFeedbacksDetails',
        'directive' => 'afsearch-rural-planned-visit-feedbacks-details',
        'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
    ];

    foreach ($tabConfigs as $key => $config) {
      $isAdmin = \CRM_Core_Permission::check('admin');

      if (!\CRM_Core_Permission::checkAnyPerm($config['permissions'])) {
        // Does not permission; just continue.
        continue;
      }

      $tabs[$key] = [
        'id' => $key,
        'title' => $config['title'],
        'active' => 1,
        'template' => $config['template'],
        'module' => $config['module'],
        'directive' => $config['directive'],
        'entity' => $config['entity'],
        'valid' => 1,
      ];

      \Civi::service('angularjs.loader')->addModules($config['module']);
    }

  }

}
