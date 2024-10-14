<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Relationship;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\CollectionSource;
use Civi\Traits\QrCodeable;
use Civi\Api4\Email;
use Civi\Api4\OptionValue;

/**
 *
 */
class DroppingCenterService extends AutoSubscriber {
  use QrCodeable;
  use CollectionSource;

  const ENTITY_NAME = 'Collection_Camp';
  const RELATIONSHIP_TYPE_NAME = 'Dropping Center Coordinator of';
  const ENTITY_SUBTYPE_NAME = 'Dropping_Center';
  const MATERIAL_RELATIONSHIP_TYPE_NAME = 'Material Management Team of';
  const FALLBACK_OFFICE_NAME = 'Delhi';

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_tabset' => 'droppingCenterTabset',
      '&hook_civicrm_pre' => 'generateDroppingCenterQr',
      '&hook_civicrm_custom' => [
        ['setOfficeDetails'],
        ['mailNotificationToMmt'],
      ],
    ];
  }

  /**
   *
   */
  public static function generateDroppingCenterQr(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName !== 'Eck_Collection_Camp' || !$objectId || !self::isCurrentSubtype($objectRef)) {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';
    if (!$newStatus) {
      return;
    }

    $collectionCamps = EckEntity::get('Collection_Camp', TRUE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_Core_Details.Contact_Id')
      ->addWhere('id', '=', $objectId)
      ->execute();

    $currentCollectionCamp = $collectionCamps->first();
    $currentStatus = $currentCollectionCamp['Collection_Camp_Core_Details.Status'];
    $collectionCampId = $currentCollectionCamp['id'];

    // Check for status change.
    if ($currentStatus !== $newStatus && $newStatus === 'authorized') {
      self::generateDroppingCenterQrCode($collectionCampId);
    }
  }

  /**
   *
   */
  private static function getFallbackCoordinator() {
    $fallbackOffice = self::getFallbackOffice();
    $fallbackCoordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $fallbackOffice['id'])
      ->addWhere('relationship_type_id:name', '=', self::RELATIONSHIP_TYPE_NAME)
      ->addWhere('is_current', '=', True)
      ->execute();

    $coordinatorCount = $fallbackCoordinators->count();

    $randomIndex = rand(0, $coordinatorCount - 1);
    $coordinator = $fallbackCoordinators->itemAt($randomIndex);

    return $coordinator;
  }

  /**
   *
   */
  private static function findStateField(array $array) {
    $stateFieldId = CustomField::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'State')
      ->addWhere('custom_group_id:name', '=', 'Dropping_Centre')
      ->execute()
      ->first()['id'] ?? NULL;

    if (!$stateFieldId) {
      return FALSE;
    }

    foreach ($array as $item) {
      if ($item['entity_table'] === 'civicrm_eck_collection_camp' &&
            $item['custom_field_id'] === $stateFieldId) {
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
  private static function generateDroppingCenterQrCode($id) {
    $baseUrl = \CRM_Core_Config::singleton()->userFrameworkBaseURL;
    $data = "{$baseUrl}actions/dropping-center/{$id}";

    $saveOptions = [
      'customGroupName' => 'Collection_Camp_QR_Code',
      'customFieldName' => 'QR_Code',
    ];

    self::generateQrCode($data, $id, $saveOptions);
  }

  private static function findOfficeId(array $array) {
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

  public static function mailNotificationToMmt($op, $groupID, $entityID, &$params) {
    if ($op !== 'create') {
      return;
    }

    if (!($goonjField = self::findOfficeId($params))) {
      return;
    }

    $goonjFieldId = $goonjField['value'];
    $vehicleDispatchId = $goonjField['entity_id'];

    $collectionSourceVehicleDispatch = EckEntity::get('Collection_Source_Vehicle_Dispatch', FALSE)
      ->addSelect('Camp_Vehicle_Dispatch.Collection_Camp')
      ->addWhere('id', '=', $vehicleDispatchId)
      ->execute()->first();

    $collectionCampId = $collectionSourceVehicleDispatch['Camp_Vehicle_Dispatch.Collection_Camp'];

    $droppingCenter = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Dropping_Centre.Where_do_you_wish_to_open_dropping_center_Address_', 'title')
      ->addWhere('id', '=', $collectionCampId)
      ->execute()->single();

    $droppingCenterCode = $droppingCenter['title'];
    $droppingCenterAddress = $droppingCenter['Dropping_Centre.Where_do_you_wish_to_open_dropping_center_Address_'];

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
      'subject' => 'Dropping center Address ' . $droppingCenterAddress . ' - Material Acknowledgement',
      'from' => $fromEmail['label'],
      'toEmail' => $mmtEmail,
      'replyTo' => $fromEmail['label'],
      'html' => self::goonjcustom_material_management_email_html($collectionCampId, $droppingCenterCode, $droppingCenterAddress, $vehicleDispatchId),
    ];
    \CRM_Utils_Mail::send($mailParams);

  }

  /**
   *
   */
  public static function goonjcustom_material_management_email_html($collectionCampId, $droppingCenterCode, $droppingCenterAddress, $vehicleDispatchId) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $materialdispatchUrl = $homeUrl . '/acknowledgement-form-for-dispatch/#?Eck_Collection_Source_Vehicle_Dispatch1=' . $vehicleDispatchId . '&Camp_Vehicle_Dispatch.Collection_Camp=' . $collectionCampId . '&id=' . $vehicleDispatchId . '&Eck_Collection_Camp1=' . $collectionCampId;
    $html = "
    <p>Dear MMT team,</p>
    <p>This is to inform you that a vehicle has been sent from the dropping center <strong>$droppingCenterCode</strong> at <strong>$droppingCenterAddress</strong>.</p>
    <p>Kindly acknowledge the details by clicking on this form <a href=\"$materialdispatchUrl\"> Link </a> when it is received at the center.</p>
    <p>Warm regards,<br>Urban Relations Team</p>";

    return $html;
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

    $droppingCenterId = $stateField['entity_id'];

    $droppingCenter = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Dropping_Centre.Will_your_dropping_center_be_open_for_general_public_as_well_out')
      ->addWhere('id', '=', $droppingCenterId)
      ->execute();

    $collectionCampData = $droppingCenter->first();

    if (!$stateId) {
      \CRM_Core_Error::debug_log_message('Cannot assign Goonj Office to  dropping center: ' . $droppingCenter['id']);
      \CRM_Core_Error::debug_log_message('No state provided on the intent for  dropping center: ' . $droppingCenter['id']);
      return FALSE;
    }

    $officesFound = Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addWhere('Goonj_Office_Details.Collection_Camp_Catchment', 'CONTAINS', $stateId)
      ->execute();

    $stateOffice = $officesFound->first();

    // If no state office is found, assign the fallback state office.
    if (!$stateOffice) {
      $stateOffice = self::getFallbackOffice();
    }

    $stateOfficeId = $stateOffice['id'];

    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Dropping_Centre.Goonj_Office', $stateOfficeId)
      ->addWhere('id', '=', $droppingCenterId)
      ->execute();

    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $stateOfficeId)
      ->addWhere('relationship_type_id:name', '=', self::RELATIONSHIP_TYPE_NAME)
      ->addWhere('is_current', '=', TRUE)
      ->execute();

    $coordinatorCount = $coordinators->count();

    if ($coordinatorCount === 0) {
      $coordinator = self::getFallbackCoordinator();
    }
    elseif ($coordinatorCount > 1) {
      $randomIndex = rand(0, $coordinatorCount - 1);
      $coordinator = $coordinators->itemAt($randomIndex);
    }
    else {
      $coordinator = $coordinators->first();
    }

    if (!$coordinator) {
      \CRM_Core_Error::debug_log_message('No coordinator available to assign.');
      return FALSE;
    }

    $coordinatorId = $coordinator['contact_id_a'];

    EckEntity::update('Collection_Camp', FALSE)
      ->addValue('Dropping_Centre.Coordinating_Urban_POC', $coordinatorId)
      ->addWhere('id', '=', $droppingCenterId)
      ->execute();

    return TRUE;

  }

  /**
   *
   */
  public static function droppingCenterTabset($tabsetName, &$tabs, $context) {
    if (!self::isViewingDroppingCenter($tabsetName, $context)) {
      return;
    }
    $tabConfigs = [
      'SendDispatchEmail' => [
        'title' => ts('Send Dispatch Email'),
        'module' => 'afformSendDispatchEmail',
        'directive' => 'afform-send-dispatch-email',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCampService.tpl',
      ],
      'status' => [
        'title' => ts('Status'),
        'module' => 'afsearchDroppingCenterStatus',
        'directive' => 'afsearch-dropping-center-status',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
      ],
      'visitDetails' => [
        'title' => ts('Visit Details'),
        'module' => 'afsearchVisitDetails',
        'directive' => 'afsearch-visit-details',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
      ],
      'donationTracking' => [
        'title' => ts('Donation Tracking'),
        'module' => 'afsearchDroppingCenterDonationBoxRegisterList',
        'directive' => 'afsearch-dropping-center-donation-box-register-list',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
      ],
      'logisticsCoordination' => [
        'title' => ts('Logistics'),
        'module' => 'afsearchDroppingCenterLogisticsCoordination',
        'directive' => 'afsearch-dropping-center-logistics-coordination',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
      ],
      'outcome' => [
        'title' => ts('Outcome'),
        'module' => 'afformDroppingCenterOutcome',
        'directive' => 'afform-dropping-center-outcome',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCampService.tpl',
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

  /**
   *
   */
  private static function isViewingDroppingCenter($tabsetName, $context) {
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

}
