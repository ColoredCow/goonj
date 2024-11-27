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

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_fieldOptions' => 'setIndianStateOptions',
      '&hook_civicrm_pre' => 'generateInstitutionCollectionCampQr',
      '&hook_civicrm_custom' => 'setOfficeDetails',
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

        $startDate = new \DateTime($collectionCamp['Institution_Collection_Camp_Intent.Collections_will_start_on_Date_']);

        $today = new \DateTimeImmutable();
        $endOfToday = $today->setTime(23, 59, 59);

        if (!$logisticEmailSent && $startDate <= $endOfToday) {
            if (!$selfManagedBy) {
                $recipient = Contact::get(FALSE)
                    ->addSelect('email.email', 'display_name')
                    ->addJoin('Email AS email', 'LEFT')
                    ->addWhere('id', '=', $campAttendedById)
                    ->execute()->single();
            } else {
                $recipient = Contact::get(FALSE)
                    ->addSelect('email.email', 'display_name')
                    ->addJoin('Email AS email', 'LEFT')
                    ->addWhere('id', '=', $institutionPOCId)
                    ->execute()->single();
            }
            $recipientEmail = $recipient['email.email'];

            $recipientName = $recipient['display_name'];

            if (!$recipientEmail) {
                throw new \Exception('Recipient email missing');
            }
            $from = HelperService::getDefaultFromEmail();
            // Prepare email parameters
            $mailParams = [
                'subject' => 'Collection Camp Notification: ' . $campCode . ' at ' . $campAddress,
                'from' => $from,
                'toEmail' => $recipientEmail,
                'replyTo' => $from,
                'html' => self::getLogisticsEmailHtml($recipientName, $campId, $campAttendedById, $campOffice, $campCode, $campAddress),
            ];

            // Send email
            $emailSendResult = \CRM_Utils_Mail::send($mailParams);

            if ($emailSendResult) {
                \Civi::log()->info("Logistics email sent for collection camp: $campId");
                EckEntity::update('Collection_Camp', FALSE)
                    ->addValue('Institution_Collection_Camp_Logistics.Email_Sent', 1)
                    ->addWhere('id', '=', $campId)
                    ->execute();
            }
        }
    } catch (\Exception $e) {
        \Civi::log()->error("Error in sendLogisticsEmail for $campId: " . $e->getMessage());
    }
}

/**
 * Generates the logistics email HTML content
 */
private static function getLogisticsEmailHtml($contactName, $collectionCampId, $campAttendedById, $collectionCampGoonjOffice, $campCode, $campAddress) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();

    // Construct URLs for the dispatch and outcome forms
    $campVehicleDispatchFormUrl = $homeUrl . 'institution-camp-vehicle-dispatch-form/#?Camp_Vehicle_Dispatch.Collection_Camp=' . $collectionCampId . '&Camp_Vehicle_Dispatch.Filled_by=' . $campAttendedById . '&Camp_Vehicle_Dispatch.To_which_PU_Center_material_is_being_sent=' . $collectionCampGoonjOffice . '&Eck_Collection_Camp1=' . $collectionCampId;

    $campOutcomeFormUrl = $homeUrl . '/institution-camp-outcome-form/#?Eck_Collection_Camp1=' . $collectionCampId . '&Camp_Outcome.Filled_By=' . $campAttendedById;

    // Construct email HTML
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
