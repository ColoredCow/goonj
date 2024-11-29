<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\StateProvince;
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
      '&hook_civicrm_fieldOptions' => 'setIndianStateOptions',
      '&hook_civicrm_pre' => 'generateInstitutionCollectionCampQr',
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
      ->addSelect('Camp_Vehicle_Dispatch.Collection_Camp')
      ->addWhere('id', '=', $vehicleDispatchId)
      ->execute()->first();

    $collectionCampId = $collectionSourceVehicleDispatch['Camp_Vehicle_Dispatch.Collection_Camp'];

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
      'html' => self::sendEmailToMmt($collectionCampId, $campCode, $campAddress, $vehicleDispatchId),
    ];
    \CRM_Utils_Mail::send($mailParams);

  }

  /**
   *
   */
  public static function sendEmailToMmt($collectionCampId, $campCode, $campAddress, $vehicleDispatchId) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $materialdispatchUrl = $homeUrl . 'institution-camp-acknowledgement-dispatch/#?Eck_Collection_Source_Vehicle_Dispatch1=' . $vehicleDispatchId . '&Camp_Vehicle_Dispatch.Collection_Camp=' . $collectionCampId . '&id=' . $vehicleDispatchId . '&Eck_Collection_Camp1=' . $collectionCampId;
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
  public static function setIndianStateOptions(string $entity, string $field, array &$options, array $params) {
    if ($entity !== 'Eck_Collection_Camp') {
      return;
    }

    $intentStateFields = CustomField::get(FALSE)
      ->addWhere('custom_group_id:name', '=', 'Institution_Collection_Camp_Intent')
      ->addWhere('name', '=', 'State')
      ->execute();

    $stateField = $intentStateFields->first();

    $statefieldId = $stateField['id'];

    if ($field !== "custom_$statefieldId") {
      return;
    }

    $indianStates = StateProvince::get(FALSE)
      ->addWhere('country_id.iso_code', '=', 'IN')
      ->addOrderBy('name', 'ASC')
      ->execute();

    $stateOptions = [];
    foreach ($indianStates as $state) {
      if ($state['is_active']) {
        $stateOptions[$state['id']] = $state['name'];
      }
    }

    $options = $stateOptions;

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

      $startDate = new \DateTime($collectionCamp['Institution_Collection_Camp_Intent.Collections_will_start_on_Date_']);

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
            'html' => self::getLogisticsEmailHtml($recipientName, $campId, $campAttendedById, $campOffice, $campCode, $campAddress),
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
            'html' => self::sendDispatchEmail($recipientName, $campId, $coordinatingPOCId, $campOffice, $campCode, $campAddress),
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
  private static function sendDispatchEmail($contactName, $collectionCampId, $campAttendedById, $collectionCampGoonjOffice, $campCode, $campAddress) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();

    $campVehicleDispatchFormUrl = $homeUrl . 'institution-camp-vehicle-dispatch-form/#?Camp_Vehicle_Dispatch.Collection_Camp=' . $collectionCampId . '&Camp_Vehicle_Dispatch.Filled_by=' . $campAttendedById . '&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent=' . $collectionCampGoonjOffice . '&Eck_Collection_Camp1=' . $collectionCampId;

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

    $campOutcomeFormUrl = $homeUrl . '/institution-camp-outcome-form/#?Eck_Collection_Camp1=' . $collectionCampId . '&Camp_Outcome.Filled_By=' . $campAttendedById;

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
  private static function getLogisticsEmailHtml($contactName, $collectionCampId, $campAttendedById, $collectionCampGoonjOffice, $campCode, $campAddress) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();

    $campVehicleDispatchFormUrl = $homeUrl . 'institution-camp-vehicle-dispatch-form/#?Camp_Vehicle_Dispatch.Collection_Camp=' . $collectionCampId . '&Camp_Vehicle_Dispatch.Filled_by=' . $campAttendedById . '&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent=' . $collectionCampGoonjOffice . '&Eck_Collection_Camp1=' . $collectionCampId;

    $campOutcomeFormUrl = $homeUrl . '/institution-camp-outcome-form/#?Eck_Collection_Camp1=' . $collectionCampId . '&Camp_Outcome.Filled_By=' . $campAttendedById;

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

    // If no state office is found, assign the fallback state office.
    if (!$stateOffice) {
      $stateOffice = self::getFallbackOffice();
    }

    $stateOfficeId = $stateOffice['id'];

    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Institution_collection_camp_Review.Goonj_Office', $stateOfficeId)
      ->addValue('Institution_Collection_Camp_Intent.Camp_Type', $isPublicDriveOpen)
      ->addWhere('id', '=', $institutionCollectionCampId)
      ->execute();
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
  public static function institutionCollectionCampTabset($tabsetName, &$tabs, $context) {
    if (!self::isViewingInstituteCollectionCamp($tabsetName, $context)) {
      return;
    }

    $tabConfigs = [
      'logistics' => [
        'title' => ts('Logistics'),
        'module' => 'afsearchInstitutionCollectionCampLogistics',
        'directive' => 'afsearch-institution-collection-camp-logistics',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
      ],
      'materialContribution' => [
        'title' => ts('Material Contribution'),
        'module' => 'afsearchInstitutionCollectionCampMaterialContribution',
        'directive' => 'afsearch-institution-collection-camp-material-contribution',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
      ],
      'vehicleDispatch' => [
        'title' => ts('Dispatch'),
        'module' => 'afsearchInstitutionCampVehicleDispatchData',
        'directive' => 'afsearch-institution-camp-vehicle-dispatch-data',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
      ],
      'materialAuthorization' => [
        'title' => ts('Material Authorization'),
        'module' => 'afsearchInstitutionCampAcknowledgementDispatch',
        'directive' => 'afsearch-institution-camp-acknowledgement-dispatch',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
      ],
      'campOutcome' => [
        'title' => ts('Camp Outcome'),
        'module' => 'afsearchInstitutionCampOutcome',
        'directive' => 'afsearch-institution-camp-outcome',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
      ],
    ];

    foreach ($tabConfigs as $key => $config) {
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
