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
      ->addSelect('Material_Contribution.Collection_Camp', 'Institution_Material_Contribution.Description_of_Material_No_of_Bags_Material_', 'subject', 'Material_Contribution.Delivered_By', 'id')
      ->addWhere('Material_Contribution.Collection_Camp', '=', $campId)
      ->addWhere('id', '=', $activityId)
      ->execute()->first();

    error_log("activities: " . print_r($activities, TRUE));

    $entityId = $activities['id'];
    error_log("entityId: " . print_r($entityId, TRUE));

    self::generatePdfForCollectionCamp($entityId);

  }

}
