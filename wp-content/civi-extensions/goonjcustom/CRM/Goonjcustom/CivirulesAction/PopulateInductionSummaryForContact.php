<?php
class CRM_Goonjcustom_CivirulesAction_PopulateInductionSummaryForContact extends CRM_Civirules_Action
{
	private function fetchCustomFieldsInGroup($customGroupId)
	{
		$params = [
			'sequential' => 1,
			'custom_group_id' => $customGroupId,
			'options' => ['limit' => 0], // No limit on results
		];
		$result = civicrm_api3('CustomField', 'get', $params);

		if ($result['is_error']) {
			throw new Exception('Error fetching custom fields: ' . $result['error_message']);
		}

		return $result['values'];
	}

	private function fetchCustomGroupByName($customGroupName)
	{
		$params = [
			'sequential' => 1,
			'name' => $customGroupName,
		];
		$result = civicrm_api3('CustomGroup', 'get', $params);

		if ($result['is_error']) {
			throw new Exception('Error fetching custom group: ' . $result['error_message']);
		}

		if (empty($result['values'])) {
			throw new Exception('No custom group found with the name: ' . $customGroupName);
		}

		return reset($result['values']);
	}

	/**
	 * Method processAction to execute the action
	 * This action it to populate contact's activity (induction type) details to showcase in volunteer activity summary page
	 *
	 * @param CRM_Civirules_TriggerData_TriggerData $triggerData
	 * @access public
	 */
	public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData)
	{

		$activityId = $triggerData->getEntityId();
		if (!$activityId) {
			return;
		}

		$customGroupName = 'Volunteer_Induction_Summary';
		$customGroup = $this->fetchCustomGroupByName($customGroupName);
		if (empty($customGroup)) {
			return false;
		}

		$customFields = $this->fetchCustomFieldsInGroup($customGroup['id']);

		// Get the activity details including status, date, assignee, and location.
		$activity = civicrm_api3('Activity', 'getsingle', [
			'id' => $activityId,
			'return' => ['details', 'status_id', 'activity_date_time', 'location', 'target_contact_id'],
		]);

		// Fetch the assignee details using ActivityContact API.
		$assigneeContacts = civicrm_api3('ActivityContact', 'get', [
			'activity_id' => $activityId,
			'record_type_id' => 1, // Assignee role
		]);

		$assignees = [];
		if (!empty($assigneeContacts['values'])) {
			foreach ($assigneeContacts['values'] as $assigneeContact) {
				$assignee = civicrm_api3('Contact', 'getsingle', [
					'id' => $assigneeContact['contact_id'],
					'return' => ['display_name'],
				]);
				$assignees[] = $assignee['display_name'];
			}
		}
		$activityAssigneeNames = implode(', ', $assignees);

		// Fetch the activity status label
		$status = civicrm_api3('OptionValue', 'getsingle', [
			'option_group_id' => 'activity_status',
			'value' => $activity['status_id'],
		]);

		$inductionValues = [
			'Induction_details' => $activity['details'],
			'Induction_status' => $status['label'],
			'Induction_date' => $activity['activity_date_time'],
			'Induction_Location' => $activity['location'],
			'Induction_assignee' => $activityAssigneeNames,
		];

		$targetContactId = $activity['target_contact_id'][0];
		if (!$targetContactId) {
			return;
		}

		$params = ['id' => (int) $targetContactId];
		$targetedCustomFields = ['Induction_status', 'Induction_Location', 'Induction_assignee', 'Induction_date', 'Induction_details'];

		foreach ($customFields as $customField) {
			if (in_array($customField['name'], $targetedCustomFields)) {
				$params['custom_' . $customField['id']] = strip_tags($inductionValues[$customField['name']]);
			}
		}

		civicrm_api3('Contact', 'create', $params);
		return true;
	}

	/**
	 * Method to return the url for additional form processing for action
	 * and return false if none is needed
	 *
	 * @param int $ruleActionId
	 * @return bool
	 * @access public
	 */
	public function getExtraDataInputUrl($ruleActionId)
	{
		return false;
	}
}
