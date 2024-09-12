<?php

namespace Civi;

use Civi\Api4\CustomField;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Core\Service\AutoSubscriber;
use Civi\Api4\EckEntity;
use Civi\Api4\MessageTemplate;
use Civi\Api4\OptionValue;

/**
 *
 */
class CollectionBaseService extends AutoSubscriber {

  const ENTITY_NAME = 'Collection_Camp';
  const INTENT_CUSTOM_GROUP_NAME = 'Collection_Camp_Intent_Details';

  private static $stateCustomFieldDbDetails = [];

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_tabset' => 'collectionBaseTabset',
      '&hook_civicrm_selectWhereClause' => 'aclCollectionCamp',
      '&hook_civicrm_pre' => ['handleAuthorizationEmails'],
    ];
  }

  /**
   *
   */
  public static function collectionBaseTabset($tabsetName, &$tabs, $context) {
    if ($tabsetName !== 'civicrm/eck/entity' || empty($context) || $context['entity_type']['name'] !== self::ENTITY_NAME) {
      return;
    }

    // URL for the Contribution tab.
    $contributionUrl = \CRM_Utils_System::url(
          "wp-admin/admin.php?page=CiviCRM&q=civicrm%2Fcollection-camp%2Fmaterial-contributions",
    );

    // Add the Contribution tab.
    $tabs['contribution'] = [
      'title' => ts('Contribution'),
      'link' => $contributionUrl,
      'valid' => 1,
      'active' => 1,
      'current' => FALSE,
    ];
  }

  /**
   *
   */
  public static function aclCollectionCamp($entity, &$clauses, $userId, $conditions) {
    if ($entity !== 'Eck_Collection_Camp') {
      return FALSE;
    }

    try {
      $teamGroupContacts = GroupContact::get(FALSE)
        ->addSelect('group_id')
        ->addWhere('contact_id', '=', $userId)
        ->addWhere('status', '=', 'Added')
        ->addWhere('group_id.Chapter_Contact_Group.Use_Case', '=', 'chapter-team')
        ->execute();

      $teamGroupContact = $teamGroupContacts->first();

      if (!$teamGroupContact) {
        \Civi::log()->debug('no chapter team group found for user: ' . $userId);
        // @todo we should handle it in a better way.
        // if there is no chapter assigned to the contact
        // then ideally she should not see any collection camp which
        // can be done but then it limits for the admin user as well.
        return FALSE;
      }

      $groupId = $teamGroupContact['group_id'];

      $chapterGroups = Group::get(FALSE)
        ->addSelect('Chapter_Contact_Group.States_controlled')
        ->addWhere('id', '=', $groupId)
        ->execute();

      $group = $chapterGroups->first();
      $statesControlled = $group['Chapter_Contact_Group.States_controlled'];

      if (empty($statesControlled)) {
        // Handle the case when the group is not controlling any state.
        $clauses['id'][] = 'IN (null)';
        return TRUE;
      }

      $statesControlled = array_unique($statesControlled);
      $statesList = implode(',', array_map('intval', $statesControlled));

      $stateField = self::getStateFieldDbDetails();

      $clauseString = sprintf(
      'IN (SELECT entity_id FROM `%1$s` WHERE `%2$s` IN (%3$s))',
      $stateField['tableName'],
      $stateField['columnName'],
      $statesList,
      );

      $clauses['id'][] = $clauseString;
    }
    catch (\Exception $e) {
      \Civi::log()->warning("Unable to apply acl on collection camp for user $userId. " . $e->getMessage());
    }

    return TRUE;
  }

  /**
   *
   */
  private static function getStateFieldDbDetails() {
    if (empty(self::$stateCustomFieldDbDetails)) {
      $customField = CustomField::get(FALSE)
        ->addSelect('column_name', 'custom_group_id.table_name')
        ->addWhere('custom_group_id.name', '=', self::INTENT_CUSTOM_GROUP_NAME)
        ->addWhere('name', '=', 'state')
        ->execute()->single();

      self::$stateCustomFieldDbDetails = [
        'tableName' => $customField['custom_group_id.table_name'],
        'columnName' => $customField['column_name'],
      ];

    }

    return self::$stateCustomFieldDbDetails;

  }

  /**
   * This hook is called after a db write on entities.
   *
   * @param string $op
   *   The type of operation being performed.
   * @param string $objectName
   *   The name of the object.
   * @param int $objectId
   *   The unique identifier for the object.
   * @param object $objectRef
   *   The reference to the object.
   */
  public static function handleAuthorizationEmails(string $op, string $objectName, $objectId, &$objectRef) {
    \Civi::log()->info('test');
    if ($objectName != 'Eck_Collection_Camp' || !$objectId) {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';
    $subType = $objectRef['subtype'] ?? '';

    if (!$newStatus) {
      return;
    }

    $collectionCamps = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_Core_Details.Contact_Id')
      ->addWhere('id', '=', $objectId)
      ->execute();

    $currentCollectionCamp = $collectionCamps->first();
    $currentStatus = $currentCollectionCamp['Collection_Camp_Core_Details.Status'];
    $contactId = $currentCollectionCamp['Collection_Camp_Core_Details.Contact_Id'];

    $optionValues = OptionValue::get(FALSE)
      ->addWhere('option_group_id:label', '=', 'ECK Subtypes')
      ->addWhere('label', 'IN', ['Collection Camp', 'Dropping Center'])
      ->execute();

    $collectionCampSubtype = NULL;
    $droppingCenterSubtype = NULL;

    foreach ($optionValues as $optionValue) {
      switch ($optionValue['label']) {
        case 'Collection Camp':
          $collectionCampSubtype = $optionValue['value'];
          break;

        case 'Dropping Center':
          $droppingCenterSubtype = $optionValue['value'];
          break;
      }

      if ($collectionCampSubtype !== NULL && $droppingCenterSubtype !== NULL) {
        break;
      }
    }

    // Check for status change.
    if ($currentStatus !== $newStatus) {
      if ($newStatus === 'authorized') {
        self::sendAuthorizationEmail($contactId, $subType, $collectionCampSubtype, $droppingCenterSubtype);
      }
      elseif ($newStatus === 'unauthorized') {
        self::sendUnAuthorizationEmail($contactId, $subType, $collectionCampSubtype, $droppingCenterSubtype);
      }
    }
  }

  /**
   * Send Authorization Email to contact.
   */
  private static function sendAuthorizationEmail($contactId, $subType, $collectionCampSubtype, $droppingCenterSubtype) {
    try {
      // Determine the template based on dynamic subtype.
      $templateIds = self::getMessageTemplateIDs();
      $collectionCampAuthorizedTemplateId = $templateIds['collectionCampAuthorizedTemplateId'];
      $droppingCenterAuthorizedTemplateId = $templateIds['droppingCenterAuthorizedTemplateId'];
      $templateId = $subType == $collectionCampSubtype ? $collectionCampAuthorizedTemplateId : ($subType == $droppingCenterSubtype ? $droppingCenterAuthorizedTemplateId : NULL);

      if (!$templateId) {
        return;
      }

      $emailParams = [
        'contact_id' => $contactId,
        // Template ID for the authorization email.
        'template_id' => $templateId,
      ];

      $result = civicrm_api3('Email', 'send', $emailParams);

    }
    catch (\CiviCRM_API3_Exception $ex) {
      error_log("Exception caught while sending authorization email: " . $ex->getMessage());
    }
  }

  /**
   * Send UnAuthorization Email to contact.
   */
  private static function sendUnAuthorizationEmail($contactId, $subType, $collectionCampSubtype, $droppingCenterSubtype) {
    try {
      // Determine the template based on dynamic subtype.
      $templateIds = self::getMessageTemplateIDs();
      $collectionCampUnAuthorizedTemplateId = $templateIds['collectionCampUnAuthorizedTemplateId'];
      $droppingCenterUnAuthorizedTemplateId = $templateIds['droppingCenterUnAuthorizedTemplateId'];
      $templateId = $subType == $collectionCampSubtype ? $collectionCampUnAuthorizedTemplateId : ($subType == $droppingCenterSubtype ? $droppingCenterUnAuthorizedTemplateId : NULL);

      if (!$templateId) {
        return;
      }

      $emailParams = [
        'contact_id' => $contactId,
        // Template ID for the unauthorization email.
        'template_id' => $templateId,
      ];

      $result = civicrm_api3('Email', 'send', $emailParams);

    }
    catch (\CiviCRM_API3_Exception $ex) {
      error_log("Exception caught while sending unauthorization email: " . $ex->getMessage());
    }
  }

  /**
   *
   */
  public static function getMessageTemplateIDs() {
    $messageTemplates = MessageTemplate::get(TRUE)
      ->addSelect('id', 'msg_title')
      ->execute();

    $collectionCampAuthorizedTemplateId = NULL;
    $collectionCampUnAuthorizedTemplateId = NULL;
    $droppingCenterAuthorizedTemplateId = NULL;
    $droppingCenterUnAuthorizedTemplateId = NULL;

    foreach ($messageTemplates as $messageTemplate) {
      if ($messageTemplate['msg_title'] === 'Collection Camp Authorized Email To User') {
        $collectionCampAuthorizedTemplateId = $messageTemplate['id'];
      }
      if ($messageTemplate['msg_title'] === 'Trigger UnAuthorized Email for Collection Camp to User') {
        $collectionCampUnAuthorizedTemplateId = $messageTemplate['id'];
      }
      if ($messageTemplate['msg_title'] === 'Trigger UnAuthorized Email for dropping center to User') {
        $droppingCenterUnAuthorizedTemplateId = $messageTemplate['id'];
      }
      if ($messageTemplate['msg_title'] === 'Dropping Center Authorized Email To User') {
        $droppingCenterAuthorizedTemplateId = $messageTemplate['id'];
      }

      if ($messageTemplate['msg_title'] === 'Notify Urban Ops Team after collection camp form submission') {
        $adminNotificationTemplateId = $messageTemplate['id'];
      }

    }

    return [
      'collectionCampAuthorizedTemplateId' => $collectionCampAuthorizedTemplateId,
      'collectionCampUnAuthorizedTemplateId' => $collectionCampUnAuthorizedTemplateId,
      'droppingCenterAuthorizedTemplateId' => $droppingCenterAuthorizedTemplateId,
      'droppingCenterUnAuthorizedTemplateId' => $droppingCenterUnAuthorizedTemplateId,
      'adminNotificationColletionCampTemplateId' => $adminNotificationTemplateId,
    ];
  }

}
