<?php

/**
 * @file
 */

use Civi\Api4\EckEntity;

/**
 * Goonjcustom.MonthlySummaryForInstituteDroppingCenterCron API specification (optional).
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_monthly_summary_for_institute_dropping_center_cron_spec(&$spec) {
  // No input params required.
}

/**
 * Goonjcustom.MonthlySummaryForInstituteDroppingCenterCron API.
 *
 * Sends monthly summary emails for all authorized Institute Dropping Centers,
 * skipping those permanently closed. Runs in batches to avoid load.
 *
 * @param array $params
 *
 * @return array
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_goonjcustom_monthly_summary_for_institute_dropping_center_cron($params) {
  $returnValues = [];

  $today   = new \DateTime();
  $lastDay = new \DateTime('last day of this month');

  // Run this last day of month only.
  if ($today->format('Y-m-d') !== $lastDay->format('Y-m-d')) {
    \Civi::log()->info('MonthlySummaryForInstituteDroppingCenterCron skipped (not last day of month)');
    return civicrm_api3_create_success([], $params, 'Goonjcustom', 'monthly_summary_for_institute_dropping_center_cron');
  }

  $limit  = 20;
  $offset = 0;

  while (TRUE) {
    $instituteDroppingCentersResult = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('id', 'Institution_Collection_Camp_Intent.Institution_POC', 'Institution_Dropping_Center_Review.Goonj_Office', 'Institution_Dropping_Center_Intent.Is_Monthly_Institution_Email_Sent')
      ->addWhere('subtype:name', '=', 'Institution_Dropping_Center')
      ->addWhere('Collection_Camp_Core_Details.Status', '=', 'authorized')
      ->setLimit($limit)
      ->setOffset($offset)
      ->execute();

    $instituteDroppingCenters = $instituteDroppingCentersResult->getArrayCopy();

    if (count($instituteDroppingCenters) === 0) {
      break;
    }

    foreach ($instituteDroppingCenters as $droppingCenter) {
      try {
        $instituteDroppingCenterId = $droppingCenter['id'];
        $lastSentDate = $droppingCenter['Institution_Dropping_Center_Intent.Is_Monthly_Institution_Email_Sen'] ?? NULL;

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
          \Civi::log()->info("Skipping Institute Dropping Center $instituteDroppingCenterId: email already sent this month");
          continue;
        }

        // Send email.
        InstitutionDroppingCenterService::SendMonthlySummaryEmailToInstitute($droppingCenter);

        EckEntity::update('Collection_Camp', FALSE)
          ->addValue('Institution_Dropping_Center_Intent.Is_Monthly_Institution_Email_Sen', $today->format('Y-m-d'))
          ->addWhere('id', '=', $instituteDroppingCenterId)
          ->execute();

        $returnValues[] = [
          'id' => $instituteDroppingCenterId,
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

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'monthly_summary_for_institute_dropping_center_cron');
}
