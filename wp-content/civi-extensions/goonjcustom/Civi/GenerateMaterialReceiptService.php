<?php

namespace Civi;

require_once __DIR__ . '/../../../../wp-content/civi-extensions/goonjcustom/vendor/autoload.php';

use Civi\Api4\Activity;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\QrCodeable;

/**
 *
 */
class GenerateMaterialReceiptService extends AutoSubscriber {
  use QrCodeable;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => [
      ['generateMaterialReceipt'],
      ],

    ];
  }

  /**
   *
   */
  public static function generateMaterialReceipt(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'AfformSubmission' || empty($objectRef->afform_name) || $objectRef->afform_name !== 'afformSendReminderToCollectionCampMaterialContributions') {
      error_log("Conditions not met for generating PDF.");
      return;
    }
    $data = json_decode($objectRef->data, TRUE);

    $activityData = $data['Eck_Collection_Camp1'][0]['fields'] ?? [];

    $campId = $data['Eck_Collection_Camp1'][0]['fields']['id'] ?? NULL;
    $activityId = $data['Activity1'][0]['fields']['id'] ?? NULL;

    error_log("campId: " . print_r($campId, TRUE));
    error_log("activityId: " . print_r($activityId, TRUE));

    $activities = Activity::get(TRUE)
      ->addSelect('*', 'contact.display_name', 'Material_Contribution.Delivered_By', 'Material_Contribution.Delivered_By_Contact', 'Material_Contribution.Goonj_Office', 'Material_Contribution.Collection_Camp.subtype:name', 'Material_Contribution.Institution_Collection_Camp.subtype:name', 'Material_Contribution.Dropping_Center.subtype:name', 'Material_Contribution.Institution_Dropping_Center.subtype:name', 'Material_Contribution.Contribution_Date')
      ->addWhere('Material_Contribution.Collection_Camp', '=', $campId)
      ->addWhere('id', '=', $activityId)
      ->execute();

    $contribution = $activities->first();
    $descriptionOfMaterial = $contribution['subject'] ?? NULL;
    $contributionDate = $contribution['Material_Contribution.Contribution_Date'];
    $activityDate = date("F j, Y", strtotime($activity['activity_date_time']));
    // $receivedOnDate = !empty($contributionDate)
    // ? date("F j, Y", strtotime($contributionDate))
    // : $activityDate;


    $entityId = $contribution['id'];
    error_log("entityId: " . print_r($entityId, TRUE));

    self::generatePdfForCollectionCamp($entityId);

  }

}
