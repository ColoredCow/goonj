<?php

/**
 * @file
 */

use Civi\Api4\EntityTag;
use Civi\Api4\GroupContact;

/**
 * Goonjcustom.ImportContactWithoutGroupCron API specification.
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_import_contact_without_group_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.ImportContactWithoutGroupCron API.
 *
 * Finds contacts tagged "Contacts Without Group" and removes them from any
 * auto-assigned chapter groups (ending with "Team" or "Contact").
 *
 * @param array $params
 *
 * @return array API result descriptor
 *
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_goonjcustom_import_contact_without_group_cron($params) {
  $returnValues = [];
  $limit = 500;
  $offset = 0;
  $processedCount = 0;
  $failedContacts = [];

  do {
    try {
      $entityTags = EntityTag::get(FALSE)
        ->addSelect('entity_id', 'id')
        ->addWhere('entity_table', '=', 'civicrm_contact')
        ->addWhere('tag_id:name', '=', 'Contacts_Without_Group')
        ->setLimit($limit)
        ->setOffset($offset)
        ->execute();
    }
    catch (\Exception $e) {
      \Civi::log()->error('[ImportContactWithoutGroupCron] Failed to fetch tagged contacts', [
        'error' => $e->getMessage(),
        'offset' => $offset,
      ]);
      break;
    }

    $batchCount = $entityTags->count();

    if ($batchCount === 0) {
      break;
    }

    foreach ($entityTags as $entityTag) {
      $contactId = $entityTag['entity_id'];
      $entityTagId = $entityTag['id'];

      try {
        // Remove contact from any chapter "Team" or "Contacts" groups.
        $existingChapterGroups = GroupContact::get(FALSE)
          ->addSelect('id', 'group_id')
          ->addJoin('Group AS group', 'LEFT')
          ->addWhere('contact_id', '=', $contactId)
          ->addClause('OR',
            ['group.title', 'LIKE', '%Team'],
            ['group.title', 'LIKE', '%Contacts']
          )
          ->execute();

        foreach ($existingChapterGroups as $existingGroup) {
          GroupContact::delete(FALSE)
            ->addWhere('id', '=', $existingGroup['id'])
            ->execute();

          \Civi::log()->info('[ImportContactWithoutGroupCron] Removed contact from group', [
            'contact_id' => $contactId,
            'group_id' => $existingGroup['group_id'],
          ]);
        }

        // Remove the "Contacts_Without_Group" tag from this contact.
        EntityTag::delete(FALSE)
          ->addWhere('entity_table', '=', 'civicrm_contact')
          ->addWhere('entity_id', '=', $contactId)
          ->addWhere('tag_id:name', '=', 'Contacts_Without_Group')
          ->execute();

        // Add the "Ungrouped_Contacts" tag only if groups were actually removed.
        if ($existingChapterGroups->count() > 0) {
          EntityTag::create(FALSE)
            ->addValue('entity_id', $contactId)
            ->addValue('entity_table', 'civicrm_contact')
            ->addValue('tag_id:name', 'Ungrouped_Contacts')
            ->execute();
        }

        $processedCount++;
      }
      catch (\Exception $e) {
        $failedContacts[] = $contactId;
        \Civi::log()->error('[ImportContactWithoutGroupCron] Failed to process contact', [
          'contact_id' => $contactId,
          'error' => $e->getMessage(),
        ]);
        $offset++;
      }
    }
  } while ($batchCount === $limit);

  \Civi::log()->info('[ImportContactWithoutGroupCron] Cron completed', [
    'processed' => $processedCount,
    'failed_count' => count($failedContacts),
    'failed_contacts' => $failedContacts,
  ]);

  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'import_contact_without_group_cron');
}
