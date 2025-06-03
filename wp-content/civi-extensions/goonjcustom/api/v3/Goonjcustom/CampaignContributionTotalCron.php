<?php

/**
 * @file
 */

use Civi\Api4\Campaign;
use Civi\Api4\Contribution;

/**
 * API spec (optional).
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

    try {
      $totalAmount = goonjcustom_get_total_contributions_by_campaign($campaignId);

      Campaign::update(FALSE)
        ->addWhere('id', '=', $campaignId)
        ->addValue('Additional_Details.Total_Contribution_Amount', $totalAmount)
        ->execute();

      $messages[] = "Campaign $campaignId updated with total contribution amount ₹$totalAmount";
    }
    catch (\Exception $e) {
      $messages[] = "Failed to update campaign $campaignId: " . $e->getMessage();
    }
  }

  return civicrm_api3_create_success([
    'messages' => $messages,
  ], $params, 'Goonjcustom', 'campaign_contribution_total_cron');
}

/**
 * Helper function to calculate total contribution amount for a campaign.
 *
 * @param int $campaignId
 *
 * @return float
 *
 * @throws \CRM_Core_Exception
 */
function goonjcustom_get_total_contributions_by_campaign($campaignId) {
  $contributionsResult = Contribution::get(FALSE)
    ->addSelect('total_amount')
    ->addWhere('campaign_id', '=', $campaignId)
    ->execute();

  $contributions = $contributionsResult->getArrayCopy();

  return array_sum(array_column($contributions, 'total_amount'));
}
