
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
  $returnValues = [];

  $campaigns = Campaign::get(FALSE)
    ->addSelect('id')
    ->execute();

  foreach ($campaigns as $campaign) {
    $campaignId = $campaign['id'];

    try {
      $data = goonjcustom_get_total_contributions_by_campaign($campaignId);
      $totalAmount = $data['totalAmount'];
      $contributorCount = $data['contributorCount'];

      Campaign::update(FALSE)
        ->addWhere('id', '=', $campaignId)
        ->addValue('Contribution_Data.Total_Contribution_Amount', $totalAmount)
        ->addValue('Contribution_Data.Total_Number_of_Contributors', $contributorCount)
        ->execute();

    }
    catch (\Exception $e) {
      // Do nothing.
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'campaign_contribution_total_cron');

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
  $contributions = Contribution::get(FALSE)
    ->addSelect('total_amount')
    ->addWhere('campaign_id', '=', $campaignId)
    ->execute();

  if (empty($contributions)) {
    return;
  }

  $totalAmount = 0;

  foreach ($contributions as $contribution) {
    $totalAmount += $contribution['total_amount'];
  }

  return [
    'totalAmount' => $totalAmount,
    'contributorCount' => count($contributions),
  ];
}
