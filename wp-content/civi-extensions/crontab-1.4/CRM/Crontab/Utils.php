<?php
use CRM_Crontab_ExtensionUtil as E;

class CRM_Crontab_Utils {

  public static function basic() {
    $list = [
      "10" => "Every 5 minutes",
      "11" => "Every 10 minutes",
      "1" => "Every 15 minutes",
      "2" => "Every 30 minutes",
      "3" => "Every hour",
      "4" => "Every 6 hours",
      "5" => "Every 12 hours",
      "6" => "Every day",
      "7" => "Every week",
      "8" => "Every month",
    ];

    return $list;
  }

  public static function hours() {
    $list = [
      "*" => "Every hour",
      "*/2" => "Every 2 hours",
      "*/3" => "Every 3 hours",
      "*/4" => "Every 4 hours",
      "*/6" => "Every 6 hours",
      "*/8" => "Every 8 hours",
      "*/12" => "Every 12 hours",
      "0" => "00",
      "1" => "01",
      "2" => "02",
      "3" => "03",
      "4" => "04",
      "5" => "05",
      "6" => "06",
      "7" => "07",
      "8" => "08",
      "9" => "09",
      "10" => "10",
      "11" => "11",
      "12" => "12",
      "13" => "13",
      "14" => "14",
      "15" => "15",
      "16" => "16",
      "17" => "17",
      "18" => "18",
      "19" => "19",
      "20" => "20",
      "21" => "21",
      "22" => "22",
      "23" => "23"
    ];

    return $list;
  }

  public static function minute() {
    $list = [
      "*" => "Every minute",
      "*/2" => "Every 2 minutes",
      "*/3" => "Every 3 minutes",
      "*/4" => "Every 4 minutes",
      "*/5" => "Every 5 minutes",
      "*/10" => "Every 10 minutes",
      "4,19,34,49" => "Every 15 minutes",
      "11,41" => "Every 30 minutes",
      "56" => "Every 60 minutes",
      "0" => "00",
      "1" => "01",
      "2" => "02",
      "3" => "03",
      "4" => "04",
      "5" => "05",
      "6" => "06",
      "7" => "07",
      "8" => "08",
      "9" => "09",
      "10" => "10",
      "11" => "11",
      "12" => "12",
      "13" => "13",
      "14" => "14",
      "15" => "15",
      "16" => "16",
      "17" => "17",
      "18" => "18",
      "19" => "19",
      "20" => "20",
      "21" => "21",
      "22" => "22",
      "23" => "23",
      "24" => "24",
      "25" => "25",
      "26" => "26",
      "27" => "27",
      "28" => "28",
      "29" => "29",
      "30" => "30",
      "31" => "31",
      "32" => "32",
      "33" => "33",
      "34" => "34",
      "35" => "35",
      "36" => "36",
      "37" => "37",
      "38" => "38",
      "39" => "39",
      "40" => "40",
      "41" => "41",
      "42" => "42",
      "43" => "43",
      "44" => "44",
      "45" => "45",
      "46" => "46",
      "47" => "47",
      "48" => "48",
      "49" => "49",
      "50" => "50",
      "51" => "51",
      "52" => "52",
      "53" => "53",
      "54" => "54",
      "55" => "55",
      //"56" => "56",
      "57" => "57",
      "58" => "58",
      "59" => "59",
    ];

    return $list;
  }

  public static function days() {
    $list = [
      "*" => "Every day",
      "*/2" => "Every 2 days",
      "*/3" => "Every 3 days",
      "*/7" => "Every 7 days",
      "*/15" => "Every 15 days",
      "1" => "1",
      "2" => "2",
      "3" => "3",
      "4" => "4",
      "5" => "5",
      "6" => "6",
      "7" => "7",
      "8" => "8",
      "9" => "9",
      "10" => "10",
      "11" => "11",
      "12" => "12",
      "13" => "13",
      "14" => "14",
      "15" => "15",
      "16" => "16",
      "17" => "17",
      "18" => "18",
      "19" => "19",
      "20" => "20",
      "21" => "21",
      "22" => "22",
      "23" => "23",
      "24" => "24",
      "25" => "25",
      "26" => "26",
      "27" => "27",
      "28" => "28",
      "29" => "29",
      "30" => "30",
      "31" => "31",
    ];

    return $list;
  }

  public static function month() {
    $list = [
      "*" => "Every month",
      "*/2" => "Every 2 months",
      "*/3" => "Every 3 months",
      "*/6" => "Every 6 months",
      "1" => "January",
      "2" => "February",
      "3" => "March",
      "4" => "April",
      "5" => "May",
      "6" => "June",
      "7" => "July",
      "8" => "August",
      "9" => "September",
      "10" => "October",
      "11" => "November",
      "12" => "December",
    ];

    return $list;
  }

  public static function weekdays() {
    $list = [
      "*" => "Every day",
      "1" => "Monday",
      "2" => "Tuesday",
      "3" => "Wednesday",
      "4" => "Thursday",
      "5" => "Friday",
      "6" => "Saturday",
      "0" => "Sunday",
    ];

    return $list;
  }

  public static function getSettings($jobID) {
    $result = civicrm_api3('Job', 'getsingle', [
      'sequential' => 1,
      'return' => ["crontab_apply", "crontab_frequency", "crontab_offset",
        "crontab_hour_range", "crontab_date_time_start", 'crontab_date_time_end',
        'crontab_time_from', 'crontab_time_to'],
      'id' => $jobID,
    ]);
    unset($result['id']);
    if (!empty($result['crontab_frequency'])) {
      $jobParts = explode(' ', trim($result['crontab_frequency']));

      $parts = ['minute', 'hour', 'day', 'month', 'weekday'];
      $result = array_merge($result, array_combine($parts, $jobParts));
      foreach ($result as $part => &$value) {
        if (strpos($value, '-') !== FALSE && in_array($part, $parts)) {
          $value = preg_replace_callback('/(\d+)-(\d+)/', function ($m) {
            return implode(',', range($m[1], $m[2]));
          }, $value);
        }
      }
    }

    return $result;
  }

  public static function _getJobs() {
    $jobs = [];
    $dao = new CRM_Core_DAO_Job();
    $dao->orderBy('name');
    $dao->domain_id = CRM_Core_Config::domainID();
    $dao->find();
    while ($dao->fetch()) {
      $temp = [];
      CRM_Core_DAO::storeValues($dao, $temp);
      $jobs[$dao->id] = new CRM_Crontab_ScheduledJob($temp);
    }

    return $jobs;
  }
}
