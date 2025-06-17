<?php

class CRM_CivirulesConditions_Utils_GroupContact {

  /**
   * Checks wether a contact is a member of a group
   *
   * This function is a copy of CRM_Contact_BAO_GroupContact::isContactInGroup but with
   * a change so that the group contact cache won't be rebuild. Which somehow resulted
   * in a deadlock
   *
   * @param $contact_id
   * @param $group_id
   * @param array $statuses
   * @return bool
   */
  public static function isContactInGroup($contact_id, $group_id, array $statuses = ['Added']) {
    if (!CRM_Utils_Rule::positiveInteger($contact_id) ||
      !CRM_Utils_Rule::positiveInteger($group_id)
    ) {
      return FALSE;
    }
    $query = "SELECT saved_search_id FROM `civicrm_group` WHERE id=%1";
    $queryParams = [
      1 => [$group_id, 'Positive'],
    ];
    if (CRM_Core_DAO::singleValueQuery($query, $queryParams, FALSE)) {
      // @fixme We really need a "is in smartgroup" API!!
      // If the groups are smartgroups (saved searches) they may be out of date.
      // This triggers a check (and rebuild if necessary).
      \CRM_Contact_BAO_GroupContactCache::check($group_id);
      // We query the cache table to check a row exists for the provided group and contact
      // Any returned column/field is acceptable; we just want something returned.
      // We return group_id rather than id as cache tables may not necessarily have an id column.
      // cf. https://github.com/civicrm/civicrm-core/pull/9801
      $query = "SELECT group_id FROM `civicrm_group_contact_cache` WHERE group_id=%1 AND contact_id=%2";
      $queryParams = [
        1 => [$group_id, 'Positive'],
        2 => [$contact_id, 'Positive'],
      ];
      if (CRM_Core_DAO::singleValueQuery($query, $queryParams, FALSE)) {
        return TRUE;
      }
    }
    try {
      $groupContactCount = civicrm_api4('GroupContact', 'get', [
        'select' => [
          'row_count',
        ],
        'where' => [
          ['contact_id', '=', $contact_id],
          ['group_id', '=', $group_id],
          ['status', 'IN', $statuses],
        ],
        'checkPermissions' => FALSE,
      ])->count();
    }
    catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
    if ($groupContactCount > 0) {
      return TRUE;
    }

    return FALSE;
  }

}
