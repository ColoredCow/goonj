# Dashboard Refresh

This extension adds a 'Refresh all dashlets' button to the dashboard.

Previous versions of the dashboard had a similar 'refresh all' button but this was removed when the dashboard was rewritten in Angular.  Each dashlet now has a refresh link in its menu bar.

Some dashlets are refreshed on page load but others are cached in the browser.  If you want to refresh that cached data you can do so with the refresh link.  If you have multiple active dashlets then you need to refresh them individually ... unless you install this extension and click the 'Refresh all dashlets' button at the top right.

Note this can hammer your server - use with restraint!

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v7.0+
* CiviCRM 5.40+

## Installation (Web UI)

This extension has not yet been published for installation via the web UI.

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl dashboardrefresh@https://lab.civicrm.org/extensions/dashboardrefresh/archive/main.zip
```

## Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) repo for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://lab.civicrm.org/extensions/dashboardrefresh.git
cv en dashboardrefresh
```

## Usage

On the dashboard, you should see a 'Refresh all dashlets' button.

## Known Issues

None.
