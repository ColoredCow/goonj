# Installation

### Pre-requisites

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

1. Download the database dump from here [LINK](https://drive.google.com/drive/folders/13h9c76wD6Po6ezYDPEaFXlYGgnier-iW)

1. After importing the database, run the `wp search-replace //goonj-crm.staging.coloredcow.com //goonj-civicrm.test` command to update the UAT URLs to your local URLs.

1. Clone this repository
   ```sh
   git clone https://github.com/ColoredCow/goonj.git
   ```

1. Change to project root directory
   ```sh
   cd goonj
   ```

1. Delete the existing uploads folder in wp-content and replace it with the new one downloaded and unzipped from [Here](https://drive.google.com/drive/folders/13h9c76wD6Po6ezYDPEaFXlYGgnier-iW)

1. Open `wp-content/uploads/civicrm/civicrm.settings.php`. Search and replace 3 things:
   1. All the staging URLs: `https://goonj-crm.staging.coloredcow.com` to your local URL.
   2. All the staging paths: `/var/www/html/goonj-crm.staging.coloredcow.com` to your local path.
   3. All the MySQL links: `mysql://goonj-crm:EZZ....@localhost/goonj-crm?new_link=true` to your local.

1. Create the WordPress config file (**specify the correct database credentials**)
   ```sh
   wp config create --dbname=goonj --dbuser=root --dbpass=
   ```
   > _Note: If you see an error try to pass the [`dbhost`](https://developer.wordpress.org/cli/commands/config/create/#options) option as well._

1. Install WordPress and create admin
   ```sh
   wp core install --url=goonj.test --title="Goonj" --admin_user=admin --admin_password=admin --admin_email=admin@example.com
   ```

1. Set the WordPress permalink structure to "postname"
   ```sh
   wp rewrite structure '/%postname%/'
   ```
### Create a Virtual Host
1. For Windows
   1. Create a Virtual host for your project. The virtual host should point to `/path_to_project_directory/public`:
      - WAMP: If you prefer using WAMP, you can set up virtual host by following steps mentioned on this [link](https://stackoverflow.com/questions/22217386/how-to-setup-virtual-host-using-wamp-server-properly).
      - XAMPP: If you prefer using XAMPP, you can set up virtual host by following steps mentioned on this [link](https://github.com/ColoredCow/resources/blob/master/virtualhost/WINDOWS.md).

2. For macOS
   1. Create a virtual host
   ```sh
   valet link goonj
   ```

   2. Secure the virtual host
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

#### Change CiviCRM extensions directory path

1. In your text editor open, `wp-content/uploads/civicrm/civicrm.settings.php`
2. Search for `Override the extensions directory` comment
3. We are going to keep all the extensions in `wp-content/civi-extensions` directory:
   ```php
   // Replace with your actual path to goonj-crm below!
   $civicrm_setting['domain']['extensionsDir'] = '/path/to/goonj-crm/wp-content/civi-extensions';
   ```

#### Extensions

1. Activate required extensions
   ```sh
   cv ext:enable afform afform_admin civirules emailapi
   ```
