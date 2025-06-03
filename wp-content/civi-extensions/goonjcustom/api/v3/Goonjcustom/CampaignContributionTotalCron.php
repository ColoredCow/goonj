<?php

/**
 * @file
 */

use Civi\Api4\Campaign;
use Civi\Api4\Contribution;

/**
 * API specification (optional)
 *
 * @param array $spec
 */
function _civicrm_api3_goonjcustom_campaign_contribution_total_cron_spec(&$spec) {
}

/**
 * Custom API to calculate and update total contribution amount per campaign.
 *
 * @param array $params
 *
 * @return array
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_goonjcustom_campaign_contribution_total_cron($params) {
  $messages = [];

  $campaigns = Campaign::get(FALSE)
    ->addSelect('id')
    ->execute();

  foreach ($campaigns as $campaign) {
    $campaignId = $campaign['id'];

    $contributions = Contribution::get(FALSE)
      ->addSelect('total_amount')
      ->addWhere('campaign_id', '=', $campaignId)
      ->execute();

    $totalAmount = 0;
    foreach ($contributions as $contribution) {
      $totalAmount += $contribution['total_amount'];
    }

    Campaign::update(FALSE)
      ->addWhere('id', '=', $campaignId)
      ->addValue('Additional_Details.Total_Contribution_Amount', $totalAmount)
      ->execute();

    $messages[] = "Campaign $campaignId updated with total contribution amount ₹$totalAmount";
  }

  return civicrm_api3_create_success([
    'messages' => $messages,
  ], $params, 'Goonjcustom', 'campaign_contribution_total_cron');
}
