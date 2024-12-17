<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Api4\Campaign;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class InstitutionCampaignService extends AutoSubscriber {

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => [
        ['linkCampaignToOrganization'],
      ],
    ];
  }

  /**
   *
   */
  public static function linkCampaignToOrganization(string $op, string $objectName, int $objectId, &$objectRef) {

    if ($objectName != 'Campaign' || !$objectId) {
      return;
    }

    $institutionCampaign = Campaign::get(TRUE)
      ->addSelect(
          'id',
          'Additional_Details.Institution',
          'title'
      )
      ->addWhere('id', '=', $objectId)
      ->execute();

    $currentInstitutionCampaign = $institutionCampaign->first();
    $currentInstitutionId = $currentInstitutionCampaign['Additional_Details.Institution'];
    if (!$currentInstitutionId) {
      return;
    }
    $campaignTitle = $currentInstitutionCampaign['title'];
    $campaignId = $currentInstitutionCampaign['id'];

    // Check for status change.
    if ($currentInstitutionId) {
      self::createCollectionCampOrganizeActivity($currentInstitutionId, $campaignTitle, $campaignId);
    }
  }

  /**
   * Log an activity in CiviCRM.
   */
  private static function createCollectionCampOrganizeActivity($currentInstitutionId, $campaignTitle, $campaignId) {
    try {
      $results = Activity::create(FALSE)
        ->addValue('subject', $campaignTitle)
        ->addValue('activity_type_id:name', 'Institution Campaign')
        ->addValue('status_id:name', 'Completed')
        ->addValue('activity_date_time', date('Y-m-d H:i:s'))
        ->addValue('source_contact_id', $currentInstitutionId)
        ->addValue('target_contact_id', $currentInstitutionId)
        ->execute();

    }
    catch (\CiviCRM_API4_Exception $ex) {
      \Civi::log()->debug("Exception while creating Organize Collection Camp activity: " . $ex->getMessage());
    }
  }

}
