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
use Civi\Api4\Relationship;

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
      ['generateMaterialReceiptForInstitution'],
      ],

    ];
  }

  /**
   * Generate a PDF receipt for organization contributions when a specific form is submitted.
   */
  public static function generateMaterialReceiptForInstitution(string $op, string $objectName, int $objectId, &$objectRef) {
    if (
      $op !== 'create' ||
      $objectName !== 'AfformSubmission' ||
      empty($objectRef->afform_name) ||
      (
          $objectRef->afform_name !== 'afformInstitutionReceiptGeneration'
      )
    ) {
      return;
    }
    $data = json_decode($objectRef->data, TRUE);
    $activityId = $data['Activity1'][0]['fields']['id'] ?? NULL;

    $activities = Activity::get(FALSE)
      ->addSelect('Institution_Material_Contribution.Contribution_Date', 'campaign_id', 'Institution_Material_Contribution.Description_of_Material_No_of_Bags_Material_', 'Institution_Material_Contribution.Delivered_By_Name', 'Institution_Material_Contribution.Delivered_By_Contact_New', 'source_contact_id', 'Institution_Material_Contribution.Institution_POC')
      ->addWhere('id', '=', $activityId)
      ->execute()->first();

    $activityDate = $activities['Institution_Material_Contribution.Contribution_Date'];
    $campaignId = $activities['campaign_id'] ?? NULL;
    $description = $activities['Institution_Material_Contribution.Description_of_Material_No_of_Bags_Material_'] ?? '';
    $deliveredBy = $activities['Institution_Material_Contribution.Delivered_By_Name'] ?? '';
    $deliveredByContact = $activities['Institution_Material_Contribution.Delivered_By_Contact_New'] ?? '';
    $organizationId = $activities['source_contact_id'];
    $institutionPOCId = $activities['Institution_Material_Contribution.Institution_POC'];

    $organizations = Organization::get(FALSE)
      ->addSelect('address_primary.street_address', 'display_name')
      ->addWhere('id', '=', $organizationId)
      ->execute()->first();

    $organizationName = $organizations['display_name'] ?? '';
    $organizationAddress = $organizations['address_primary.street_address'] ?? '';

    $activities = Activity::get(FALSE)
      ->addSelect('*')
      ->addJoin('Organization AS organization', 'LEFT')
      ->addWhere('activity_type_id:name', '=', 'Institution Material Contribution')
      ->addWhere('organization.id', '=', $organizationId)
      ->addOrderBy('created_date', 'DESC')
      ->setLimit(1)
      ->execute();

    $contribution = $activities->first() ?? [];

    // Determine target contact with fallback logic.
    if (!empty($institutionPOCId)) {
      $targetContactId = $institutionPOCId;
    }
    else {
      $initiatorId = NULL;

      // First, check for Primary Institution POC relationship.
      $primaryRelationships = Relationship::get(FALSE)
        ->addWhere('contact_id_a', '=', $organizationId)
        ->addWhere('relationship_type_id:name', '=', 'Primary Institution POC of')
        ->addWhere('is_active', '=', TRUE)
        ->execute();

      if ($primaryRelationships) {
        $firstPrimaryRelationship = $primaryRelationships->first();
        $initiatorId = $firstPrimaryRelationship['contact_id_b'] ?? NULL;
      }

      // If Primary not found, check for Institution POC of relationship.
      if (!$initiatorId) {
        $pocRelationships = Relationship::get(FALSE)
          ->addWhere('contact_id_a', '=', $organizationId)
          ->addWhere('relationship_type_id:name', '=', 'Institution POC of')
          ->addWhere('is_active', '=', TRUE)
          ->execute();

        if ($pocRelationships) {
          $firstPocRelationship = $pocRelationships->first();
          $initiatorId = $firstPocRelationship['contact_id_b'] ?? NULL;
        }
      }

      $targetContactId = $initiatorId ?? $organizationId;
    }

    $contact = self::getContactDetails($targetContactId);

    if (!$contact || empty($contact['email'])) {
      return;
    }
    $email = $contact['email'];
    $name = $contact['name'];
    $phone = $contact['phone'];

    QrCodeable::generatePdfForEvent($organizationName, $organizationAddress, $contribution, $email, $phone, $description, $name, $deliveredBy, $deliveredByContact, $activityDate, $activityId);
  }

  /**
   *
   */
  public static function getContactDetails($contactId) {
    $contact = Contact::get(TRUE)
      ->addSelect('email_primary.email', 'phone_primary.phone', 'display_name')
      ->addWhere('id', '=', $contactId)
      ->execute()
      ->first();

    return (!empty($contact)) ? [
      'email' => $contact['email_primary.email'] ?? '',
      'phone' => $contact['phone_primary.phone'] ?? '',
      'name' => $contact['display_name'] ?? '',
    ] : NULL;
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
      ->addSelect('email_primary.email', 'phone_primary.phone', 'address_primary.street_address')
      ->addWhere('id', '=', $contactId)
      ->execute()->single();

    $email = $contactData['email_primary.email'] ?? 'N/A';
    $phone = $contactData['phone_primary.phone'] ?? 'N/A';
    $contributorAddress = $contactData['address_primary.street_address'] ?? 'N/A';

    $entityId = $contribution['id'];

    QrCodeable::generatePdfForCollectionCamp($entityId, $contribution, $email, $phone, $contributionVenue, $contributionDate, $subtype, $eventId, $puId, $contributorAddress);

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
