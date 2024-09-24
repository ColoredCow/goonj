<?php

namespace Civi;

use Civi\Api4\CustomField;
use Civi\Api4\EckEntity;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Api4\MessageTemplate;
use Civi\Api4\OptionValue;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class CollectionBaseService extends AutoSubscriber {

  const ENTITY_NAME = 'Collection_Camp';
  const INTENT_CUSTOM_GROUP_NAME = 'Collection_Camp_Intent_Details';

  private static $stateCustomFieldDbDetails = [];
  private static $collectionAuthorized = NULL;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_tabset' => 'collectionBaseTabset',
      '&hook_civicrm_selectWhereClause' => 'aclCollectionCamp',
      '&hook_civicrm_pre' => 'handleAuthorizationEmails',
      '&hook_civicrm_post' => 'handleAuthorizationEmailsPost',
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

    // URL for the Logistics tab.
    $logisticsUrl = \CRM_Utils_System::url(
      "wp-admin/admin.php?page=CiviCRM&q=civicrm%2Flogistics-coordination#",
    );

    // URL for the Dispatch tab.
    $vehicleDispatch = \CRM_Utils_System::url(
      "wp-admin/admin.php?page=CiviCRM&q=civicrm%2Fcamp-vehicle-dispatch-data",
    );

    // URL for the camp outcome tab.
    $campOutcome = \CRM_Utils_System::url(
      "wp-admin/admin.php?page=CiviCRM&q=civicrm%2Fadmin-camp-outcome-form",
    );

    // URL for the event volunteer tab.
    $eventVolunteersUrl = \CRM_Utils_System::url(
      "wp-admin/admin.php?page=CiviCRM&q=civicrm%2Fevent-volunteer",
    );

    // Add the event volunteer tab.
    $tabs['eventVolunteers'] = [
      'title' => ts('Event Volunteers'),
      'link' => $eventVolunteersUrl,
      'valid' => 1,
      'active' => 1,
      'current' => FALSE,
    ];

    // Add the Contribution tab.
    $tabs['contribution'] = [
      'title' => ts('Material Contribution'),
      'link' => $contributionUrl,
      'valid' => 1,
      'active' => 1,
      'current' => FALSE,
    ];

    // Add the Logistics tab.
    $tabs['logistics'] = [
      'title' => ts('Logistics'),
      'link' => $logisticsUrl,
      'valid' => 1,
      'active' => 1,
      'current' => FALSE,
    ];

    // Add the vehicle dispatch tab.
    $tabs['vehicleDispatch'] = [
      'title' => ts('Dispatch'),
      'link' => $vehicleDispatch,
      'valid' => 1,
      'active' => 1,
      'current' => FALSE,
    ];

    // Add the camp outcome tab.
    $tabs['campOutcome'] = [
      'title' => ts('Camp Outcome'),
      'link' => $campOutcome,
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
    if ($objectName != 'Eck_Collection_Camp' || !$objectId) {
      return;
    }

    $newStatus = $objectRef['Collection_Camp_Core_Details.Status'] ?? '';
    $subType = $objectRef['subtype'] ?? '';

    if (!$newStatus) {
      return;
    }

    $currentCollectionCamp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_Core_Details.Contact_Id')
      ->addWhere('id', '=', $objectId)
      ->execute()->single();

    $currentStatus = $currentCollectionCamp['Collection_Camp_Core_Details.Status'];
    $initiatorId = $currentCollectionCamp['Collection_Camp_Core_Details.Contact_Id'];

    // Check for status change.
    if ($currentStatus !== $newStatus) {
      self::$collectionAuthorized = $objectId;
    }
  }

  /**
   *
   */
  public static function handleAuthorizationEmailsPost(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName != 'Eck_Collection_Camp' || !$objectId || $objectId !== self::$collectionAuthorized) {
      return;
    }
   
    $collectionCamp = EckEntity::get('Collection_Camp', FALSE)
      ->addSelect('Collection_Camp_Core_Details.Status', 'Collection_Camp_Core_Details.Contact_Id', 'subtype')
      ->addWhere('id', '=', $objectRef->id)
      ->execute()->single();

      $status = $collectionCamp['Collection_Camp_Core_Details.Status'];
      $initiator = $collectionCamp['Collection_Camp_Core_Details.Contact_Id'];
      $subType = $collectionCamp['subtype'];

      self::sendAuthorizationEmail($initiator, $subType, $newStatus);
  }

  /**
   * Send Authorization Email to contact.
   */
  private static function sendAuthorizationEmail($initiatorId, $subType, $status) {
    try {
      $templateId = self::getMessageTemplateId($subType, $status);

      $emailParams = [
        'contact_id' => $initiatorId,
        'template_id' => $templateId,
      ];

      $result = civicrm_api3('Email', 'send', $emailParams);

    }
    catch (\Exception $ex) {
      \Civi::log()->debug('Cannot send authorization email to initiator.', [
        'initiatorId' => $initiatorId,
        'status' => $status,
        'entityId' => $objectRef['id'],
        'error' => $ex->getMessage(),
      ]);
    }
  }

  /**
   *
   */
  public static function getMessageTemplateId($collectionCampSubtype, $status) {
    $collectionCampSubtypes = OptionValue::get(FALSE)
      ->addWhere('option_group_id:name', '=', 'eck_sub_types')
      ->addWhere('grouping', '=', 'Collection_Camp')
      ->execute();

    foreach ($collectionCampSubtypes as $subtype) {
      $subtypeValue = $subtype['value'];
      $subtypeName = $subtype['name'];

      $mapper[$subtypeValue]['authorized'] = $subtypeName . ' authorized';
      $mapper[$subtypeValue]['unauthorized'] = $subtypeName . ' unauthorized';
    }

    $msgTitleStartsWith = $mapper[$collectionCampSubtype][$status] . '%';

    $messageTemplates = MessageTemplate::get(FALSE)
      ->addSelect('id')
      ->addWhere('msg_title', 'LIKE', $msgTitleStartsWith)
      ->addWhere('is_active', '=', TRUE)
      ->execute();

    $messageTemplate = $messageTemplates->first();

    return $messageTemplate['id'];
  }

}
