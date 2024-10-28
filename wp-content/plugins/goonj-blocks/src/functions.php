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

    $contact = \Civi\Api4\Contact::get(FALSE)
        ->addSelect('address_primary.state_province_id', 'address_primary.city')
        ->addWhere('id', '=', $source_contact_id)
        ->execute()->single();

    if (empty($contact)) {
        \Civi::log()->warning('Contact not found', ['source_contact_id' => $source_contact_id]);
        return [];
    }

    $activities = \Civi\Api4\Activity::get(FALSE)
        ->addSelect('id', 'activity_date_time', 'status_id', 'status_id:name', 'Induction_Fields.Goonj_Office')
        ->addWhere('source_contact_id', '=', $source_contact_id)
        ->addWhere('activity_type_id:name', '=', 'Induction')
        ->execute();

    if ($activities->count() === 0) {
        \Civi::log()->info('No activity found for contact', ['source_contact_id' => $source_contact_id]);
        return [];
    }

    $inductionActivity = $activities->first();
    if (in_array($inductionActivity['status_id:name'], ['Scheduled', 'Completed'])) {
        return [
            'status' => $inductionActivity['status_id:name'],
            'date' => (new DateTime($inductionActivity['activity_date_time']))->format('d-m-Y H:i')
        ];
    }

    $contactStateId = intval($contact['address_primary.state_province_id']);
    $physicalInductionType = 'Proceesing_Unit';
    $onlineInductionType = 'Online_only_selected_by_Urban_P';
    $stateProvinces = \Civi\Api4\StateProvince::get(FALSE)
        ->addWhere('country_id.name', '=', 'India')
        ->addWhere('name', 'IN', ['Bihar', 'Jharkhand', 'Orissa'])
        ->setLimit(3)
        ->execute()
        ->column('id');

    $contactOfficeId = $inductionActivity['Induction_Fields.Goonj_Office'];
    $scheduledActivities = \Civi\Api4\Activity::get(FALSE)
        ->addSelect('activity_date_time', 'Induction_Fields.Goonj_Office', 'id')
        ->addWhere('activity_type_id', '=', 57)
        ->addWhere('status_id', '=', 1)
        ->addWhere('Induction_Fields.Goonj_Office', '=', $contactOfficeId)
        ->addWhere('activity_date_time', '>', (new DateTime('today midnight'))->format('Y-m-d H:i:s'))
        ->setLimit(30)
        ->execute();

    $slots = [];

    if (in_array($contactStateId, $stateProvinces)) {
        // Check if city name exists and convert it to lowercase
        $contactCity = isset($contact['address_primary.city']) ? strtolower($contact['address_primary.city']) : '';

        // Check if the city is one of the specified cities
        if (in_array($contactCity, ['patna', 'ranchi', 'bhubaneshwar'])) {
            return generate_slots(['Tuesday', 'Thursday', 'Saturday'], 4, 30, $scheduledActivities, $physicalInductionType);
        }

        return generate_slots('Friday', 4, 30, $scheduledActivities, $onlineInductionType);
    }

    $officeContact = \Civi\Api4\Contact::get(FALSE)
        ->addSelect('id', 'display_name')
        ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
        ->addWhere('address_primary.state_province_id', '=', $contactStateId)
        ->execute();

    if ($officeContact->count() === 0) {
        return generate_slots('Wednesday', 4, 30, $scheduledActivities, $onlineInductionType);
    }

    return generate_slots(['Tuesday', 'Thursday', 'Saturday'], 4, 30, $scheduledActivities, $physicalInductionType);
}

function generate_slots($validDays, $timeHour, $maxSlots, $scheduledActivities, $inductionType) {
    $slots = [];
    $slotCount = 0;
    $validDays = (array)$validDays;

    for ($i = 0; $slotCount < $maxSlots; $i++) {
        $date = (new DateTime())->modify("+{$i} days");

        if (in_array($date->format('l'), $validDays)) {
            $activityDate = $date->format('Y-m-d');
            $date->setTime($timeHour, 0);
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
}
