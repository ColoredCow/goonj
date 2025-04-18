<?php

namespace Civi;

require_once __DIR__ . '/../../../../wp-content/civi-extensions/goonjcustom/vendor/autoload.php';

use Civi\Api4\Address;
use Civi\Api4\Event;
use Civi\Api4\Activity;
use Civi\Api4\EckEntity;
use Civi\Api4\Contact;
use Civi\Api4\Organization;
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
   * Generate a PDF receipt for material contributions when a specific form is submitted.
   *
   * @param string $op
   *   The operation being performed (create, edit, delete).
   * @param string $objectName
   *   The entity type being operated on.
   * @param int $objectId
   *   The ID of the entity being operated on.
   * @param object $objectRef
   *   Reference to the entity being operated on.
   */
  public static function generateMaterialReceipt(string $op, string $objectName, int $objectId, &$objectRef) {
    if (
      $op !== 'create' ||
      $objectName !== 'AfformSubmission' ||
      empty($objectRef->afform_name) ||
      (
          $objectRef->afform_name !== 'afformSendReminderToCollectionCampMaterialContributions' &&
          $objectRef->afform_name !== 'afformSendReminderToGoonjOffice'
      )
    ) {
      return;
    }

    $data = json_decode($objectRef->data, TRUE);

    $campId = $data['Eck_Collection_Camp1'][0]['fields']['id'] ?? NULL;
    $puId = $data['Activity1'][0]['fields']['Material_Contribution.Goonj_Office'] ?? NULL;
    $activityId = $data['Activity1'][0]['fields']['id'] ?? NULL;
    $eventId = $data['Activity1'][0]['fields']['Material_Contribution.Event'] ?? NULL;

    if ($campId) {
      $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
        ->addSelect('subtype:name')
        ->addWhere('id', '=', $campId)
        ->execute()->first();

      $subtype = $collectionCamp['subtype:name'];

      if ($subtype == 'Collection_Camp') {
        $contributionName = 'Material_Contribution.Collection_Camp';
      }
      elseif ($subtype == 'Dropping_Center') {
        $contributionName = 'Material_Contribution.Dropping_Center';
      }
      elseif ($subtype == 'Institution_Collection_Camp') {
        $contributionName = 'Material_Contribution.Institution_Collection_Camp';
      }
      elseif ($subtype == 'Institution_Dropping_Center') {
        $contributionName = 'Material_Contribution.Institution_Dropping_Center';
      }

      $activities = Activity::get(FALSE)
        ->addSelect('*', 'contact.display_name', 'Material_Contribution.Delivered_By', 'Material_Contribution.Delivered_By_Contact', 'Material_Contribution.Goonj_Office', 'Material_Contribution.Collection_Camp.subtype:name', 'Material_Contribution.Institution_Collection_Camp.subtype:name', 'Material_Contribution.Dropping_Center.subtype:name', 'Material_Contribution.Institution_Dropping_Center.subtype:name', 'Material_Contribution.Contribution_Date', 'source_contact_id', 'activity_date_time', 'subject')
        ->addWhere($contributionName, '=', $campId)
        ->addWhere('id', '=', $activityId)
        ->addJoin('ActivityContact AS activity_contact', 'LEFT')
        ->addJoin('Contact AS contact', 'LEFT')
        ->execute();
    }
    elseif ($puId) {

      $activities = Activity::get(FALSE)
        ->addSelect('*', 'contact.display_name', 'Material_Contribution.Delivered_By', 'Material_Contribution.Delivered_By_Contact', 'Material_Contribution.Goonj_Office', 'Material_Contribution.Collection_Camp.subtype:name', 'Material_Contribution.Institution_Collection_Camp.subtype:name', 'Material_Contribution.Dropping_Center.subtype:name', 'Material_Contribution.Institution_Dropping_Center.subtype:name', 'Material_Contribution.Contribution_Date', 'source_contact_id', 'activity_date_time', 'subject')
        ->addWhere('Material_Contribution.Goonj_Office', '=', $puId)
        ->addWhere('id', '=', $activityId)
        ->addJoin('ActivityContact AS activity_contact', 'LEFT')
        ->addJoin('Contact AS contact', 'LEFT')
        ->execute();
    }
    elseif ($eventId) {

      $activities = Activity::get(FALSE)
        ->addSelect('*', 'contact.display_name', 'Material_Contribution.Delivered_By', 'Material_Contribution.Delivered_By_Contact', 'Material_Contribution.Goonj_Office', 'Material_Contribution.Collection_Camp.subtype:name', 'Material_Contribution.Institution_Collection_Camp.subtype:name', 'Material_Contribution.Dropping_Center.subtype:name', 'Material_Contribution.Institution_Dropping_Center.subtype:name', 'Material_Contribution.Contribution_Date', 'source_contact_id', 'activity_date_time', 'subject', 'Material_Contribution.Event')
        ->addWhere('Material_Contribution.Event', '=', $eventId)
        ->addWhere('id', '=', $activityId)
        ->addJoin('ActivityContact AS activity_contact', 'LEFT')
        ->addJoin('Contact AS contact', 'LEFT')
        ->execute();
    }

    $contribution = $activities->first();

    $contributionDate = $contribution['Material_Contribution.Contribution_Date'];

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

    $contactId = $contribution['source_contact_id'];

    $contactData = Contact::get(FALSE)
      ->addSelect('email_primary.email', 'phone_primary.phone')
      ->addWhere('id', '=', $contactId)
      ->execute()->single();

    $email = $contactData['email_primary.email'] ?? 'N/A';
    $phone = $contactData['phone_primary.phone'] ?? 'N/A';

    $entityId = $contribution['id'];

    QrCodeable::generatePdfForCollectionCamp($entityId, $contribution, $email, $phone, $contributionVenue, $contributionDate);

  }

  /**
   * Get the contribution venue city/address based on contribution data and subtype.
   *
   * @param array $contribution
   *   The contribution data array.
   * @param string $subtype
   *   The subtype of the contribution.
   *
   * @return string
   *   The formatted address or empty string if not found.
   */
  private static function getContributionCity($contribution, $subtype) {
    $officeId = $contribution['Material_Contribution.Goonj_Office'];
    $eventId = $contribution['Material_Contribution.Event'];

    if ($officeId) {
      try {
        $organization = Organization::get(FALSE)
          ->addSelect('address_primary.street_address')
          ->addWhere('id', '=', $officeId)
          ->execute()->single();
        return $organization['address_primary.street_address'] ?? '';
      }
      catch (\Exception $e) {
        error_log("Error fetching organization address: " . $e->getMessage());
      }
    }

    if ($eventId) {
      try {
        $events = Event::get(FALSE)
          ->addSelect('loc_block_id.address_id')
          ->addJoin('LocBlock AS loc_block', 'LEFT')
          ->addWhere('id', '=', $eventId)
          ->execute()->first();

        $addressId = $events['loc_block_id.address_id'];

        $addresses = Address::get(FALSE)
          ->addSelect('street_address')
          ->addWhere('id', '=', $addressId)
          ->execute()->first();

        $streetAddress = $addresses['street_address'];

        return $streetAddress ?? '';
      }
      catch (\Exception $e) {
        error_log("Error fetching organization address: " . $e->getMessage());
      }
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
