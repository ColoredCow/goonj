<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;
use Civi\DroppingCenterService;

/**
 * Goonjcustom.MonthlySummaryForDroppingCenterCron API specification (optional).
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_monthly_summary_for_dropping_center_cron_spec(&$spec) {
  // No input params required.
}

/**
 * Goonjcustom.MonthlySummaryForDroppingCenterCron API.
 *
 * Sends monthly summary emails for all authorized Dropping Centers,
 * skipping those permanently closed. Runs in batches to avoid load.
 *
 * @param array $params
 *
 * @return array
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_goonjcustom_monthly_summary_for_dropping_center_cron($params) {
  $returnValues = [];

  $today   = new \DateTime();
  $lastDay = new \DateTime('last day of this month');

  // Run this last day of month only.
  // if ($today->format('Y-m-d') !== $lastDay->format('Y-m-d')) {
  //   \Civi::log()->info('MonthlySummaryForDroppingCenterCron skipped (not last day of month)');
  //   return civicrm_api3_create_success([], $params, 'Goonjcustom', 'monthly_summary_for_dropping_center_cron');
  // }

  $limit  = 20;
  $offset = 0;

  while (TRUE) {
    $droppingCentersResult = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('id', 'Collection_Camp_Core_Details.Contact_Id', 'Dropping_Centre.Goonj_Office.display_name', 'Dropping_Centre.Is_Monthly_Email_Sent')
      ->addWhere('subtype:name', '=', 'Dropping_Center')
      ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
      ->setLimit($limit)
      ->setOffset($offset)
      ->execute();

    $droppingCenters = $droppingCentersResult->getArrayCopy();

    if (count($droppingCenters) === 0) {
      break;
    }

    foreach ($droppingCenters as $droppingCenter) {
      try {
        $droppingCenterId = $droppingCenter['id'];
        $lastSentDate = $droppingCenter['Dropping_Centre.Is_Monthly_Email_Sent'] ?? NULL;

        $today = new \DateTime();
        $currentMonth = $today->format('Y-m');

        $alreadySentThisMonth = FALSE;
        if ($lastSentDate) {
          $lastSentMonth = (new \DateTime($lastSentDate))->format('Y-m');

          if ($lastSentMonth === $currentMonth) {
            $alreadySentThisMonth = TRUE;
          }
        }

        if ($alreadySentThisMonth) {
          \Civi::log()->info("Skipping Dropping Center $droppingCenterId: email already sent this month");
          continue;
        }

        // Send email.
        DroppingCenterService::SendMonthlySummaryEmail($droppingCenter);

        EckEntity::update('Collection_Camp', FALSE)
          ->addValue('Dropping_Centre.Is_Monthly_Email_Sent', $today->format('Y-m-d'))
          ->addWhere('id', '=', $droppingCenterId)
          ->execute();

        $returnValues[] = [
          'id' => $droppingCenterId,
          'status' => 'sent',
        ];
      }
      catch (\Exception $e) {
        \Civi::log()->info('Error while sending mail', [
          'id' => $droppingCenter['id'] ?? NULL,
          'error' => $e->getMessage(),
        ]);
        $returnValues[] = [
          'id' => $droppingCenter['id'] ?? NULL,
          'status' => 'error',
          'error' => $e->getMessage(),
        ];
      }
    }

    $offset += $limit;
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'monthly_summary_for_dropping_center_cron');
}
