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
        \Civi::log()->info('Invalid contactId', ['contactId' => $contactId]);
        return [];
    }

    try {
        // Fetch necessary contact details for slot generation
        $contactDetails = \Civi\Api4\Contact::get(FALSE)
            ->addSelect('address_primary.state_province_id', 'address_primary.city', 'Individual_fields.Created_Date')
            ->addWhere('id', '=', $contactId)
            ->addWhere('contact_sub_type', '=', 'Volunteer')
            ->execute();
        
        $contactData = $contactDetails->first();

        if (empty($contactData)) {
            \Civi::log()->info('Contact not found', ['contactId' => $contactId]);
            return;
        }

        // Retrieve induction activity for the contact
        $inductionActivity = \Civi\Api4\Activity::get(FALSE)
            ->addSelect('id', 'activity_date_time', 'status_id', 'status_id:name', 'Induction_Fields.Goonj_Office')
            ->addWhere('source_contact_id', '=', $contactId)
            ->addWhere('activity_type_id:name', '=', 'Induction')
            ->execute()->single();

        // Return if no induction activity found
        if (empty($inductionActivity)) {
            \Civi::log()->info('No activity found for contact', ['contactId' => $contactId]);
            return [];
        }

        // If the induction activity status is 'Scheduled' or 'Completed', return the details

        if (in_array($inductionActivity['status_id:name'], ['Scheduled', 'Completed', 'No_show', 'Cancelled'])) {
            return [
                'status' => $inductionActivity['status_id:name'],
                'date' => (new DateTime($inductionActivity['activity_date_time']))->format('d-m-Y H:i')
            ];
        }

        $contactStateId = intval($contactData['address_primary.state_province_id']);

        // Capitalize first letter of each word
        $contactCityFormatted = ucwords(strtolower($contactData['address_primary.city']));

        $inductionSlotStartDate = (new DateTime($contactData['Individual_fields.Created_Date']))->modify('+1 day');
        $physicalInductionType = 'Processing_Unit';
        $onlineInductionType = 'Online_only_selected_by_Urban_P';
        $defaultMaxSlot = 15;

        // List of states with both physical and online inductions based on cities
        $statesWithMixedInductionTypes = \Civi\Api4\StateProvince::get(FALSE)
            ->addWhere('country_id.name', '=', 'India')
            ->addWhere('name', 'IN', ['Bihar', 'Jharkhand', 'Orissa'])
            ->execute()
            ->column('id');

        $assignedOfficeId = $inductionActivity['Induction_Fields.Goonj_Office'];

        // Check for mixed induction type states and generate slots accordingly
        if (in_array($contactStateId, $statesWithMixedInductionTypes)) {
            $contactCity = isset($contactData['address_primary.city']) ? strtolower($contactData['address_primary.city']) : '';
            if (in_array($contactCity, ['patna', 'ranchi', 'bhubaneshwar'])) {
                // generate physical slot for these cities
                return generate_slots($assignedOfficeId, $defaultMaxSlot, $physicalInductionType, $inductionSlotStartDate);
            }
            // generate online induction slots for other cities
            return generate_slots($assignedOfficeId, $defaultMaxSlot, $onlineInductionType, $inductionSlotStartDate);
        }

        $officeContact = \Civi\Api4\Contact::get(FALSE)
            ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
            ->addClause('OR', ['Goonj_Office_Details.Other_Induction_Cities', 'CONTAINS', $contactCityFormatted], ['address_primary.city', 'CONTAINS', $contactCityFormatted])
            ->execute();
        
        if ($officeContact->count() === 0) {
            // Generate online induction slots for state having no office
            return generate_slots($assignedOfficeId, $defaultMaxSlot, $onlineInductionType, $inductionSlotStartDate);
        }
        $officeDetails = $officeContact->first();

        if (!empty($officeDetails)) {
            return generate_slots($assignedOfficeId, $defaultMaxSlot, $physicalInductionType, $inductionSlotStartDate);
        } else {
            return generate_slots($assignedOfficeId, $defaultMaxSlot, $onlineInductionType, $inductionSlotStartDate);
        }        
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
function generate_slots($assignedOfficeId, $maxSlots, $inductionType, $startDate) {
    $slots = [];
    $slotCount = 0;
    $highActivityCountDays = 0;

    try {
        // Fetch office induction details for induction scheduling
        $officeDetails = \Civi\Api4\Contact::get(FALSE)
            ->addSelect('display_name', 'Goonj_Office_Details.Physical_Induction_Slot_Days:name', 'Goonj_Office_Details.Physical_Induction_Slot_Time', 'Goonj_Office_Details.Online_Induction_Slot_Days:name', 'Goonj_Office_Details.Online_Induction_Slot_Time', 'Goonj_Office_Details.Holiday_Dates')
            ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
            ->addWhere('id', '=', $assignedOfficeId)
            ->execute()->single();
        
        if (empty($officeDetails) || empty($assignedOfficeId)) {
            \Civi::log()->info('Office Details not found', ['assignedOfficeId' => $assignedOfficeId]);
            return FALSE;
        }

        $holidayDates = $officeDetails['Goonj_Office_Details.Holiday_Dates'];
        // Determine valid induction days and time based on induction type
        $validInductionDays = ($inductionType === 'Processing_Unit')
            ? $officeDetails['Goonj_Office_Details.Physical_Induction_Slot_Days:name']
            : $officeDetails['Goonj_Office_Details.Online_Induction_Slot_Days:name'];

        $inductionTime = ($inductionType === 'Processing_Unit')
            ? $officeDetails['Goonj_Office_Details.Physical_Induction_Slot_Time']
            : $officeDetails['Goonj_Office_Details.Online_Induction_Slot_Time'];

        list($hour, $minute) = explode(':', $inductionTime);

        // Fetch already scheduled induction activities at the assigned Goonj office
        $scheduledActivities = \Civi\Api4\Activity::get(FALSE)
            ->addSelect('activity_date_time')
            ->addWhere('activity_type_id:name', '=', 'Induction')
            ->addWhere('status_id:name', '=', 'Scheduled')
            ->addWhere('Induction_Fields.Goonj_Office', '=', $assignedOfficeId)
            ->addWhere('activity_date_time', '>', (new DateTime('today midnight'))->format('Y-m-d H:i:s'))
            ->execute();

        // Create a set of scheduled activity dates for quick lookup
        $scheduledActivityDates = [];
        foreach ($scheduledActivities as $activity) {
            $scheduledActivityDates[] = (new DateTime($activity['activity_date_time']))->format('Y-m-d');
        }

        // Generate slots
        generateActivitySlots($slots, $maxSlots, $validInductionDays, $hour, $minute, $startDate, $scheduledActivityDates, $slotCount, $highActivityCountDays, $inductionType, $holidayDates);

        if ($highActivityCountDays >= 8) {
            $slotCount = 0;
            $startDate = new DateTime(end($slots)['date']);
            generateActivitySlots($slots, $highActivityCountDays, $validInductionDays, $hour, $minute, $startDate, $scheduledActivityDates, $slotCount, $highActivityCountDays, $inductionType, $holidayDates);
        }

        return $slots;
    } catch (\Exception $e) {
        \Civi::log()->error('Error generating slots', [
            'officeId' => $assignedOfficeId,
            'error' => $e->getMessage()
        ]);
        return [];
    }
}

function generateActivitySlots(&$slots, $maxSlots, $validInductionDays, $hour, $minute, $startDate, $scheduledActivityDates, &$slotCount, &$highActivityCountDays, $inductionType, $holidayDates) {
    $maxDays = 365;
    $holidayDatesArray = [];
    \Civi::log()->info('startDate', ['startDate'=>$startDate]);
    $currentDate = new DateTime();

    // // Ensure start date is not in the past
    if ($startDate < $currentDate) {
        $startDate = clone $currentDate;
    }

    if(!empty($holidayDates)){
        $holidayDatesArray = array_map('trim', explode(',', $holidayDates));
    }

    for ($i = 0; $i < $maxDays && $slotCount < $maxSlots; $i++) {
        $date = (clone $startDate)->modify("+{$i} days");
        $dayName = $date->format('l');

        if (in_array($dayName, $validInductionDays)) {
            $date->setTime((int)$hour, (int)$minute);
            $activityDate = $date->format('Y-m-d');

            // Skip the slot date if any date in the holiday dates array
            if(in_array($activityDate, $holidayDatesArray)){
                continue;
            }

            // Determine activity count based on scheduled activities
            $activityCount = count(array_filter($scheduledActivityDates, fn($scheduledActivityDate) => $scheduledActivityDate === $activityDate));

            if ($activityCount < 20){
                $slots[] = [
                    'day' => $dayName,
                    'date' => $date->format('d-m-Y'),
                    'time' => $date->format('h:i A'),
                    'activity_count' => $activityCount,
                    'induction_type' => $inductionType,
                ];
            }
            $slotCount++;

            // Count days with activity count greater than 20
            if ($activityCount > 20) {
                $highActivityCountDays++;
            }
        }
    }
}
