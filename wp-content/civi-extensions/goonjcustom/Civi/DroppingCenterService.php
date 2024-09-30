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

    $status = \CRM_Utils_System::url(
      "wp-admin/admin.php?page=CiviCRM&q=civicrm%2Fdropping_center-status",
    );

    $visitDetails = \CRM_Utils_System::url(
      "wp-admin/admin.php?page=CiviCRM&q=civicrm%2Fvisit-details%2Fcreate",
    );

    $donationTrackingUrl = \CRM_Utils_System::url(
      "wp-admin/admin.php?page=CiviCRM&q=civicrm%2Fdonation-box-list",
    );

    $logisticsCoordinationUrl = \CRM_Utils_System::url(
      "wp-admin/admin.php?page=CiviCRM&q=civicrm%2Fdropping-center%2Flogistics-coordination",
    );

    $outcome = \CRM_Utils_System::url(
      "wp-admin/admin.php?page=CiviCRM&q=civicrm%2Fdropping-center-outcome",
    );

    // Add the Status tab.
    $tabs['status'] = [
      'title' => ts('Status'),
      'link' => $status,
      'valid' => 1,
      'active' => 1,
      'current' => FALSE,
    ];

    // Add the Visit Details tab.
    $tabs['visit details'] = [
      'title' => ts('Visit Details'),
      'link' => $visitDetails,
      'valid' => 1,
      'active' => 1,
      'current' => FALSE,
    ];

    // Add the Donation Box/Register Tracking tab.
    $tabs['donation tracking'] = [
      'title' => ts('Donation Tracking'),
      'link' => $donationTrackingUrl,
      'valid' => 1,
      'active' => 1,
      'current' => FALSE,
    ];

    // Add the Logistics Coordination tab.
    $tabs['logistics coordination'] = [
      'title' => ts('Logistics Coordination'),
      'link' => $logisticsCoordinationUrl,
      'valid' => 1,
      'active' => 1,
      'current' => FALSE,
    ];

    // Add the outcome tab.
    $tabs['outcome'] = [
      'title' => ts('Outcome'),
      'link' => $outcome,
      'valid' => 1,
      'active' => 1,
      'current' => FALSE,
    ];
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
