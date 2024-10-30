<?php

function gb_format_date($date, $format = 'd-m-Y') {
    if (!$date instanceof DateTime) {
        throw new InvalidArgumentException('Input must be a DateTime object');
    }
    return $date->format($format);
}

function gb_format_time_range($start_date, $end_date, $format = 'h:i A') {
    if (!$start_date instanceof DateTime || !$end_date instanceof DateTime) {
        throw new InvalidArgumentException('Both inputs must be DateTime objects');
    }
    if ($end_date <= $start_date) {
        throw new InvalidArgumentException('End time must be after start time');
    }
    return $start_date->format($format) . ' - ' . $end_date->format($format);
}
/**
 * Generate induction slots based on contact's location, registration date, and induction activity details.
 * 
 * @param int|null $contactId The ID of the contact for whom to generate slots.
 * @param int $days The number of days to look ahead for slot generation (default is 30 days).
 * @return array An array of induction slots or empty if criteria aren't met.
 */
function generate_induction_slots($contactId = null, $days = 30) {
    // Validate contactId
    if (empty($contactId) || !is_numeric($contactId)) {
        \Civi::log()->warning('Invalid contactId', ['contactId' => $contactId]);
        return [];
    }

    try {
        // Fetch necessary contact details for slot generation
        $contactData = \Civi\Api4\Contact::get(FALSE)
            ->addSelect('address_primary.state_province_id', 'address_primary.city', 'Individual_fields.Created_Date')
            ->addWhere('id', '=', $contactId)
            ->execute()->single();

        if (empty($contactData)) {
            \Civi::log()->warning('Contact not found', ['contactId' => $contactId]);
            return [];
        }

        // Retrieve all induction activities for the contact
        $inductionActivities = \Civi\Api4\Activity::get(FALSE)
            ->addSelect('id', 'activity_date_time', 'status_id', 'status_id:name', 'Induction_Fields.Goonj_Office')
            ->addWhere('source_contact_id', '=', $contactId)
            ->addWhere('activity_type_id:name', '=', 'Induction')
            ->execute();

        // Return if no induction activity found
        if ($inductionActivities->count() === 0) {
            \Civi::log()->info('No activity found for contact', ['contactId' => $contactId]);
            return [];
        }

        // If the latest induction activity status is 'Scheduled' or 'Completed', return the details
        $inductionActivity = $inductionActivities->first();
        if (in_array($inductionActivity['status_id:name'], ['Scheduled', 'Completed'])) {
            return [
                'status' => $inductionActivity['status_id:name'],
                'date' => (new DateTime($inductionActivity['activity_date_time']))->format('d-m-Y H:i')
            ];
        }

        $contactStateId = intval($contactData['address_primary.state_province_id']);
        $inductionSlotStartDate = (new DateTime($contactData['Individual_fields.Created_Date']))->modify('+1 day');
        $physicalInductionType = 'Processing_Unit';
        $onlineInductionType = 'Online_only_selected_by_Urban_P';

        // List of states with both physical and online inductions based on cities
        $statesWithMixedInductionTypes = \Civi\Api4\StateProvince::get(FALSE)
            ->addWhere('country_id.name', '=', 'India')
            ->addWhere('name', 'IN', ['Bihar', 'Jharkhand', 'Orissa'])
            ->setLimit(3)
            ->execute()
            ->column('id');

        $assignedOfficeId = $inductionActivity['Induction_Fields.Goonj_Office'];

        // Fetch already scheduled induction activities at the assigned Goonj office
        $scheduledActivities = \Civi\Api4\Activity::get(FALSE)
            ->addSelect('activity_date_time', 'Induction_Fields.Goonj_Office', 'id')
            ->addWhere('activity_type_id', '=', 57)
            ->addWhere('status_id', '=', 1)
            ->addWhere('Induction_Fields.Goonj_Office', '=', $assignedOfficeId)
            ->addWhere('activity_date_time', '>', (new DateTime('today midnight'))->format('Y-m-d H:i:s'))
            ->setLimit(30)
            ->execute();

        $slots = [];

        // Check for mixed induction type states and generate slots accordingly
        if (in_array($contactStateId, $statesWithMixedInductionTypes)) {
            $contactCity = isset($contact['address_primary.city']) ? strtolower($contact['address_primary.city']) : '';
            if (in_array($contactCity, ['patna', 'ranchi', 'bhubaneshwar'])) {
                return generate_slots($assignedOfficeId, 30, $scheduledActivities, $physicalInductionType, $inductionSlotStartDate);
            }

            return generate_slots($assignedOfficeId, 30, $scheduledActivities, $onlineInductionType, $inductionSlotStartDate);
        }

        // Determine if a Goonj office exists in the contact's state and schedule accordingly
        $officeContact = \Civi\Api4\Contact::get(FALSE)
            ->addSelect('id', 'display_name')
            ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
            ->addWhere('address_primary.state_province_id', '=', $contactStateId)
            ->execute();

        if ($officeContact->count() === 0) {
            return generate_slots($assignedOfficeId, 30, $scheduledActivities, $onlineInductionType, $inductionSlotStartDate);
        }

        return generate_slots($assignedOfficeId, 30, $scheduledActivities, $physicalInductionType, $inductionSlotStartDate);
    } catch (\Exception $e) {
        \Civi::log()->error('Error generating induction slots', [
            'contactId' => $contactId,
            'error' => $e->getMessage()
        ]);
        return [];
    }
}

/**
 * Generate specific induction slots based on induction type and scheduled activities.
 *
 * @param int $assignedOfficeId The office ID where slots are generated.
 * @param int $maxSlots The maximum number of slots to generate.
 * @param array $scheduledActivities List of already scheduled induction activities.
 * @param string $inductionType The type of induction ('Processing_Unit' or 'Online_only_selected_by_Urban_P').
 * @param DateTime $startDate The start date for generating slots.
 * @return array Array of generated slots with day, date, time, count, and type.
 */
function generate_slots($assignedOfficeId, $maxSlots, $scheduledActivities, $inductionType, $startDate) {
    $slots = [];
    $slotCount = 0;

    try {
        // Fetch office details for induction scheduling
        $officeDetails = \Civi\Api4\Contact::get(FALSE)
            ->addSelect('display_name', 'Goonj_Office_Details.Physical_Induction_Slot_Days:name', 'Goonj_Office_Details.Physical_Induction_Slot_Time', 'Goonj_Office_Details.Online_Induction_Slot_Days:name', 'Goonj_Office_Details.Online_Induction_Slot_Time')
            ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
            ->addWhere('id', '=', $assignedOfficeId)
            ->execute()->first();
        
        if(empty($officeDetails) || empty($assignedOfficeId)){
            \Civi::log()->info('Office Details not found', ['assignedOfficeId'=>$assignedOfficeId]);
            return FALSE;
        }

        $validInductionDays = ($inductionType === 'Processing_Unit')
            ? $officeDetails['Goonj_Office_Details.Physical_Induction_Slot_Days:name']
            : $officeDetails['Goonj_Office_Details.Online_Induction_Slot_Days:name'];

        $inductionTime = ($inductionType === 'Processing_Unit')
            ? $officeDetails['Goonj_Office_Details.Physical_Induction_Slot_Time']
            : $officeDetails['Goonj_Office_Details.Online_Induction_Slot_Time'];

        list($hour, $minute) = explode(':', $inductionTime);

        for ($i = 0; $slotCount < $maxSlots; $i++) {
            $date = (clone $startDate)->modify("+{$i} days");

            if (in_array($date->format('l'), $validInductionDays)) {
                $date->setTime((int)$hour, (int)$minute);
                $activityDate = $date->format('Y-m-d');
                $activityCount = 0;

                foreach ($scheduledActivities as $activity) {
                    if ((new DateTime($activity['activity_date_time']))->format('Y-m-d') === $activityDate) {
                        $activityCount++;
                    }
                }

                $slots[] = [
                    'day' => $date->format('l'),
                    'date' => $date->format('d-m-Y'),
                    'time' => $date->format('H:i'),
                    'activity_count' => $activityCount,
                    'induction_type' => $inductionType,
                ];
                $slotCount++;
            }
        }

        \Civi::log()->info('Activity slots created', ['slots' => $slots]);
        return $slots;
    } catch (\Exception $e) {
        \Civi::log()->error('Error generating slots', [
            'officeId' => $officeId,
            'error' => $e->getMessage()
        ]);
        return [];
    }
}
