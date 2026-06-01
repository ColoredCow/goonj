<?php

/**
 * Class for CiviRule Generic Campaign
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 26 May 2021
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */


class CRM_CivirulesConditions_Generic_Campaign {
  /**
   * Method to get campaign data with API3 or API4
   *
   * @param int $campaignId
   * @return array
   */
  public static function getCampaignData(int $campaignId) {
    $campaignData = [];
    if (!empty($campaignId)) {
      try {
        $campaignData = civicrm_api3('Campaign', 'getsingle', ['id' => $campaignId]);
      }
      catch (CRM_Core_Exception $ex) {
      }
    }
    return $campaignData;
  }

}
