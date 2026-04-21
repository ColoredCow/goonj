<?php

/**
 * @file
 * Cron to assign the "Goonj It" campaign to contributions that have no
 * campaign set, for all contributions received on or after 2026-04-01.
 */

use Civi\Api4\Campaign;
use Civi\Api4\Contribution;

/**
 * API spec (optional).
 */
function _civicrm_api3_goonjcustom_assign_default_campaign_cron_spec(&$spec) {
}

/**
 * Assigns the "Goonj It" campaign to every contribution that:
 *   - has no campaign_id set, AND
 *   - was received on or after 2026-04-01.
 *
 * @param array $params
 *
 * @return array
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_goonjcustom_assign_default_campaign_cron($params) {
  $returnValues = [];

  $goonjItCampaign = Campaign::get(FALSE)
    ->addSelect('id')
    ->addWhere('name', '=', 'Goonj_It')
    ->execute()->first();

  if (empty($goonjItCampaign['id'])) {
    return civicrm_api3_create_error('Goonj It campaign not found.');
  }

  $goonjItCampaignId = $goonjItCampaign['id'];

  $contributions = Contribution::get(FALSE)
    ->addSelect('id')
    ->addWhere('campaign_id', 'IS NULL')
    ->addWhere('contribution_status_id:name', '=', 'Completed')
    ->addWhere('receive_date', '>=', '2026-04-01')
    ->addWhere('is_test', '=', 1)
    ->execute();

  foreach ($contributions as $contribution) {
    try {
      Contribution::update(FALSE)
        ->addValue('campaign_id', $goonjItCampaignId)
        ->addWhere('id', '=', $contribution['id'])
        ->execute();

      $returnValues[] = $contribution['id'];
    }
    catch (\Exception $e) {
      \Civi::log()->error('AssignDefaultCampaignCron: failed to update contribution ' . $contribution['id'], [
        'message' => $e->getMessage(),
      ]);
    }
  }

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'assign_default_campaign_cron');
}
