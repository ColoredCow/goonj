<?php

namespace Civi;

use Civi\Api4\EckEntity;
use Civi\Api4\OptionValue;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\QrCodeable;

/**
 *
 */
class DroppingCenterService extends AutoSubscriber {
  use QrCodeable;

  const ENTITY_NAME = 'Collection_Camp';
  const ENTITY_SUBTYPE_NAME = 'Dropping_Center';


  private static $subtypeId;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_tabset' => 'droppingCenterTabset',
      '&hook_civicrm_pre' => 'generateDroppingCenterQr',
    ];
  }

  /**
   *
   */
  private static function isDroppingCenterSubtype($objectRef) {
    // @todo need to remove from here.
    self::init();
    return (int) $objectRef['subtype'] === self::$subtypeId;
  }

  /**
   *
   */
  public static function generateDroppingCenterQr(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName != 'Eck_Collection_Camp' || !$objectId || self::isDroppingCenterSubtype($objectRef)) {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';

    if (!$newStatus) {
      return;
    }

    $collectionCamps = EckEntity::get('Collection_Camp', TRUE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_Core_Details.Contact_Id')
      ->addWhere('id', '=', $objectId)
      ->execute();

    $currentCollectionCamp = $collectionCamps->first();
    $currentStatus = $currentCollectionCamp['Collection_Camp_Core_Details.Status'];
    $collectionCampId = $currentCollectionCamp['id'];

    // Check for status change.
    if ($currentStatus !== $newStatus) {
      if ($newStatus === 'authorized') {
        self::generateDroppingCenterQrCode($collectionCampId);
      }
    }
  }

  /**
   *
   */
  private static function generateDroppingCenterQrCode($id) {
    $baseUrl = \CRM_Core_Config::singleton()->userFrameworkBaseURL;
    $data = "{$baseUrl}actions/dropping-center/{$id}";

    $saveOptions = [
      'customGroupName' => 'Collection_Camp_QR_Code',
      'customFieldName' => 'QR_Code',
    ];

    self::generateQrCode($data, $id, $saveOptions);

  }

  /**
   *
   */
  public static function droppingCenterTabset($tabsetName, &$tabs, $context) {
    if (!self::isViewingDroppingCenter($tabsetName, $context)) {
      return;
    }
    $tabConfigs = [
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

  /**
   *
   */
  public static function init() {
    $subtype = OptionValue::get(FALSE)
      ->addWhere('grouping', '=', self::ENTITY_NAME)
      ->addWhere('name', '=', self::ENTITY_SUBTYPE_NAME)
      ->execute()->single();
    self::$subtypeId = (int) $subtype['value'];

  }

}
