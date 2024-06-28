# CiviCRM

## Preparing development environment

### Pre-requisite

1. PHP 8.x
2. WP-CLI
   1. [Installing via Homebrew](https://make.wordpress.org/cli/handbook/guides/installing/#installing-via-homebrew) would be the easiest option for macOS users.
   2. If not, all other installation options can be found in the same page.
3. MySQL 5.7.5+ or MariaDB 10.2+
4. cv - command is a utility for interacting with a CiviCRM installation
   1. Download - https://github.com/civicrm/cv?tab=readme-ov-file#download

### Steps

#### WordPress site setup

First we will setup a WordPress site on top of which we will setup CiviCRM. For creating the WordPress site we will require a database.

1. Login to the MySQL terminal
   ```sh
   mysql -u root -p
   ```

1. From the MySQL terminal, create a database
   ```sql
   create database goonj;
   exit
   ```

1. Clone this repository
   ```sh
   git clone https://github.com/ColoredCow/goonj.git
   ```

1. Change to project root directory
   ```sh
   cd civicrm
   ```

1. Create the WordPress config file (**specify the correct database credentials**)
   ```sh
   wp config create --dbname=goonj --dbuser=root --dbpass=
   ```

1. Install WordPress and create admin
   ```sh
   wp core install --url=goonj.test --title="Goonj" --admin_user=admin --admin_password=admin --admin_email=admin@example.com
   ```

1. Set the WordPress permalink structure to "postname"
   ```sh
   wp rewrite structure '/%postname%/'
   ```

1. Create a virtual host
   ```sh
   valet link goonj
   ```

1. Secure the virtual host
   ```sh
   valet secure goonj
   ```
#### Setup CiviCRM

1. Activate the plugin
   ```sh
   wp plugin activate civicrm
   ```

2. Go to `https://goonj.test/wp-admin` and configure CiviCRM.
   1. Check all the Components
   2. And click on `Install CiviCRM`

##### Change CiviCRM extensions directory path

1. In your text editor open, `wp-content/uploads/civicrm/civicrm.settings.php`
2. Search for `Override the extensions directory` comment
3. We are going to keep all the extensions in `wp-content/civi-extensions` directory:
   ```php
   // Replace with your actual path to goonj-crm below!
   $civicrm_setting['domain']['extensionsDir'] = '/path/to/goonj-crm/wp-content/civi-extensions';
   ```

##### Extensions

1. Activate required extensions
   ```sh
   cv ext:enable afform afform_admin civirules emailapi
   ```
