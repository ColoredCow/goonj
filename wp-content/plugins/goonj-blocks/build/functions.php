<?php

function gb_format_date($date) {
    return $date->format('d-m-Y');
}

function gb_format_time_range($start_date, $end_date) {
    return $start_date->format('h:i A') . ' - ' . $end_date->format('h:i A');
}