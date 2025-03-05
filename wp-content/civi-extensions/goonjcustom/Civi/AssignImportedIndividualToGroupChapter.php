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
			->addWhere('contact_id', '=', $contact_id)
			->addWhere('group_id', '=', $groupId)
			->execute()->first();

		if (!empty($groupContacts)) {
			return;
		}

		if ($groupId && $contactId) {
			$result = GroupContact::create(FALSE)
				->addValue('contact_id', $contact_id)
				->addValue('group_id', $groupId)
				->addValue('status', 'Added')
				->execute();
		}
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
