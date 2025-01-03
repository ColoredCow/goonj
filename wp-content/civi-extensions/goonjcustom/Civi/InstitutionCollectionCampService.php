<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\Contribution;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Email;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Api4\OptionValue;
use Civi\Api4\Relationship;
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

        $results = EckEntity::update('Collection_Camp', TRUE)
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

    if ($objectName !== 'Eck_Collection_Camp' || !self::isCurrentSubtype($objectRef)) {
      return;
    }
    $stateId = $objectRef['Institution_Collection_Camp_Intent.State'];
    $contactId = $objectRef['Institution_Collection_Camp_Intent.Institution_POC'];
    $status = $objectRef['Collection_Camp_Core_Details.Status'];

    if ($status == 'authorized') {
      return;
    }
    if (!$stateId) {
      \Civi::log()->info("Missing Contact ID and State ID");
      return FALSE;
    }
    $groupId = self::getChapterGroupForState($stateId);

    if ($groupId) {
      self::addContactToGroup($contactId, $groupId);
    }
  }

  /**
   *
   */
  private static function addContactToGroup($contactId, $groupId) {
    if ($contactId && $groupId) {
      try {
        GroupContact::create(FALSE)
          ->addValue('contact_id', $contactId)
          ->addValue('group_id', $groupId)
          ->addValue('status', 'Added')
          ->execute();
        \Civi::log()->info("Successfully added contact_id: $contactId to group_id: $groupId.");
      }
      catch (Exception $e) {
        \Civi::log()->error("Error adding contact_id: $contactId to group_id: $groupId. Exception: " . $e->getMessage());
      }
    }
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

    $collectionCamp = EckEntity::get('Collection_Camp', TRUE)
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
      ->addSelect('Camp_Vehicle_Dispatch.Collection_Camp', 'Camp_Institution_Data.Name_of_the_institution', 'Camp_Institution_Data.Address', 'Camp_Institution_Data.Email', 'Camp_Institution_Data.Contact_Number')
      ->addWhere('id', '=', $vehicleDispatchId)
      ->execute()->first();

    $collectionCampId = $collectionSourceVehicleDispatch['Camp_Vehicle_Dispatch.Collection_Camp'];
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
<<<<<<< Updated upstream
    . '&Camp_Vehicle_Dispatch.Collection_Camp=' . $collectionCampId
    . '&id=' . $vehicleDispatchId
    . '&Eck_Collection_Source_Vehicle_Dispatch_Eck_Collection_Camp_Collection_Camp_01.id=' . $collectionCampId
    . '&Camp_Institution_Data.Name_of_the_institution=' . $nameOfInstitution
    . '&Camp_Institution_Data.Address=' . $addressOfInstitution
    . '&Camp_Institution_Data.Email=' . $pocEmail
    . '&Camp_Institution_Data.Contact_Number=' . $pocContactNumber;
=======
    . '&Camp_Vehicle_Dispatch.Institution_Collection_Camp=' . $collectionCampId
    . '&id=' . $vehicleDispatchId;
>>>>>>> Stashed changes

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

      $organization = Contact::get(FALSE)
        ->addSelect('Institute_Registration.Legal_Name_of_Institute', 'Institute_Registration.Address')
        ->addWhere('id', '=', $organizationId)
        ->execute()->single();

      $nameOfInstitution = $organization['Institute_Registration.Legal_Name_of_Institute'];
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
            'html' => self::sendDispatchEmail($recipientName, $campId, $coordinatingPOCId, $campOffice, $campCode, $campAddress, $pocEmail, $pocContactNumber, $nameOfInstitution, $addressOfInstitution),
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
            'html' => self::sendOutcomeEmail($recipientName, $campId, $coordinatingPOCId, $campCode, $campAddress,),
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
  private static function sendDispatchEmail($contactName, $collectionCampId, $campAttendedById, $collectionCampGoonjOffice, $campCode, $campAddress, $pocEmail, $pocContactNumber, $nameOfInstitution, $addressOfInstitution) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();

    $campVehicleDispatchFormUrl = $homeUrl . 'institution-camp-vehicle-dispatch-form/#?Camp_Vehicle_Dispatch.Institution_Collection_Camp=' . $collectionCampId
    . '&Camp_Vehicle_Dispatch.Filled_by=' . $campAttendedById
    . '&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent=' . $collectionCampGoonjOffice
    . '&Eck_Collection_Camp1=' . $collectionCampId
    . '&Camp_Institution_Data.Name_of_the_institution=' . $nameOfInstitution
    . '&Camp_Institution_Data.Address=' . $addressOfInstitution
    . '&Camp_Institution_Data.Email=' . $pocEmail
    . '&Camp_Institution_Data.Contact_Number=' . $pocContactNumber
    . '&Institution_Collection_Camp_Intent.Collection_Camp_Address=' . $campAddress;

    $html = "
  <p>Dear $contactName,</p>
  <p>Thank you for attending the camp <strong>$campCode</strong> at <strong>$campAddress</strong>. There is a form that requires your attention during the camp:</p>
  <ol>
      <li><a href=\"$campVehicleDispatchFormUrl\">Dispatch Form</a><br>
      Please complete this form from the camp location once the vehicle is being loaded and ready for dispatch to Goonj's processing center.</li>
  </ol>
  <p>We appreciate your cooperation.</p>
  <p>Warm Regards,<br>Urban Relations Team</p>";

    return $html;
  }

  /**
   *
   */
  private static function sendOutcomeEmail($contactName, $collectionCampId, $coordinatingPOCId, $campCode, $campAddress) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();

    $campOutcomeFormUrl = $homeUrl . '/institution-camp-outcome-form/#?Eck_Collection_Camp1=' . $collectionCampId . '&Camp_Outcome.Filled_By=' . $campAttendedById . '&Institution_Collection_Camp_Intent.Collection_Camp_Address=' . $campAddress;

    $html = "
  <p>Dear $contactName,</p>
  <p>Thank you for attending the camp <strong>$campCode</strong> at <strong>$campAddress</strong>. There is one form that requires your attention after the camp:</p>
  <ol>
      <li><a href=\"$campOutcomeFormUrl\">Camp Outcome Form</a><br>
      This feedback form should be filled out after the camp/drive ends, once you have an overview of the event's outcomes.</li>
  </ol>
  <p>We appreciate your cooperation.</p>
  <p>Warm Regards,<br>Urban Relations Team</p>";

    return $html;
  }

  /**
   * Generates the logistics email HTML content.
   */
  private static function getLogisticsEmailHtml($contactName, $collectionCampId, $campAttendedById, $collectionCampGoonjOffice, $campCode, $campAddress, $pocEmail, $pocContactNumber, $nameOfInstitution, $addressOfInstitution) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();

    $campVehicleDispatchFormUrl = $homeUrl
    . 'institution-camp-vehicle-dispatch-form/#?Camp_Vehicle_Dispatch.Institution_Collection_Camp=' . $collectionCampId
    . '&Camp_Vehicle_Dispatch.Filled_by=' . $campAttendedById
    . '&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent=' . $collectionCampGoonjOffice
    . '&Eck_Collection_Camp1=' . $collectionCampId
    . '&Camp_Institution_Data.Name_of_the_institution=' . $nameOfInstitution
    . '&Camp_Institution_Data.Address=' . $addressOfInstitution
    . '&Camp_Institution_Data.Email=' . $pocEmail
    . '&Camp_Institution_Data.Contact_Number=' . $pocContactNumber
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
      'College' => 'College Coordinator of',
      'Associations' => 'Associations Coordinator of',
      'Others' => 'Others Coordinator of',
    ];

    $registrationCategorySelection = $registrationType['Institution_Collection_Camp_Intent.You_wish_to_register_as:name'];

    $registrationCategorySelection = trim($registrationCategorySelection);

    if (array_key_exists($registrationCategorySelection, $relationshipTypeMap)) {
      $relationshipTypeName = $relationshipTypeMap[$registrationCategorySelection];
    }
    else {
      $relationshipTypeName = 'Other Entities Coordinator of';
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

    $restrictedRoles = ['account_team', 'ho_account'];

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
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'vehicleDispatch' => [
        'title' => ts('Dispatch'),
        'module' => 'afsearchInstitutionCampVehicleDispatchData',
        'directive' => 'afsearch-institution-camp-vehicle-dispatch-data',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'materialAuthorization' => [
        'title' => ts('Material Authorization'),
        'module' => 'afsearchInstitutionCampAcknowledgementDispatch',
        'directive' => 'afsearch-institution-camp-acknowledgement-dispatch',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'materialContribution' => [
        'title' => ts('Material Contribution'),
        'module' => 'afsearchInstitutionCollectionCampMaterialContribution',
        'directive' => 'afsearch-institution-collection-camp-material-contribution',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'campOutcome' => [
        'title' => ts('Camp Outcome'),
        'module' => 'afsearchInstitutionCampOutcome',
        'directive' => 'afsearch-institution-camp-outcome',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'campFeedback' => [
        'title' => ts('Volunteer Feedback'),
        'module' => 'afsearchInstitutionCollectionCampFeedback',
        'directive' => 'afsearch-institution-collection-camp-feedback',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
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
