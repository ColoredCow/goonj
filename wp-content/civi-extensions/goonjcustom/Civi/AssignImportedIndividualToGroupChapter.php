<?php

namespace Civi;

use Civi\Api4\GroupContact;
use Civi\Api4\Group;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class AssignImportedIndividualToGroupChapter extends AutoSubscriber {
  private static $individualId = NULL;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_post' => [
                ['assignToGroupChapter'],
      ],
    ];
  }

  /**
   *
   */
  public static function assignToGroupChapter($op, $objectName, $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'Address') {
      return FALSE;
    }

    $contact_id = $objectRef->contact_id;

    $stateProvinceId = $objectRef->state_province_id;

    if (!$objectRef->state_province_id) {
      return FALSE;
    }

    $groupId = self::getChapterGroupForState($objectRef->state_province_id);

    $contactId = is_array($contact_id) ? ($contact_id[0] ?? NULL) : $contact_id;

    $groupContacts = GroupContact::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('group_id', '=', $groupId)
      ->execute()->first();

    if (!empty($groupContacts)) {
      return;
    }

    if ($groupId && $contactId) {
      try {
        $result = GroupContact::create(FALSE)
          ->addValue('contact_id', $contactId)
          ->addValue('group_id', $groupId)
          ->addValue('status', 'Added')
          ->execute();
      }
      catch (\Exception $e) {
        if (str_contains($e->getMessage(), 'already exists')) {
          \Civi::log()->info('GroupContact already exists, skipping creation.', [
            'contact_id' => $contactId,
            'group_id' => $groupId,
          ]);
        }
        else {
          throw $e;
        }
      }
    }

    // Refresh only the ACL/group caches so the new chapter-group assignment is
    // immediately visible in forms/ACLs — fix for ColoredCow/goonj-crm#269.
    //
    // Previously this called civicrm_api3('System', 'flush'), but System.flush
    // also runs managed-entity reconciliation. During a live event registration
    // (a new contact + Address create) that reconcile re-INSERTs already-present
    // managed entities such as the Razorpay payment_processor_type, throwing a
    // duplicate-key error mid-postProcess and silently aborting the rest of the
    // save (fields show on the thank-you screen but never reach the DB/SearchKit).
    // Clearing just the ACL + group-contact caches achieves the #269 visibility
    // goal without touching managed entities — and is far cheaper, so it no
    // longer needs the bulk-import guard.
    if ($groupId) {
      \CRM_Contact_BAO_GroupContactCache::invalidateGroupContactCache($groupId);
    }
    \CRM_ACL_BAO_Cache::resetCache();
  }

  /**
   *
   */
  private static function getChapterGroupForState($stateId) {
    $stateContactGroups = Group::get(FALSE)
      ->addSelect('id')
      ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
      ->addWhere('Chapter_Contact_Group.Contact_Catchment', 'CONTAINS', $stateId)
      ->execute();

    $stateContactGroup = $stateContactGroups->first();

    if (!$stateContactGroup) {
      \CRM_Core_Error::debug_log_message('No chapter contact group found for state ID: ' . $stateId);

      $fallbackGroups = Group::get(FALSE)
        ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
        ->addWhere('Chapter_Contact_Group.Fallback_Chapter', '=', 1)
        ->execute();

      $stateContactGroup = $fallbackGroups->first();

      \Civi::log()->info('Assigning fallback chapter contact group: ' . $stateContactGroup['title']);
    }

    return $stateContactGroup ? $stateContactGroup['id'] : NULL;
  }

}
