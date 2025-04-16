<?php

namespace Civi;

require_once __DIR__ . '/../../../../wp-content/civi-extensions/goonjcustom/vendor/autoload.php';

use Civi\Api4\Activity;
use Civi\Api4\EckEntity;

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
    $activityDate = date("F j, Y", strtotime($contribution['activity_date_time']));

    $receivedOnDate = !empty($contributionDate)
    ? date("F j, Y", strtotime($contributionDate))
    : $activityDate;

    $from = $contribution['contact.display_name'];

    $subtype = NULL;
    if (!empty($contribution['Material_Contribution.Collection_Camp.subtype:name'])) {
      $subtype = $contribution['Material_Contribution.Collection_Camp.subtype:name'];
    }
    elseif (!empty($contribution['Material_Contribution.Institution_Collection_Camp.subtype:name'])) {
      $subtype = $contribution['Material_Contribution.Institution_Collection_Camp.subtype:name'];
    }
    elseif (!empty($contribution['Material_Contribution.Dropping_Center.subtype:name'])) {
      $subtype = $contribution['Material_Contribution.Dropping_Center.subtype:name'];
    }
    elseif (!empty($contribution['Material_Contribution.Institution_Dropping_Center.subtype:name'])) {
      $subtype = $contribution['Material_Contribution.Institution_Dropping_Center.subtype:name'];
    }

    $contributionVenue = self::getContributionCity($contribution, $subtype);

    $email = $contactData['email_primary.email'] ?? 'N/A';
    $phone = $contactData['phone_primary.phone'] ?? 'N/A';

    $deliveredBy = empty($contribution['Material_Contribution.Delivered_By']) ? $contribution['contact.display_name'] : $contribution['Material_Contribution.Delivered_By'];

    $deliveredByContact = empty($contribution['Material_Contribution.Delivered_By_Contact']) ? $phone : $contribution['Material_Contribution.Delivered_By_Contact'];

    $entityId = $contribution['id'];
    error_log("entityId: " . print_r($entityId, TRUE));

    // self::generatePdfForCollectionCamp($entityId, $descriptionOfMaterial, $receivedOnDate, $contributionVenue, $email, $phone, $deliveredBy, $deliveredByContact );.
    QrCodeable::generatePdfForCollectionCamp($entityId, $contribution, $email, $phone, $contributionVenue, $contributionDate);

  }

  /**
   *
   */
  private static function getContributionCity($contribution, $subtype) {
    $officeId = $contribution['Material_Contribution.Goonj_Office'];

    if ($officeId) {
      $organization = Organization::get(FALSE)
        ->addSelect('address_primary.street_address')
        ->addWhere('id', '=', $officeId)
        ->execute()->single();
      return $organization['address_primary.street_address'] ?? '';
    }

    $campFieldMapping = [
      'Collection_Camp' => 'Material_Contribution.Collection_Camp',
      'Dropping_Center' => 'Material_Contribution.Dropping_Center',
      'Institution_Collection_Camp' => 'Material_Contribution.Institution_Collection_Camp',
      'Institution_Dropping_Center' => 'Material_Contribution.Institution_Dropping_Center',
    ];

    $campField = $campFieldMapping[$subtype] ?? NULL;
    if (empty($campField)) {
      return;
    }

    $activity = Activity::get(FALSE)
      ->addSelect($campField)
      ->addWhere('id', '=', $contribution['id'])
      ->execute()->single();

    if (empty($activity[$campField])) {
      return '';
    }

    $addressField = ($subtype == 'Collection_Camp')

    ? 'Collection_Camp_Intent_Details.Location_Area_of_camp'
    : (($subtype == 'Dropping_Center')
        ? 'Dropping_Centre.Where_do_you_wish_to_open_dropping_center_Address_'
        : (($subtype == 'Institution_Collection_Camp')
            ? 'Institution_Collection_Camp_Intent.Collection_Camp_Address'
            : (($subtype == 'Institution_Dropping_Center')
                ? 'Institution_Dropping_Center_Intent.Dropping_Center_Address'
                : NULL)));

    $collectionCamp = EckEntity::get('Collection_Camp', TRUE)
      ->addSelect($addressField)
      ->addWhere('id', '=', $activity[$campField])
      ->execute()->single();

    return $collectionCamp[$addressField] ?? '';
  }

}
