<?php

require_once 'crontab.civix.php';
// phpcs:disable
use CRM_Crontab_ExtensionUtil as E;
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function crontab_civicrm_config(&$config) {
  _crontab_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function crontab_civicrm_install() {
  _crontab_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function crontab_civicrm_enable() {
  _crontab_civix_civicrm_enable();
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 */
//function crontab_civicrm_preProcess($formName, &$form) {
//
//}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
//function crontab_civicrm_navigationMenu(&$menu) {
//  _crontab_civix_insert_navigation_menu($menu, 'Mailings', array(
//    'label' => E::ts('New subliminal message'),
//    'name' => 'mailing_subliminal_message',
//    'url' => 'civicrm/mailing/subliminal',
//    'permission' => 'access CiviMail',
//    'operator' => 'OR',
//    'separator' => 0,
//  ));
//  _crontab_civix_navigationMenu($menu);
//}

function crontab_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Admin_Form_Job') {
    $month = CRM_Crontab_Utils::month();
    $days = CRM_Crontab_Utils::days();
    $weekdays = CRM_Crontab_Utils::weekdays();
    $hours = CRM_Crontab_Utils::hours();
    $minutes = CRM_Crontab_Utils::minute();
    $moment = CRM_Crontab_Utils::basic();
    $select2style = [
      'multiple' => TRUE,
      'style' => 'width: 100%; max-width: 60em;height:220px',
      'class' => 'crm-select2',
      //'placeholder' => ts('- select -'),
    ];

    $form->add('advcheckbox', 'crontab_apply', ts('Advanced Job Scheduling'));
    $form->add('select', 'basic_crontab', ts('Schedule Time'), ['' => 'Advanced Settings'] + $moment, FALSE);
    $form->add('select', 'hour', ts('Hour'), $hours, FALSE, $select2style);
    $form->add('advcheckbox', 'crontab_hour_range', ts('Use Hour Range'));
    $form->add('advcheckbox', 'crontab_day_range', ts('Use Day Range'));
    $form->add('select', 'minute', ts('Minute'), $minutes, FALSE);
    $form->add('select', 'day', ts('Day'), $days, FALSE, $select2style);
    $form->add('select', 'month', ts('Month'), $month, FALSE, $select2style);
    $form->add('select', 'weekday', ts('Day of week'), $weekdays, FALSE, $select2style);
    $form->add('text', 'crontab_frequency', ts('New Run Frequency'));
    $form->add('text', 'crontab_offset', ts('Run Frequency Time Margin'));


    $form->add('datepicker', 'crontab_date_time_start', ts('Scheduled Start Date'), NULL, FALSE, ['time' => TRUE]);
    $form->add('datepicker', 'crontab_date_time_end', ts('Scheduled End Date'), NULL, FALSE, ['time' => TRUE]);

    $form->add('datepicker', 'crontab_time_from', ts('Active From'), NULL, FALSE, ['date' => FALSE, 'time' => TRUE]);
    $form->add('datepicker', 'crontab_time_to', ts('Active To'), NULL, FALSE, ['date' => FALSE, 'time' => TRUE]);

    if ($form->_action & CRM_Core_Action::UPDATE || $form->_action & CRM_Core_Action::VIEW) {
      $jobExtras = CRM_Crontab_Utils::getSettings($form->getVar('_id'));
      if (!empty($jobExtras)) {
        //$jobExtras['crontab_apply'] = 1;
        if (empty($jobExtras['crontab_offset'])) {
          $jobExtras['crontab_offset'] = 5;
        }
        if (!empty($jobExtras['crontab_hour_range'])) {
          $jobExtras['crontab_hour_range'] = 1;
        }
        if (!empty($jobExtras['crontab_day_range'])) {
          $jobExtras['crontab_day_range'] = 1;
        }
        $form->setDefaults($jobExtras);
      }
    }
    if ($form->_action & CRM_Core_Action::ADD) {
      $jobExtras = [];
      $jobExtras['crontab_offset'] = 5;
      $form->setDefaults($jobExtras);
    }
  }
}

function crontab_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if ($formName == 'CRM_Admin_Form_Job') {
    if (!empty($fields['crontab_apply']) && empty($fields['basic_crontab'])) {
      if (empty($fields['hour'])) {
        $errors['hour'] = ts("Hour is required field.");
      }
      if (empty($fields['day'])) {
        $errors['day'] = ts("Day is required field.");
      }
      if (empty($fields['month'])) {
        $errors['month'] = ts("Month is required field.");
      }
      if (empty($fields['weekday'])) {
        $errors['weekday'] = ts("Day of week is required field.");
      }

      if (!empty($fields['crontab_hour_range']) && !empty($fields['hour'])) {
        $nonints = preg_grep('/\D/', $fields['hour']);

        if (!empty($nonints)) {
          $errors['hour'] = ts("For Hour range select only plain hours.");
        }
      }
      if (!empty($fields['crontab_day_range']) && !empty($fields['day'])) {
        $nonints = preg_grep('/\D/', $fields['day']);

        if (!empty($nonints)) {
          $errors['day'] = ts("For Day range select only plain days.");
        }
      }
    }
    if (!empty($fields['crontab_apply'])) {
      if (!empty($fields['crontab_time_from']) && empty($fields['crontab_time_to'])) {
        $errors['crontab_time_to'] = ts("Set Time for Active To.");
      }
      if (empty($fields['crontab_time_from']) && !empty($fields['crontab_time_to'])) {
        $errors['crontab_time_from'] = ts("Set Time for Active From.");
      }
    }
  }
}

/**
 * Implements hook_civicrm_apiWrappers().
 */
function crontab_civicrm_entityTypes(&$entityTypes) {
  $civiVersion = CRM_Utils_System::version();
  $entity = 'CRM_Core_DAO_Job';
  if (version_compare($civiVersion, '5.75.0') >= 0) {
    $entity = 'Job';
  }
  $entityTypes[$entity]['fields_callback'][]
    = function ($class, &$fields) {
    $fields['crontab_apply'] = [
      'name' => 'crontab_apply',
      'type' => CRM_Utils_Type::T_BOOLEAN,
      'title' => ts('Activate crontab'),
      'description' => 'Activate crontab',
      'table_name' => 'civicrm_job',
      'entity' => 'Job',
      'bao' => 'CRM_Core_BAO_Job',
      'localizable' => 0,
    ];
    $fields['crontab_frequency'] = [
      'name' => 'crontab_frequency',
      'type' => CRM_Utils_Type::T_STRING,
      'title' => ts('Cront Tab Frequency'),
      'description' => 'Cront Tab Frequency, this overwrite civicrm default frequency time',
      'table_name' => 'civicrm_job',
      'entity' => 'Job',
      'bao' => 'CRM_Core_BAO_Job',
      'localizable' => 0,
    ];

    $fields['crontab_offset'] = [
      'name' => 'crontab_offset',
      'type' => CRM_Utils_Type::T_INT,
      'title' => ts('Cron Tab offset'),
      'description' => 'Offset margine to consider job eligiable for run.',
      'table_name' => 'civicrm_job',
      'entity' => 'Job',
      'bao' => 'CRM_Core_BAO_Job',
      'localizable' => 0,
    ];
    $fields['crontab_hour_range'] = [
      'name' => 'crontab_hour_range',
      'type' => CRM_Utils_Type::T_BOOLEAN,
      'title' => ts('Hour Range'),
      'description' => 'Use Hour range to execute job',
      'table_name' => 'civicrm_job',
      'entity' => 'Job',
      'bao' => 'CRM_Core_BAO_Job',
      'localizable' => 0,
    ];

    $fields['crontab_day_range'] = [
      'name' => 'crontab_day_range',
      'type' => CRM_Utils_Type::T_BOOLEAN,
      'title' => ts('Day Range'),
      'description' => 'Use Day range to execute job',
      'table_name' => 'civicrm_job',
      'entity' => 'Job',
      'bao' => 'CRM_Core_BAO_Job',
      'localizable' => 0,
    ];

    $fields['crontab_date_time_start'] = [
      'name' => 'crontab_date_time_start',
      'type' => CRM_Utils_Type::T_TIMESTAMP,
      'title' => ts('Date & Time Start'),
      'description' => ts('When is this cron job should start to run'),
      'required' => FALSE,
      'where' => 'civicrm_job.crontab_date_time_start',
      'default' => 'NULL',
      'table_name' => 'civicrm_job',
      'entity' => 'Job',
      'bao' => 'CRM_Core_BAO_Job',
      'localizable' => 0,
      'html' => [
        'label' => ts("Date & Time Start"),
      ]
    ];
    $fields['crontab_date_time_end'] = [
      'name' => 'crontab_date_time_end',
      'type' => CRM_Utils_Type::T_TIMESTAMP,
      'title' => ts('Date & Time End'),
      'description' => ts('When is this cron job should end to run'),
      'required' => FALSE,
      'where' => 'civicrm_job.crontab_date_time_end',
      'default' => 'NULL',
      'table_name' => 'civicrm_job',
      'entity' => 'Job',
      'bao' => 'CRM_Core_BAO_Job',
      'localizable' => 0,
      'html' => [
        'label' => ts("Date & Time End"),
      ]
    ];

    $fields['crontab_time_from'] = [
      'name' => 'crontab_time_from',
      'type' => CRM_Utils_Type::T_TIME,
      'title' => ts('Active From'),
      'description' => ts('When is this cron job should start to run'),
      'required' => FALSE,
      'where' => 'civicrm_job.crontab_time_from',
      'default' => 'NULL',
      'table_name' => 'civicrm_job',
      'entity' => 'Job',
      'bao' => 'CRM_Core_BAO_Job',
      'localizable' => 0,
      'html' => [
        'label' => ts("Active From"),
      ]
    ];

    $fields['crontab_time_to'] = [
      'name' => 'crontab_time_to',
      'type' => CRM_Utils_Type::T_TIME,
      'title' => ts('Active To'),
      'description' => ts('When is this cron job should end to run'),
      'required' => FALSE,
      'where' => 'civicrm_job.crontab_time_to',
      'default' => 'NULL',
      'table_name' => 'civicrm_job',
      'entity' => 'Job',
      'bao' => 'CRM_Core_BAO_Job',
      'localizable' => 0,
      'html' => [
        'label' => ts("Active To"),
      ]
    ];

  };
}

/**
 * Implements hook_civicrm_postProcess().
 */
function crontab_civicrm_postProcess($formName, &$form) {
  if ($formName == 'CRM_Admin_Form_Job') {
    $values = $form->getVar('_submitValues');
    $values['crontab_apply'] = !empty($values['crontab_apply']) ? $values['crontab_apply'] : 0;
    $values['crontab_offset'] = !empty($values['crontab_offset']) ? $values['crontab_offset'] : '5';
    $values['crontab_hour_range'] = !empty($values['crontab_hour_range']) ? $values['crontab_hour_range'] : 0;
    $values['crontab_day_range'] = !empty($values['crontab_day_range']) ? $values['crontab_day_range'] : 0;
    if (empty($form->getVar('_id')) && !empty($values['crontab_frequency'])) {
      $result = civicrm_api3('Job', 'get', [
        'sequential' => 1,
        'return' => ["id"],
        'name' => $values['name'],
      ]);
      if ($result['count'] == 1) {
        $jobID = $result['values'][0]['id'];
      }
    }
    else {
      $jobID = $form->getVar('_id');
    }
    if ($jobID) {
      $set = $params = $fields = [];
      $fields['crontab_apply'] = 'Integer';
      $fields['crontab_frequency'] = 'String';
      $fields['crontab_offset'] = 'Integer';
      $fields['crontab_hour_range'] = 'Boolean';
      $fields['crontab_day_range'] = 'Boolean';
      $fields['crontab_date_time_start'] = 'Timestamp';
      $fields['crontab_date_time_end'] = 'Timestamp';
      $fields['crontab_time_from'] = 'Timestamp';
      $fields['crontab_time_to'] = 'Timestamp';
      $count = 1;
      foreach ($fields as $fieldName => $fieldType) {
        if (empty($values[$fieldName]) && $values[$fieldName] != '0') {
          $values[$fieldName] = '';
        }
        // for timestamp type field, convert date to YmdHis format
        if ($fieldType == 'Timestamp') {
          if (!empty($values[$fieldName])) {
            $ts = strtotime($values[$fieldName]);
            $values[$fieldName] = CRM_Utils_Date::currentDBDate($ts);
          }
        }
        $set[$fieldName] = $count;
        $params[$count] = [$values[$fieldName], $fieldType];
        $count++;
      }
      $sqlOP = "UPDATE civicrm_job ";
      $where = " WHERE  id = $jobID";
      $setClause = [];
      foreach ($set as $n => $v) {
        $setClause[] = "$n = %{$v}";
      }
      $setClause = implode(',', $setClause);
      $query = "$sqlOP 
        SET $setClause 
        $where";
      /*
      CRM_Core_Error::debug_var('$query', $query);
      CRM_Core_Error::debug_var('$params', $params);
      $finalQury = CRM_Core_DAO::composeQuery($query, $params);
      CRM_Core_Error::debug_var('$finalQury', $finalQury);
      */
      CRM_Core_DAO::executeQuery($query, $params);
    }
  }
}

function crontab_civicrm_cron($jobManager) {
  $jobManager->jobs = CRM_Crontab_Utils::_getJobs();
}
