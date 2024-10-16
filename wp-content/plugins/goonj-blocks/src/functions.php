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
function generate_induction_slots($days = 30) {
    $slots = [];
  
    // Define the available times for induction sessions (e.g., 9 AM, 2 PM, 4 PM).
    $available_times = ['09:00', '14:00', '16:00'];
  
    // Loop through the next 30 days to generate time slots.
    for ($i = 0; $i < $days; $i++) {
      $date = new DateTime();
      $date->modify("+{$i} days");
  
      // For each date, create slots for each available time.
      foreach ($available_times as $time) {
        // Clone to avoid modifying the original date.
        $slot = clone $date;
        $slot->setTime((int) substr($time, 0, 2), (int) substr($time, 3, 2));
  
        $slots[] = [
        // Day of the week.
          'day' => $slot->format('l'),
        // Date.
          'date' => $slot->format('d-m-Y'),
        // Time.
          'time' => $slot->format('H:i'),
        ];
      }
    }
  
    return $slots;
  }