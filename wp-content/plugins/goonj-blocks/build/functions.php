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
 *
 */
function generate_induction_slots($source_contact_id = null, $days = 30) {
    if (empty($source_contact_id) || !is_numeric($source_contact_id)) {
        \Civi::log()->warning('Invalid source_contact_id', ['source_contact_id' => $source_contact_id]);
        return [];
    }

    // Fetch the contact's state information
    $contact = \Civi\Api4\Contact::get(FALSE)
        ->addSelect('address_primary.state_province_id', 'address_primary.city', 'address_primary.state_province_id:label')
        ->addWhere('id', '=', $source_contact_id)
        ->execute()->single();

    if (empty($contact)) {
        \Civi::log()->warning('Contact not found', ['source_contact_id' => $source_contact_id]);
        return [];
    }

    // Fetch the inuction activity for the contact
    $activities = \Civi\Api4\Activity::get(FALSE)
        ->addSelect('id', 'activity_date_time', 'status_id', 'status_id:name')
        ->addWhere('source_contact_id', '=', $source_contact_id)
        ->addWhere('activity_type_id:name', '=', 'Induction')
        ->execute();

    // If no activities found, exit
    if ($activities->count() === 0) {
        \Civi::log()->info('No activity found for contact', ['source_contact_id' => $source_contact_id]);
        return;
    }

    $inductionActivity = $activities->first();
    $contactInductionStatus = $inductionActivity['status_id:name'];

    // Check if the induction status is 'Scheduled' or 'Completed'
    if (in_array($contactInductionStatus, ['Scheduled', 'Completed'])) {
        $scheduledDateTime = new DateTime($inductionActivity['activity_date_time']);
        $formattedDateTime = $scheduledDateTime->format('d-m-Y H:i');
        $statusLabel = $contactInductionStatus === 'Scheduled' ? 'Scheduled' : 'Completed';

        // Return or display the induction details based on the status
        return [
            'status' => $statusLabel,
            'date' => $formattedDateTime
        ];
    }

    $contactStateId = intval($contact['address_primary.state_province_id']);

    // Fetch Goonj Office in the same state
    $officeContact = \Civi\Api4\Contact::get(FALSE)
        ->addSelect('id', 'display_name')
        ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
        ->addWhere('address_primary.state_province_id', '=', $contactStateId)
        ->execute();

    if ($officeContact->count() === 0) {
        \Civi::log()->warning('No Goonj Office found for contact state', ['contact_state_id' => $contactStateId]);
    }

    $officeContactId = $officeContact['id'];

    $startOfDay = new DateTime('today midnight');
    $endOfRange = (new DateTime('today midnight'))->modify("+{$days} days");

    // Initialize the array for storing date counts
    $activityDates = [];

    // Fetch activities related to the Goonj Office
    $activities = \Civi\Api4\Activity::get(FALSE)
        ->addSelect('activity_date_time', 'Induction_Fields.Goonj_Office', 'id')
        ->addWhere('activity_type_id', '=', 57) // Induction type activities
        ->addWhere('status_id', '=', 1) // Only scheduled activities
        ->addWhere('Induction_Fields.Goonj_Office', '=', $officeContactId)
        ->addWhere('activity_date_time', '>', $startOfDay->format('Y-m-d H:i:s'))
        ->setLimit(30)
        ->execute();

    // Initialize the array for Physical Induction days (Tuesdays, Thursdays, Saturdays)
    $validDays = ['Tuesday', 'Thursday', 'Saturday'];

    $slots = [];
    $slotCount = 0;

    // Loop through the next X days and create a slot for each valid day (Tuesday, Thursday, Saturday)
    for ($i = 0; $slotCount < 30; $i++) {
        $date = (new DateTime())->modify("+{$i} days");

        // Check if the current day is a valid day (Tuesday, Thursday, or Saturday)
        if (in_array($date->format('l'), $validDays)) {
            $activityDate = $date->format('Y-m-d');

            // Set time to 4 PM
            $date->setTime(16, 0); // 4:00 PM

            // Count occurrences of activities for this date
            $activityCount = 0;
            foreach ($activities as $activity) {
                $activityDateTime = $activity['activity_date_time'];
                $activityDateFormatted = (new DateTime($activityDateTime))->format('Y-m-d');

                if ($activityDateFormatted === $activityDate) {
                    $activityCount++;
                }
            }

            // Add the slot to the slots array
            $slots[] = [
                'day' => $date->format('l'),
                'date' => $date->format('d-m-Y'),
                'time' => $date->format('H:i'),
                'activity_count' => $activityCount, // Number of activities scheduled for this date
            ];

            $slotCount++;
        }
    }

    \Civi::log()->info('Activity slots created', ['slots' => $slots]);

    return $slots;
}




