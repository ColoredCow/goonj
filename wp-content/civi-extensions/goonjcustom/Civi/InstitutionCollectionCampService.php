<?php

namespace Civi;

use Civi\Afform\Event\AfformSubmitEvent;
use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\Contribution;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Email;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Api4\OptionValue;
use Civi\Api4\Organization;
use Civi\Api4\Relationship;
use Civi\Api4\Utils\CoreUtil;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\CollectionSource;
use Civi\Traits\QrCodeable;

/**
 *
 */
class InstitutionCollectionCampService extends AutoSubscriber {
  use QrCodeable;
  use CollectionSource;
  const ENTITY_SUBTYPE_NAME = 'Institution_Collection_Camp';
  const ENTITY_NAME = 'Collection_Camp';
  const FALLBACK_OFFICE_NAME = 'Delhi';
  const MATERIAL_RELATIONSHIP_TYPE_NAME = 'Material Management Team of';
  const INSTITUTION_COLLECTION_CAMP_INTENT_FB_NAMES = [
    'afformInstitutionCollectionCampIntent',
    'afformInstitutionCollectionCampIntentBackend',
  ];

  private static $collectionCampAddress = NULL;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_pre' => [
        ['assignChapterGroupToIndividual'],
        ['generateInstitutionCollectionCampQr'],
        ['linkInstitutionCollectionCampToContact'],
        ['updateInstitutionCampStatusAfterAuth'],
        ['updateCampaignType'],
      ],
      'civi.afform.submit' => [
        ['setInstitutionCollectionCampAddress', 9],
        ['setInstitutionEventVolunteersAddress', 8],
      ],
      '&hook_civicrm_post' => [
        ['updateNameOfTheInstitution'],
        ['updateCampStatusOnOutcomeFilled'],
      ],
      '&hook_civicrm_custom' => [
        ['setOfficeDetails'],
        ['mailNotificationToMmt'],
      ],
      '&hook_civicrm_tabset' => 'institutionCollectionCampTabset',
    ];
  }

  /**
   *
   */
  public static function updateCampStatusOnOutcomeFilled(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($objectName !== 'AfformSubmission') {
      return;
    }

    $afformName = $objectRef->afform_name;

    if ($afformName !== 'afformInstitutionCampOutcomeForm') {
      return;
    }

    $jsonData = $objectRef->data;
    $dataArray = json_decode($jsonData, TRUE);

    $collectionCampId = $dataArray['Eck_Collection_Camp1'][0]['fields']['id'];

    if (!$collectionCampId) {
      return;
    }

    try {
      EckEntity::update('Collection_Camp', FALSE)
        ->addWhere('id', '=', $collectionCampId)
        ->addValue('Institution_collection_camp_Review.Camp_Status', '3')
        ->execute();

    }
    catch (\Exception $e) {
      \Civi::log()->error("Exception occurred while updating camp status for campId: $collectionCampId. Error: " . $e->getMessage());
    }
  }

  /**
   *
   */
  public static function updateCampaignType(string $op, string $objectName, $objectId, &$objectRef) {
    if ($op !== 'edit' || $objectName !== 'AfformSubmission') {
      return FALSE;
    }

    $fields = $objectRef['data']['Eck_Collection_Camp1'][0]['fields'] ?? NULL;
    $fieldValue = $fields['Institution_Collection_Camp_Intent.Will_your_collection_drive_be_open_for_general_public_as_well'] ?? NULL;
    $id = $fields['id'] ?? NULL;

    if (!$fieldValue || !$id) {
      return FALSE;
    }

    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Institution_collection_camp_Review.Is_the_camp_IHC_PCC_', $fieldValue)
      ->addWhere('id', '=', $id)
      ->execute();

  }

  /**
   *
   */
  public static function setInstitutionCollectionCampAddress(AfformSubmitEvent $event) {
    $afform = $event->getAfform();
    $formName = $afform['name'];

    if (!in_array($formName, self::INSTITUTION_COLLECTION_CAMP_INTENT_FB_NAMES, TRUE)) {
      return;
    }

    $entityType = $event->getEntityType();

    if ($entityType !== 'Eck_Collection_Camp') {
      return;
    }

    $records = $event->records;

    foreach ($records as $record) {
      $fields = $record['fields'];

      self::$collectionCampAddress = [
        'location_type_id' => 3,
        'state_province_id' => $fields['Institution_Collection_Camp_Intent.State'],
      // India.
        'country_id' => 1101,
        'street_address' => $fields['Institution_Collection_Camp_Intent.Collection_Camp_Address'],
        'city' => $fields['Institution_Collection_Camp_Intent.District_City'],
        'postal_code' => $fields['Institution_Collection_Camp_Intent.Postal_Code'],
        'is_primary' => 1,
      ];
    }
  }

  /**
   *
   */
  public static function setInstitutionEventVolunteersAddress(AfformSubmitEvent $event) {
    $afform = $event->getAfform();
    $formName = $afform['name'];

    if (!in_array($formName, self::INSTITUTION_COLLECTION_CAMP_INTENT_FB_NAMES, TRUE)) {
      return;
    }

    $entityType = $event->getEntityType();

    if (!CoreUtil::isContact($entityType)) {
      return;
    }

    foreach ($event->records as $index => $contact) {
      if (empty($contact['fields'])) {
        continue;
      }
      if (self::$collectionCampAddress === NULL) {
        continue;
      }
      $event->records[$index]['joins']['Address'][] = self::$collectionCampAddress;
    }

  }

  /**
   *
   */
  public static function updateNameOfTheInstitution(string $op, string $objectName, int $objectId, &$objectRef) {
    if (!empty($objectRef->afform_name) && $objectRef->afform_name == "afformInstitutionCollectionCampIntentVerification") {

      $data = json_decode($objectRef->data, TRUE);

      if (!isset($data['Eck_Collection_Camp1'][0]['fields'])) {
        return;
      }

      $fields = $data['Eck_Collection_Camp1'][0]['fields'];

      if (isset($fields['Collection_Camp_Core_Details.Status']) && $fields['Collection_Camp_Core_Details.Status'] === 'authorized') {
        $id = $fields['id'];
      }
      else {
        return;
      }

      $organizationId = $data['Organization1'][0]['fields']['id'] ?? NULL;

      if (!$organizationId) {
        return;
      }

      $organizations = Organization::get(FALSE)
        ->addSelect('display_name')
        ->addWhere('id', '=', $organizationId)
        ->execute()
        ->single();

      if (!$organizations || !isset($organizations['display_name'])) {
        return;
      }

      $organizationName = $organizations['display_name'];

      EckEntity::update('Collection_Camp', FALSE)
        ->addValue('Institution_collection_camp_Review.Name_of_the_Institution', $organizationName)
        ->addWhere('id', '=', $id)
        ->execute();
    }
  }

  /**
   *
   */
  public static function updateInstitutionCampStatusAfterAuth(string $op, string $objectName, $objectId, &$objectRef) {
    $statusDetails = self::checkCampStatusAndIds($objectName, $objectId, $objectRef);

    if (!$statusDetails) {
      return;
    }

    $newStatus = $statusDetails['newStatus'];
    $currentStatus = $statusDetails['currentStatus'];

    if ($currentStatus !== $newStatus) {
      if ($newStatus === 'authorized') {
        $institutionCampId = $objectRef['id'] ?? NULL;
        if ($institutionCampId === NULL) {
          return;
        }

        $results = EckEntity::update('Collection_Camp', FALSE)
          ->addValue('Institution_collection_camp_Review.Camp_Status', 1)
          ->addWhere('id', '=', $institutionCampId)
          ->execute();
      }
    }
  }

  /**
   *
   */
  public static function linkInstitutionCollectionCampToContact(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName !== 'Eck_Collection_Camp' || !$objectId || !self::isCurrentSubtype($objectRef)) {
      return;
    }
    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';
    $organizationId = $objectRef['Institution_Collection_Camp_Intent.Organization_Name'];

    if (!$newStatus) {
      return;
    }

    $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Institution_Collection_Camp_Intent.Organization_Name', 'title', 'Institution_Collection_Camp_Intent.Institution_POC')
      ->addWhere('id', '=', $objectId)
      ->execute();

    $currentCollectionCamp = $collectionCamps->first();
    $currentStatus = $currentCollectionCamp['Collection_Camp_Core_Details.Status'];
    $PocId = $currentCollectionCamp['Institution_Collection_Camp_Intent.Institution_POC'];

    if (!$PocId && !$organizationId) {
      return;
    }

    $collectionCampTitle = $currentCollectionCamp['title'];
    $collectionCampId = $currentCollectionCamp['id'];

    if ($currentStatus !== $newStatus && $newStatus === 'authorized') {
      self::createCollectionCampOrganizeActivity($PocId, $organizationId, $collectionCampTitle, $collectionCampId);
    }
  }

  /**
   *
   */
  private static function createCollectionCampOrganizeActivity($PocId, $organizationId, $collectionCampTitle, $collectionCampId) {
    try {

      // Create activity for PocId.
      self::createActivity($PocId, $collectionCampTitle, $collectionCampId);

      // Create activity for organizationId, only if it's different from PocId.
      if ($organizationId !== $PocId) {
        self::createActivity($organizationId, $collectionCampTitle, $collectionCampId);
      }

    }
    catch (\CiviCRM_API4_Exception $ex) {
      \Civi::log()->debug("Exception while creating Organize Institution Collection Camp activity: " . $ex->getMessage());
    }
  }

  /**
   *
   */
  private static function createActivity($contactId, $collectionCampTitle, $collectionCampId) {
    Activity::create(FALSE)
      ->addValue('subject', $collectionCampTitle)
      ->addValue('activity_type_id:name', 'Organize Institution Collection Camp')
      ->addValue('status_id:name', 'Authorized')
      ->addValue('activity_date_time', date('Y-m-d H:i:s'))
      ->addValue('source_contact_id', $contactId)
      ->addValue('target_contact_id', $contactId)
      ->addValue('Collection_Camp_Data.Collection_Camp_ID', $collectionCampId)
      ->execute();

    \Civi::log()->info("Activity created for contact {$contactId} for Institution Collection Camp {$collectionCampTitle}");
  }

  /**
   *
   */
  private static function getChapterGroupForState($stateId) {
    $stateContactGroup = Group::get(FALSE)
      ->addSelect('id')
      ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
      ->addWhere('Chapter_Contact_Group.Contact_Catchment', 'CONTAINS', $stateId)
      ->execute()->first();

    if (!$stateContactGroup) {
      $stateContactGroup = Group::get(FALSE)
        ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
        ->addWhere('Chapter_Contact_Group.Fallback_Chapter', '=', 1)
        ->execute()->first();

    }

    return $stateContactGroup ? $stateContactGroup['id'] : NULL;
  }

  /**
   *
   */
  public static function assignChapterGroupToIndividual(string $op, string $objectName, $objectId, &$objectRef) {
    if ($op !== 'edit' || $objectName !== 'AfformSubmission') {
      return FALSE;
    }

    if (empty($objectRef['data']['Eck_Collection_Camp1']) || empty($objectRef['data']['Individual1'])) {
      return FALSE;
    }

    $individualData = $objectRef['data']['Individual1'];
    $collectionCampData = $objectRef['data']['Eck_Collection_Camp1'];

    foreach ($individualData as $individual) {
      $contactId = $individual['id'];
      foreach ($collectionCampData as $visit) {
        $fields = $visit['fields'] ?? [];
        $stateProvinceId = $fields['Institution_Collection_Camp_Intent.State'] ?? NULL;

        $groupId = self::getChapterGroupForState($stateProvinceId);

        if ($groupId && $contactId) {

          // Check if the contact is already in the group.
          $groupContacts = GroupContact::get(FALSE)
            ->addSelect('contact_id', 'group_id')
            ->addWhere('group_id.id', '=', $groupId)
            ->addWhere('contact_id', '=', $contactId)
            ->execute();

          // If the contact is already in the group.
          if ($groupContacts->count() > 0) {
            return;
          }

          GroupContact::create(FALSE)
            ->addValue('contact_id', $contactId)
            ->addValue('group_id', $groupId)
            ->addValue('status', 'Added')
            ->execute();

        }

        \Civi::log()->info("Contact ID $contactId has been added to Group ID $groupId.");
      }
    }

    return TRUE;
  }

  /**
   *
   */
  public static function generateInstitutionCollectionCampQr(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName !== 'Eck_Collection_Camp' || !$objectId || !self::isCurrentSubtype($objectRef)) {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';
    if (!$newStatus) {
      return;
    }

    $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_Core_Details.Contact_Id')
      ->addWhere('id', '=', $objectId)
      ->execute()->first();

    $currentStatus = $collectionCamp['Collection_Camp_Core_Details.Status'];
    $collectionCampId = $collectionCamp['id'];

    // Check for status change.
    if ($currentStatus !== $newStatus && $newStatus === 'authorized') {
      self::generateInstitutionCollectionCampQrCode($collectionCampId);
    }
  }

  /**
   *
   */
  private static function generateInstitutionCollectionCampQrCode($id) {
    $baseUrl = \CRM_Core_Config::singleton()->userFrameworkBaseURL;
    $data = "{$baseUrl}actions/institution-collection-camp/{$id}";

    $saveOptions = [
      'customGroupName' => 'Collection_Camp_QR_Code',
      'customFieldName' => 'QR_Code',
    ];

    self::generateQrCode($data, $id, $saveOptions);
  }

  /**
   *
   */
  private static function getOfficeId(array $array) {
    $filteredItems = array_filter($array, fn($item) => $item['entity_table'] === 'civicrm_eck_collection_source_vehicle_dispatch');

    if (empty($filteredItems)) {
      return FALSE;
    }

    $goonjOfficeId = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('custom_group_id:name', '=', 'Camp_Vehicle_Dispatch')
      ->addWhere('name', '=', 'To_which_PU_Center_material_is_being_sent')
      ->execute()
      ->first();

    if (!$goonjOfficeId) {
      return FALSE;
    }

    $goonjOfficeFieldId = $goonjOfficeId['id'];

    $goonjOfficeIndex = array_search(TRUE, array_map(fn($item) =>
        $item['entity_table'] === 'civicrm_eck_collection_source_vehicle_dispatch' &&
        $item['custom_field_id'] == $goonjOfficeFieldId,
        $filteredItems
    ));

    return $goonjOfficeIndex !== FALSE ? $filteredItems[$goonjOfficeIndex] : FALSE;
  }

  /**
   *
   */
  public static function mailNotificationToMmt($op, $groupID, $entityID, &$params) {
    if ($op !== 'create') {
      return;
    }
    if (!($goonjField = self::getOfficeId($params))) {
      return;
    }

    $goonjFieldId = $goonjField['value'];
    $vehicleDispatchId = $goonjField['entity_id'];

    $collectionSourceVehicleDispatch = EckEntity::get('Collection_Source_Vehicle_Dispatch', FALSE)
      ->addSelect('Camp_Vehicle_Dispatch.Institution_Collection_Camp', 'Camp_Institution_Data.Name_of_the_institution', 'Camp_Institution_Data.Address', 'Camp_Institution_Data.Email', 'Camp_Institution_Data.Contact_Number')
      ->addWhere('id', '=', $vehicleDispatchId)
      ->execute()->first();

    $collectionCampId = $collectionSourceVehicleDispatch['Camp_Vehicle_Dispatch.Institution_Collection_Camp'];
    $nameOfInstitution = $collectionSourceVehicleDispatch['Camp_Institution_Data.Name_of_the_institution'];
    $addressOfInstitution = $collectionSourceVehicleDispatch['Camp_Institution_Data.Address'];
    $pocEmail = $collectionSourceVehicleDispatch['Camp_Institution_Data.Email'];
    $pocContactNumber = $collectionSourceVehicleDispatch['Camp_Institution_Data.Contact_Number'];

    if (self::getEntitySubtypeName($collectionCampId) !== self::ENTITY_SUBTYPE_NAME) {
      return;
    }

    $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Institution_Collection_Camp_Intent.Collection_Camp_Address', 'title')
      ->addWhere('id', '=', $collectionCampId)
      ->execute()->single();

    if (!$collectionCamp) {
      return;
    }

    $campCode = $collectionCamp['title'];
    $campAddress = $collectionCamp['Institution_Collection_Camp_Intent.Collection_Camp_Address'];

    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $goonjFieldId)
      ->addWhere('relationship_type_id:name', '=', self::MATERIAL_RELATIONSHIP_TYPE_NAME)
      ->addWhere('is_current', '=', TRUE)
      ->execute()->first();

    $mmtId = $coordinators['contact_id_a'];

    if (empty($mmtId)) {
      return;
    }

    $email = Email::get(FALSE)
      ->addSelect('email')
      ->addWhere('contact_id', '=', $mmtId)
      ->execute()->single();

    $mmtEmail = $email['email'];

    $fromEmail = OptionValue::get(FALSE)
      ->addSelect('label')
      ->addWhere('option_group_id:name', '=', 'from_email_address')
      ->addWhere('is_default', '=', TRUE)
      ->execute()->single();

    // Email to material management team member.
    $mailParams = [
      'subject' => 'Material Acknowledgement for Camp: ' . $campCode . ' at ' . $campAddress,
      'from' => $fromEmail['label'],
      'toEmail' => $mmtEmail,
      'replyTo' => $fromEmail['label'],
      'html' => self::sendEmailToMmt($collectionCampId, $campCode, $campAddress, $vehicleDispatchId, $nameOfInstitution, $addressOfInstitution, $pocEmail, $pocContactNumber),
    ];
    \CRM_Utils_Mail::send($mailParams);

  }

  /**
   *
   */
  public static function sendEmailToMmt($collectionCampId, $campCode, $campAddress, $vehicleDispatchId) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $materialdispatchUrl = $homeUrl . 'institution-camp-acknowledgement-dispatch/#?Eck_Collection_Source_Vehicle_Dispatch1=' . $vehicleDispatchId
    . '&Camp_Vehicle_Dispatch.Institution_Collection_Camp=' . $collectionCampId
    . '&Eck_Collection_Camp1=' . $collectionCampId
    . '&id=' . $collectionCampId;

    $html = "
    <p>Dear MMT team,</p>
    <p>This is to inform you that a vehicle has been sent from camp <strong>$campCode</strong> at <strong>$campAddress</strong>.</p>
    <p>Kindly acknowledge the details by clicking on this form <a href=\"$materialdispatchUrl\"> Link </a> when it is received at the center.</p>
    <p>Warm regards,<br>Urban Relations Team</p>";

    return $html;
  }

  /**
   *
   */
  public static function sendLogisticsEmail($collectionCamp) {
    try {
      $campId = $collectionCamp['id'];
      $campCode = $collectionCamp['title'];
      $campOffice = $collectionCamp['Institution_collection_camp_Review.Goonj_Office'];
      $campAddress = $collectionCamp['Institution_Collection_Camp_Intent.Collection_Camp_Address'];
      $campAttendedById = $collectionCamp['Institution_Collection_Camp_Logistics.Camp_to_be_attended_by'];
      $logisticEmailSent = $collectionCamp['Institution_Collection_Camp_Logistics.Email_Sent'];
      $selfManagedBy = $collectionCamp['Institution_Collection_Camp_Logistics.Self_Managed_by_Institution'];
      $institutionPOCId = $collectionCamp['Institution_Collection_Camp_Intent.Institution_POC'];
      $coordinatingPOCId = $collectionCamp['Institution_collection_camp_Review.Coordinating_POC'];
      $organizationId = $collectionCamp['Institution_Collection_Camp_Intent.Organization_Name'];

      $startDate = new \DateTime($collectionCamp['Institution_Collection_Camp_Intent.Collections_will_start_on_Date_']);

      $contacts = Contact::get(FALSE)
        ->addSelect('email.email', 'phone.phone')
        ->addJoin('Email AS email', 'LEFT')
        ->addJoin('Phone AS phone', 'LEFT')
        ->addWhere('id', '=', $institutionPOCId)
        ->execute()->single();

      $pocEmail = $contacts['email.email'];
      $pocContactNumber = $contacts['phone.phone'];

      $coordinatingPocEmail = Contact::get(FALSE)
        ->addSelect('email.email', 'phone.phone')
        ->addJoin('Email AS email', 'LEFT')
        ->addJoin('Phone AS phone', 'LEFT')
        ->addWhere('id', '=', $coordinatingPOCId)
        ->execute()->single();

      $coordinatingPOCEmail = $coordinatingPocEmail['email.email'];
      $coordinatingPOCPhone = $coordinatingPocEmail['phone.phone'];

      $organization = Organization::get(TRUE)
        ->addSelect('Institute_Registration.Address', 'display_name')
        ->addWhere('id', '=', $organizationId)
        ->execute()->single();

      $nameOfInstitution = $organization['display_name'];
      $addressOfInstitution = $organization['Institute_Registration.Address'];

      $today = new \DateTimeImmutable();
      $endOfToday = $today->setTime(23, 59, 59);
      if (!$logisticEmailSent && $startDate <= $endOfToday) {
        if (!$selfManagedBy && $campAttendedById) {
          $recipient = Contact::get(FALSE)
            ->addSelect('email.email', 'display_name')
            ->addJoin('Email AS email', 'LEFT')
            ->addWhere('id', '=', $campAttendedById)
            ->execute()->single();

          $recipientEmail = $recipient['email.email'];
          $recipientName = $recipient['display_name'];

          if (!$recipientEmail) {
            \Civi::log()->info("Recipient email missing: $campId");
          }
          $from = HelperService::getDefaultFromEmail();
          $mailParams = [
            'subject' => 'Collection Camp Notification: ' . $campCode . ' at ' . $campAddress,
            'from' => $from,
            'toEmail' => $recipientEmail,
            'replyTo' => $from,
            'html' => self::getLogisticsEmailHtml($recipientName, $campId, $campAttendedById, $campOffice, $campCode, $campAddress, $pocEmail, $pocContactNumber, $nameOfInstitution, $addressOfInstitution),
          ];

          // Send logistics email.
          $emailSendResult = \CRM_Utils_Mail::send($mailParams);

          if ($emailSendResult) {
            \Civi::log()->info("Logistics email sent for collection camp: $campId");
            EckEntity::update('Collection_Camp', FALSE)
              ->addValue('Institution_Collection_Camp_Logistics.Email_Sent', 1)
              ->addWhere('id', '=', $campId)
              ->execute();
          }
        }
        else {
          $recipient = Contact::get(FALSE)
            ->addSelect('email.email', 'display_name')
            ->addJoin('Email AS email', 'LEFT')
            ->addWhere('id', '=', $institutionPOCId)
            ->execute()->single();

          $recipientEmail = $recipient['email.email'];
          $recipientName = $recipient['display_name'];

          if (!$recipientEmail) {
            \Civi::log()->info("Recipient email missing for institution POC: $campId");
            return;
          }

          $from = HelperService::getDefaultFromEmail();
          $mailParams = [
            'subject' => 'Dispatch Notification for Self Managed Camp: ' . $campCode,
            'from' => $from,
            'toEmail' => $recipientEmail,
            'replyTo' => $from,
            'cc' => $coordinatingPOCEmail,
            'html' => self::sendDispatchEmail($recipientName, $campId, $institutionPOCId, $campOffice, $campCode, $campAddress, $pocEmail, $pocContactNumber, $nameOfInstitution, $addressOfInstitution, $coordinatingPOCEmail, $coordinatingPOCPhone),
          ];

          $dispatchEmailSendResult = \CRM_Utils_Mail::send($mailParams);

          if ($dispatchEmailSendResult) {
            \Civi::log()->info("dispatch email sent for collection camp: $campId");
            EckEntity::update('Collection_Camp', FALSE)
              ->addValue('Institution_Collection_Camp_Logistics.Email_Sent', 1)
              ->addWhere('id', '=', $campId)
              ->execute();
          }

          $recipient = Contact::get(FALSE)
            ->addSelect('email.email', 'display_name')
            ->addJoin('Email AS email', 'LEFT')
            ->addWhere('id', '=', $coordinatingPOCId)
            ->execute()->single();

          $recipientEmail = $recipient['email.email'];
          $recipientName = $recipient['display_name'];

          if (!$recipientEmail) {
            \Civi::log()->info("Recipient email missing for coordinating POC': $campId");
            return;
          }

          $mailParams = [
            'subject' => 'Outcome Notification for Self Managed Camp: ' . $campCode,
            'from' => $from,
            'toEmail' => $recipientEmail,
            'replyTo' => $from,
            'html' => self::sendOutcomeEmail($recipientName, $campId, $coordinatingPOCId, $campCode, $campAddress),
          ];

          $outcomeEmailSendResult = \CRM_Utils_Mail::send($mailParams);
          if ($outcomeEmailSendResult) {
            \Civi::log()->info("dispatch email sent for collection camp: $campId");
            EckEntity::update('Collection_Camp', FALSE)
              ->addValue('Institution_Collection_Camp_Logistics.Email_Sent', 1)
              ->addWhere('id', '=', $campId)
              ->execute();
          }
        }
      }
    }
    catch (\Exception $e) {
      \Civi::log()->error("Error in sendLogisticsEmail for $campId: " . $e->getMessage());
    }
  }

  /**
   *
   */
  private static function sendDispatchEmail(
    $contactName,
    $collectionCampId,
    $institutionPOCId,
    $collectionCampGoonjOffice,
    $campCode,
    $campAddress,
    $pocEmail,
    $pocContactNumber,
    $nameOfInstitution,
    $addressOfInstitution,
    $coordinatingPOCEmail,
    $coordinatingPOCPhone,
  ) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();

    $campVehicleDispatchFormUrl = $homeUrl . 'institution-camp-vehicle-dispatch-form/#?Camp_Vehicle_Dispatch.Institution_Collection_Camp=' . $collectionCampId
    . '&Camp_Vehicle_Dispatch.Filled_by=' . $institutionPOCId
    . '&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent=' . $collectionCampGoonjOffice
    . '&Eck_Collection_Camp1=' . $collectionCampId
    . '&Camp_Institution_Data.Name_of_the_institution=' . $nameOfInstitution
    . '&Camp_Institution_Data.Address=' . $addressOfInstitution
    . '&Camp_Institution_Data.Email=' . $pocEmail
    . '&Camp_Institution_Data.Contact_Number=' . $pocContactNumber
    . '&Institution_Collection_Camp_Intent.Collection_Camp_Address=' . $campAddress;

    $html = "
    <p>Dear $contactName,</p>
    <p>Thank you for conducting a collection camp drive for Goonj and contributing to our efforts to create meaningful change. To ensure we receive accurate details about the materials collected and can promptly acknowledge them, we kindly request you to complete the Dispatch Form from the location venue once the vehicle is loaded and ready for dispatch to Goonj’s processing center.</p>
    <p>The Dispatch Form helps us track and process the materials efficiently, ensuring smooth handling and timely acknowledgment.</p>
    <p>Please use the link below to access and complete the form:<br>
    <a href=\"$campVehicleDispatchFormUrl\">Dispatch Form</a></p>
    <p>If you encounter any issues or need assistance while filling out the form, feel free to contact us at $coordinatingPOCEmail or $coordinatingPOCPhone.</p>
    <p>Thank you once again for your valuable support and cooperation. We truly appreciate your efforts in making a difference.</p>
    <p>Warm Regards,<br>
    Team Goonj</p>";

    return $html;
  }

  /**
   *
   */
  private static function sendOutcomeEmail($contactName, $collectionCampId, $coordinatingPOCId, $campCode, $campAddress) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();

    $campOutcomeFormUrl = $homeUrl . '/institution-camp-outcome-form/#?Eck_Collection_Camp1=' . $collectionCampId
        . '&Camp_Outcome.Filled_By=' . $coordinatingPOCId
        . '&Institution_Collection_Camp_Intent.Collection_Camp_Address=' . $campAddress;

    $html = "
    <p>Dear $contactName,</p>
    <p>Thank you for organizing the collection camp <strong>$campCode</strong> at <strong>$campAddress</strong>. Your efforts have been instrumental in driving positive change and supporting Goonj’s initiatives.</p>
    <p>To help us gather insights and feedback on the outcomes of the event, we request you to complete the <strong>Camp Outcome Form</strong> after the camp concludes:</p>
    <p><a href=\"$campOutcomeFormUrl\">Complete the Camp Outcome Form</a></p>
    <p>Your feedback is essential in helping us improve and streamline future campaigns.</p>
    <p>If you face any issues or need assistance, please feel free to contact your designated Goonj coordinator.</p>
    <p>Thank you once again for your valuable support.</p>
    <p>Warm Regards,<br>Urban Relations Team</p>";

    return $html;
  }

  /**
   * Generates the logistics email HTML content.
   */
  private static function getLogisticsEmailHtml($contactName, $collectionCampId, $campAttendedById, $collectionCampGoonjOffice, $campCode, $campAddress, $pocEmail, $pocContactNumber, $nameOfInstitution, $addressOfInstitution) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();

    $campVehicleDispatchFormUrl = $homeUrl
    . 'institution-camp-vehicle-dispatch-form-not-self-managed/#?Camp_Vehicle_Dispatch.Institution_Collection_Camp=' . $collectionCampId
    . '&Camp_Vehicle_Dispatch.Filled_by=' . $campAttendedById
    . '&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent=' . $collectionCampGoonjOffice
    . '&Eck_Collection_Camp1=' . $collectionCampId
    . '&Institution_Collection_Camp_Intent.Collection_Camp_Address=' . $campAddress;

    $campOutcomeFormUrl = $homeUrl . '/institution-camp-outcome-form/#?Eck_Collection_Camp1=' . $collectionCampId . '&Camp_Outcome.Filled_By=' . $campAttendedById . '&Institution_Collection_Camp_Intent.Collection_Camp_Address=' . $campAddress;

    $html = "
    <p>Dear $contactName,</p>
    <p>Thank you for attending the camp <strong>$campCode</strong> at <strong>$campAddress</strong>. There are two forms that require your attention during and after the camp:</p>
    <ol>
        <li><a href=\"$campVehicleDispatchFormUrl\">Dispatch Form</a><br>
        Please complete this form from the camp location once the vehicle is being loaded and ready for dispatch to Goonj's processing center.</li>
        <li><a href=\"$campOutcomeFormUrl\">Camp Outcome Form</a><br>
        This feedback form should be filled out after the camp/drive ends, once you have an overview of the event's outcomes.</li>
    </ol>
    <p>We appreciate your cooperation.</p>
    <p>Warm Regards,<br>Urban Relations Team</p>";

    return $html;
  }

  /**
   *
   */
  private static function findStateField(array $array) {
    $institutionCollectionCampStateField = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'state')
      ->addWhere('custom_group_id:name', '=', 'Institution_Collection_Camp_Intent')
      ->execute()
      ->first();

    if (!$institutionCollectionCampStateField) {
      return FALSE;
    }

    $stateFieldId = $institutionCollectionCampStateField['id'];

    foreach ($array as $item) {
      if (isset($item['entity_table']) && $item['entity_table'] === 'civicrm_eck_collection_camp' &&
          isset($item['custom_field_id']) && $item['custom_field_id'] === $stateFieldId) {
        return $item;
      }
    }

    return FALSE;
  }

  /**
   *
   */
  private static function getFallbackOffice() {
    $fallbackOffices = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('organization_name', 'CONTAINS', self::FALLBACK_OFFICE_NAME)
      ->execute();

    return $fallbackOffices->first();
  }

  /**
   *
   */
  public static function assignCoordinatorByRelationshipType($stateOfficeId, $registrationType, $collectionCampId) {
    // Define the mapping of registration categories to relationship type names.
    $relationshipTypeMap = [
      'Corporate' => 'Corporate Coordinator of',
      'School' => 'School Coordinator of',
      'College/University' => 'College Coordinator of',
      'Association' => 'Associations Coordinator of',
      'Other' => 'Default Coordinator of',
    ];

    $registrationCategorySelection = $registrationType['Institution_Collection_Camp_Intent.You_wish_to_register_as:name'];

    $registrationCategorySelection = trim($registrationCategorySelection);

    if (array_key_exists($registrationCategorySelection, $relationshipTypeMap)) {
      $relationshipTypeName = $relationshipTypeMap[$registrationCategorySelection];
    }
    else {
      $relationshipTypeName = 'Default Coordinator of';
    }

    // Retrieve the coordinators for the selected relationship type.
    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $stateOfficeId)
      ->addWhere('relationship_type_id:name', '=', $relationshipTypeName)
      ->addWhere('is_current', '=', TRUE)
      ->execute();

    $coordinator = self::getCoordinator($stateOfficeId, $relationshipTypeName, $coordinators);
    if (!$coordinator) {
      \CRM_Core_Error::debug_log_message('No coordinator available to assign.');
      return FALSE;
    }

    // Assign the coordinator to the collection camp.
    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Institution_collection_camp_Review.Coordinating_POC', $coordinator['contact_id_a'])
      ->addWhere('id', '=', $collectionCampId)
      ->execute();

    return TRUE;
  }

  /**
   *
   */
  public static function getCoordinator($stateOfficeId, $relationshipTypeName, $existingCoordinators = NULL) {
    if (!$existingCoordinators) {
      $existingCoordinators = Relationship::get(FALSE)
        ->addWhere('contact_id_b', '=', $stateOfficeId)
        ->addWhere('relationship_type_id:name', '=', $relationshipTypeName)
        ->addWhere('is_current', '=', TRUE)
        ->execute();
    }

    if ($existingCoordinators->count() === 0) {
      return self::getFallbackCoordinator($relationshipTypeName);
    }

    $coordinatorCount = $existingCoordinators->count();
    return $existingCoordinators->count() > 1
        ? $existingCoordinators->itemAt(rand(0, $coordinatorCount - 1))
        : $existingCoordinators->first();
  }

  /**
   *
   */
  public static function getFallbackCoordinator($relationshipTypeName) {
    $fallbackOffice = self::getFallbackOffice();
    if (!$fallbackOffice) {
      \CRM_Core_Error::debug_log_message('No fallback office found.');
      return FALSE;
    }

    // Retrieve fallback coordinators associated with the fallback office and relationship type.
    $fallbackCoordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $fallbackOffice['id'])
      ->addWhere('relationship_type_id:name', '=', $relationshipTypeName)
      ->addWhere('is_current', '=', TRUE)
      ->execute();

    // If no coordinators found, return false.
    if ($fallbackCoordinators->count() === 0) {
      \CRM_Core_Error::debug_log_message('No fallback coordinators found.');
      return FALSE;
    }

    // Randomly select a fallback coordinator if more than one is found.
    $randomIndex = rand(0, $fallbackCoordinators->count() - 1);
    return $fallbackCoordinators->itemAt($randomIndex);
  }

  /**
   *
   */
  public static function setOfficeDetails($op, $groupID, $entityID, &$params) {
    if ($op !== 'create' || self::getEntitySubtypeName($entityID) !== self::ENTITY_SUBTYPE_NAME) {
      return;
    }
    if (!($stateField = self::findStateField($params))) {
      return;
    }

    $stateId = $stateField['value'];
    $institutionCollectionCampId = $stateField['entity_id'];

    $institutionCollectionCampData = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Institution_Collection_Camp_Intent.Will_your_collection_drive_be_open_for_general_public_as_well')
      ->addWhere('id', '=', $institutionCollectionCampId)
      ->execute()->single();

    $isPublicDriveOpen = $institutionCollectionCampData['Institution_Collection_Camp_Intent.Will_your_collection_drive_be_open_for_general_public_as_well'];

    if (!$stateId) {
      \CRM_Core_Error::debug_log_message('Cannot assign Goonj Office to institution collection camp: ' . $institutionCollectionCampData['id']);
      return FALSE;
    }

    $officesFound = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addWhere('Goonj_Office_Details.Institution_Catchment', 'CONTAINS', $stateId)
      ->execute();

    $stateOffice = $officesFound->first();

    if (!$stateOffice) {
      $stateOffice = self::getFallbackOffice();
    }

    $stateOfficeId = $stateOffice['id'];

    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Institution_collection_camp_Review.Goonj_Office', $stateOfficeId)
      ->addValue('Institution_collection_camp_Review.Is_the_camp_IHC_PCC_', $isPublicDriveOpen)
      ->addWhere('id', '=', $institutionCollectionCampId)
      ->execute();

    $registrationType = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Institution_Collection_Camp_Intent.You_wish_to_register_as:name')
      ->addWhere('id', '=', $entityID)
      ->execute()->single();

    return self::assignCoordinatorByRelationshipType($stateOfficeId, $registrationType, $institutionCollectionCampId);

  }

  /**
   *
   */
  private static function isViewingInstituteCollectionCamp($tabsetName, $context) {
    if ($tabsetName !== 'civicrm/eck/entity' || empty($context) || $context['entity_type']['name'] !== self::ENTITY_NAME) {
      return FALSE;
    }

    $entityId = $context['entity_id'];

    $entity = EckEntity::get(self::ENTITY_NAME, TRUE)
      ->addWhere('id', '=', $entityId)
      ->execute()->single();

    $entitySubtypeValue = $entity['subtype'];

    $subtypeId = self::getSubtypeId();

    return (int) $entitySubtypeValue === $subtypeId;
  }

  /**
   *
   */
  public static function updateContributorCount($collectionCamp) {
    $activities = Activity::get(FALSE)
      ->addSelect('id')
      ->addWhere('Material_Contribution.Institution_Collection_Camp', '=', $collectionCamp['id'])
      ->execute();

    $contributorCount = count($activities);

    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Camp_Outcome.Number_of_Contributors', $contributorCount)
      ->addWhere('id', '=', $collectionCamp['id'])
      ->execute();
  }

  /**
   *
   */
  public static function updateContributionCount($collectionCamp) {
    $contributions = Contribution::get(FALSE)
      ->addSelect('total_amount')
      ->addWhere('Contribution_Details.Source', '=', $collectionCamp['id'])
      ->addWhere('is_test', 'IS NOT NULL')
      ->execute();

    // Initialize sum variable.
    $totalSum = 0;

    // Iterate through the results and sum the total_amount.
    foreach ($contributions as $contribution) {
      $totalSum += $contribution['total_amount'];
    }

    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Camp_Outcome.Monitory_Contribution', $totalSum)
      ->addWhere('id', '=', $collectionCamp['id'])
      ->execute();
  }

  /**
   *
   */
  public static function institutionCollectionCampTabset($tabsetName, &$tabs, $context) {
    if (!self::isViewingInstituteCollectionCamp($tabsetName, $context)) {
      return;
    }

    $restrictedRoles = ['account_team', 'ho_account', 'mmt'];

    $isAdmin = \CRM_Core_Permission::check('admin');

    $hasRestrictedRole = !$isAdmin && \CRM_Core_Permission::checkAnyPerm($restrictedRoles);

    if ($hasRestrictedRole) {
      unset($tabs['view']);
      unset($tabs['edit']);
    }

    $tabConfigs = [
      'logistics' => [
        'title' => ts('Logistics'),
        'module' => 'afsearchInstitutionCollectionCampLogistics',
        'directive' => 'afsearch-institution-collection-camp-logistics',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin'],
      ],
      'eventVolunteers' => [
        'title' => ts('Event Volunteers'),
        'module' => 'afsearchInstitutionCollectionCampEventVolunteers',
        'directive' => 'afsearch-institution-collection-camp-event-volunteers',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin'],
      ],
      'vehicleDispatch' => [
        'title' => ts('Dispatch'),
        'module' => 'afsearchInstitutionCampVehicleDispatchData',
        'directive' => 'afsearch-institution-camp-vehicle-dispatch-data',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin'],
      ],
      'materialAcknowledgement' => [
        'title' => ts('Material Acknowledgement'),
        'module' => 'afsearchInstitutionCampAcknowledgementDispatch',
        'directive' => 'afsearch-institution-camp-acknowledgement-dispatch',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin'],
      ],
      'materialContribution' => [
        'title' => ts('Material Contribution'),
        'module' => 'afsearchInstitutionCollectionCampMaterialContribution',
        'directive' => 'afsearch-institution-collection-camp-material-contribution',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'mmt', 'urban_ops_admin'],
      ],
      'campOutcome' => [
        'title' => ts('Camp Outcome'),
        'module' => 'afsearchInstitutionCampOutcome',
        'directive' => 'afsearch-institution-camp-outcome',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin'],
      ],
      'campFeedback' => [
        'title' => ts('Volunteer Feedback'),
        'module' => 'afsearchInstitutionCollectionCampFeedback',
        'directive' => 'afsearch-institution-collection-camp-feedback',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin'],
      ],
      'monetaryContribution' => [
        'title' => ts('Monetary Contribution'),
        'module' => 'afsearchMonetaryContribution',
        'directive' => 'afsearch-monetary-contribution',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['account_team', 'ho_account'],
      ],
      'monetaryContributionForUrbanOps' => [
        'title' => ts('Monetary Contribution'),
        'module' => 'afsearchMonetaryContributionForUrbanOps',
        'directive' => 'afsearch-monetary-contribution-for-urban-ops',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
    ];

    foreach ($tabConfigs as $key => $config) {
      $isAdmin = \CRM_Core_Permission::check('admin');
      if ($key == 'monetaryContributionForUrbanOps' && $isAdmin) {
        continue;
      }

      if (!\CRM_Core_Permission::checkAnyPerm($config['permissions'])) {
        // Does not permission; just continue.
        continue;
      }

      $tabs[$key] = [
        'id' => $key,
        'title' => $config['title'],
        'is_active' => 1,
        'template' => $config['template'],
        'module' => $config['module'],
        'directive' => $config['directive'],
      ];

      \Civi::service('angularjs.loader')->addModules($config['module']);
    }
  }

}
