<?php

namespace Civi;

use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class DroppingCenterService extends AutoSubscriber {

  const ENTITY_NAME = 'Collection_Camp';
  const ENTITY_SUBTYPE_NAME = 'Dropping_Center';

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_tabset' => 'droppingCenterTabset',
    ];
  }

  /**
   *
   */
  public static function droppingCenterTabset($tabsetName, &$tabs, $context) {
    if (!self::isViewingDroppingCenter($tabsetName, $context)) {
      return;
    }
    $tabConfigs = [
      'SendDispatchEmail' => [
        'title' => ts('Send Dispatch Email'),
        'module' => 'afformSendDispatchEmail',
        'directive' => 'afform-send-dispatch-email',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCampService.tpl',
      ],
      'status' => [
        'title' => ts('Status'),
        'module' => 'afsearchDroppingCenterStatus',
        'directive' => 'afsearch-dropping-center-status',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
      ],
      'visitDetails' => [
        'title' => ts('Visit Details'),
        'module' => 'afsearchVisitDetails',
        'directive' => 'afsearch-visit-details',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
      ],
      'donationTracking' => [
        'title' => ts('Donation Tracking'),
        'module' => 'afsearchDroppingCenterDonationBoxRegisterList',
        'directive' => 'afsearch-dropping-center-donation-box-register-list',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
      ],
      'logisticsCoordination' => [
        'title' => ts('Logistics'),
        'module' => 'afsearchDroppingCenterLogisticsCoordination',
        'directive' => 'afsearch-dropping-center-logistics-coordination',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
      ],
      'outcome' => [
        'title' => ts('Outcome'),
        'module' => 'afformDroppingCenterOutcome',
        'directive' => 'afform-dropping-center-outcome',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCampService.tpl',
      ],
    ];

    foreach ($tabConfigs as $key => $config) {
      $tabs[$key] = [
        'id' => $key,
        'title' => $config['title'],
        'is_active' => 1,
        'template' => $config['template'],
        'module' => $config['module'],
        'directive' => $config['directive'],
      ];

      \Civi::service('angularjs.loader')->addModules($config['module']);
    }
  }

  /**
   *
   */
  private static function isViewingDroppingCenter($tabsetName, $context) {
    if ($tabsetName !== 'civicrm/eck/entity' || empty($context) || $context['entity_type']['name'] !== self::ENTITY_NAME) {
      return FALSE;
    }

    $entityId = $context['entity_id'];

    $entityResults = EckEntity::get(self::ENTITY_NAME, TRUE)
      ->addWhere('id', '=', $entityId)
      ->execute();

    $entity = $entityResults->first();

    $entitySubtypeValue = $entity['subtype'];

    $subtypeResults = OptionValue::get(TRUE)
      ->addSelect('name')
      ->addWhere('grouping', '=', self::ENTITY_NAME)
      ->addWhere('value', '=', $entitySubtypeValue)
      ->execute();

    $subtype = $subtypeResults->first();

    if (!$subtype) {
      return FALSE;
    }

    $subtypeName = $subtype['name'];

    if ($subtypeName !== self::ENTITY_SUBTYPE_NAME) {
      return FALSE;
    }

    return TRUE;
  }

}
