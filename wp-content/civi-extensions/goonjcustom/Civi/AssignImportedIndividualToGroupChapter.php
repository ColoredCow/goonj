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

		// Clear CiviCRM caches so the new chapter-group assignment is immediately
		// visible in forms/ACLs — fix for ColoredCow/goonj-crm#269. Skipped during
		// bulk imports because the Contact Import Parser fires this hook per row
		// and a full System.flush per row makes 6K+ imports unusable and blocks
		// concurrent users. After an import, caches refresh naturally on the
		// next admin action or can be cleared manually.
		if (!self::isRunningInBulkImport()) {
			civicrm_api3('System', 'flush', []);
		}
	}

	/**
	 * Returns TRUE when this hook is firing inside a CiviCRM bulk-import run
	 * (CRM_Contact_Import_Parser_Contact::createContact in the call stack).
	 * Checked via backtrace because userJobID is not populated in session
	 * during the AJAX queue-runner path.
	 */
	private static function isRunningInBulkImport(): bool {
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20) as $frame) {
			$class = $frame['class'] ?? '';
			if ($class !== '' && str_contains($class, 'Import_Parser')) {
				return TRUE;
			}
		}
		return FALSE;
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
