<?php

namespace Civi;


use Civi\Afform\Event\AfformSubmitEvent;
use Civi\Api4\Activity;
use Civi\Api4\Address;
use Civi\Api4\Contact;
use Civi\Api4\Contribution;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Email;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Api4\OptionValue;
use Civi\Api4\Campaign;
use Civi\Api4\Utils\CoreUtil;
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
        ['linkCollectionCampToContact'],
      ],
    ];
}

    public static function linkCollectionCampToContact(string $op, string $objectName, int $objectId, &$objectRef) {

        error_log("objectName " . print_r($objectName, TRUE));
        error_log("objectId " . print_r($objectId, TRUE));
        error_log("objectRef " . print_r($objectRef, TRUE));
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

        error_log("institutionCampaign " . print_r($institutionCampaign, TRUE));

        $currentInstitutionCampaign = $institutionCampaign->first();
        $currentInstitutionId= $currentInstitutionCampaign['Additional_Details.Institution'];
        if (!$currentInstitutionId) {
          return;
        }
        $campaignTitle = $currentInstitutionCampaign['title'];
        error_log("campaignTitle " . print_r($campaignTitle, TRUE));
        $campaignId = $currentInstitutionCampaign['id'];
        error_log("campaignId " . print_r($campaignId, TRUE));
    
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
            ->addValue('source_record_id', $currentInstitutionId)
            ->addValue('target_record_id', $currentInstitutionId)
            ->execute();
    
        }
        catch (\CiviCRM_API4_Exception $ex) {
          \Civi::log()->debug("Exception while creating Organize Collection Camp activity: " . $ex->getMessage());
        }
}

}