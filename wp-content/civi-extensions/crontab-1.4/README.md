# com.skvare.crontab

![Screenshot](/images/screenshot.gif)

This extension provides advanced job scheduling functionality.

Admins can set Linux CRONTAB-like expressions for each scheduled job.


The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v5.6+
* CiviCRM 5.27+

## Installation (Web UI)

This extension has not yet been published for installation via the web UI.

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl com.skvare.crontab@https://github.com/Skvare/com.skvare.crontab/archive/master.zip
```

## Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) repo for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://github.com/Skvare/com.skvare.crontab.git
cv en crontab
```

## Usage

* Go to Scheduled job listing -> Edit any job -> select checkbox `Advanced Job
 Scheduling`, then additional details get displayed
* Set your job frequency either using Basic Setting or the Advanced Setting.
* Set `Run Frequency Time Margin` for at least 5 min (default value is 5)
    > * We are using the Linux Cron tab-like format to run the cron job at a specific time, but this is not an actual crontab; we are totally relying on CiviCRM Job. CiviCRM jobs are executed by crontab-like features.
    > * We may run a CiviCRM job every 5 or 10 minutes. But this will not be executed at the scheduled time. So we have to give some relaxation using this margin setting.
    > * Provide a margin in the number of minutes based on the crontab frequency of the main civicrm job. If empty, we will use 5 minutes as the default margin.
* Set a scheduled start and end date.
    > * The scheduled job only gets executed on the specified date.
* Set the active time of each scheduled job.
    > * We can run scheduled jobs every 30 minutes but only execute them in business hours from 9 a.m. to 5 p.m., or run them after business hours from 8 p.m. to the next morning at 7 a.m.
