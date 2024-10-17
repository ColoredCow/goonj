ALTER TABLE civicrm_job ADD crontab_apply TINYINT(4) NULL DEFAULT NULL COMMENT 'Is Crontab functionality enabled?';
ALTER TABLE civicrm_job ADD crontab_frequency varchar(255) COMMENT 'Crontab frequency pattern';
ALTER TABLE civicrm_job ADD crontab_offset varchar(10) COMMENT 'Crontab margin time in minutes';
ALTER TABLE civicrm_job ADD crontab_hour_range TINYINT(4) NULL DEFAULT NULL COMMENT 'Use Hour range to execute job';
ALTER TABLE civicrm_job ADD crontab_day_range TINYINT(4) NULL DEFAULT NULL COMMENT 'Use Day range to execute job';
ALTER TABLE civicrm_job ADD crontab_date_time_start datetime DEFAULT NULL COMMENT 'When to Start Job Execution';
ALTER TABLE civicrm_job ADD crontab_date_time_end datetime DEFAULT NULL COMMENT 'When to Start Job Execution';
ALTER TABLE civicrm_job ADD crontab_time_from time DEFAULT NULL;
ALTER TABLE civicrm_job ADD crontab_time_to time DEFAULT NULL;
