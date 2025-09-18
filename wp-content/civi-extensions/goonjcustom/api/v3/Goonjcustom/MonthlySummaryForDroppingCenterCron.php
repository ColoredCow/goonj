<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\DroppingCenterService;

/**
 * Goonjcustom.MonthlySummaryForDroppingCenterCron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_monthly_summary_for_dropping_center_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.MonthlySummaryForDroppingCenterCron API.
 *
 * @param array $params
 *
 * @return array API result descriptor
 *
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_goonjcustom_monthly_summary_for_dropping_center_cron($params) {
  $returnValues = [];
  $today = new \DateTime();
  $lastDay = new \DateTime('last day of this month');

  // If ($today->format('Y-m-d') !== $lastDay->format('Y-m-d')) {
  //   \Civi::log()->info('MonthlySummaryForDroppingCenterCron skipped (not last day of month)');
  //   return civicrm_api3_create_success([], $params, 'Goonjcustom', 'monthly_summary_for_dropping_center_cron');
  // }.
  $droppingCenters = EckEntity::get('Collection_Camp', FALSE)
    ->addSelect('Collection_Camp_Core_Details.Contact_Id', 'Dropping_Centre.Goonj_Office.display_name')
    ->addWhere('subtype:name', '=', 'Dropping_Center')
    ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
    ->addWhere('created_date', '>=', '2025/9/1')
    ->setLimit(25)
    ->execute();

  foreach ($droppingCenters as $droppingCenter) {
    try {
      $droppingCenterId = $droppingCenter['id'];

      // 🔎 Check Dropping Center Meta statuses
      $droppingCenterMetas = EckEntity::get('Dropping_Center_Meta', FALSE)
        ->addSelect('Status.Status:label')
        ->addWhere('Dropping_Center_Meta.Dropping_Center', '=', $droppingCenterId)
        ->execute();

      // If any status = Permanently Closed → skip.
      $isPermanentlyClosed = FALSE;
      foreach ($droppingCenterMetas as $meta) {
        if ($meta['Status.Status:label'] === 'Permanently Closed') {
          $isPermanentlyClosed = TRUE;
          break;
        }
      }

      if ($isPermanentlyClosed) {
        \Civi::log()->info("Skipping dropping center (permanently closed): $droppingCenterId");
        continue;
      }

      DroppingCenterService::SendMonthlySummaryEmail($droppingCenter);
    }
    catch (\Exception $e) {
      \Civi::log()->info('Error while sending mail', [
        'id'    => $droppingCenter['id'],
        'error' => $e->getMessage(),
      ]);
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'monthly_summary_for_dropping_center_cron');
}
