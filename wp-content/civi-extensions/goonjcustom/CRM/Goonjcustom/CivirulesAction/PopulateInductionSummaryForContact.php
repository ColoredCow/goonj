<?php

class CRM_Goonjcustom_CivirulesAction_PopulateInductionSummaryForContact extends CRM_Civirules_Action
{
    private function fetchCustomFieldsByGroupName($customGroupName)
    {
        $customGroupId = \Civi\Api4\CustomGroup::get(TRUE)
            ->addSelect('id')
            ->addWhere('name', '=', $customGroupName)
            ->setLimit(1)
            ->execute()
            ->first()['id'] ?? null;

        if (!$customGroupId) {
            throw new Exception("Custom group '$customGroupName' not found");
        }

        $customFields = \Civi\Api4\CustomField::get(TRUE)
            ->addWhere('custom_group_id', '=', $customGroupId)
            ->setLimit(0) // No limit on the number of custom fields retrieved
            ->execute()
            ->indexBy('name');

        if (!$customFields) {
            throw new Exception("No custom fields found for custom group ID: $customGroupId");
        }

        return $customFields;
    }

    private function fetchLocationTitle($locationId)
    {
        global $wpdb;

        $query = $wpdb->prepare("SELECT title FROM civicrm_eck_processing_center WHERE id = %d", $locationId);

        $title = $wpdb->get_var($query);

        if ($title !== null) {
            return $title;
        } else {
            throw new Exception('Location not found');
        }
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
        $customFields = $this->fetchCustomFieldsByGroupName($customGroupName);
        if (empty($customFields)) {
            return false;
        }

        $useCustomGroup = 'Induction_Fields';
        $customFields2 = $this->fetchCustomFieldsByGroupName($useCustomGroup);

        // Get the activity details including status, date, assignee, and location.
        $activity = civicrm_api3('Activity', 'getsingle', [
            'id' => $activityId,
            'return' => [
                'details',
                'status_id',
                'activity_date_time',
                'custom_' . $customFields2['Location']['id'],
                'target_contact_id'
            ],
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

        // Fetch location title using custom field dynamically
        $locationTitle = '';
        if (!empty($customFields2['Location'])) {
            $locationFieldName = 'custom_' . $customFields2['Location']['id'];
            if (!empty($activity[$locationFieldName])) {
                try {
                    $locationTitle = $this->fetchLocationTitle($activity[$locationFieldName]);
                } catch (Exception $e) {
                    error_log($e->getMessage());
                }
            }
        }

        $inductionValues = [
            'Induction_details' => $activity['details'],
            'Induction_status' => $status['label'],
            'Induction_date' => $activity['activity_date_time'],
            'Induction_Location' => $locationTitle,
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
