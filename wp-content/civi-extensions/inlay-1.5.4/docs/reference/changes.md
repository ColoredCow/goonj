# Change log

## 1.5.4 for CiviCRM 6.12+

- Stop using CRM.resourceUrls which is no longer reliable.


## (1.5.0 -) 1.5.3 for CiviCRM 6.12+

- Using an older version with CiviCRM 6.12 and PHP 8+ will crash on trying to
  save your inlays! (This is because CiviCRM core changed the method signature
  for API actions' `formatWriteValues()`)

- Please do not use 1.5.0 - 1.5.2!

## 1.4.1 (unreleased)

- The 'demo/preview/use' screens on the back end now map a query parameter
  adminMode=1 to an `data-admin-mode=1` attribute on the `<script>` tag.
  This is useful for types that wish to provide usable inlays on the admin
  screen. These types might, for example, respond to the presence of admin mode
  by reducing the CRSF timeout to zero, or showing a different UI. The page
  title drops the prefix "Demo: " when used in admin mode. Inlay does not
  as yet provide a link to the use page with this parameter set, so Types
  would need to implement it from the admin screen, for example. This is a new
  feature, so moving slow.

- Javascript using `inlay.request` can now optionally override various
  `fetch()` related parameters. This allows an Inlay to, for example, set
  `{mode: 'no-cors', credentials: 'same-origin'}` and thereby make a
  request as the logged in user (if the inlay is used on the CiviCRM site).

- A `runId` is passed in with inlay requests. This is a UUID for the runtime
  in the browser. It is reset on page reload. Useful if you need to group
  multiple requests for logging/debugging purposes.

## 1.4.0

- Ensures the current config version is output with the config. This is
  important for config upgrades (specifically one in InlayPetition).

## 1.3.10

- The preview page _always_ rebuilds the inlay bundle on every page load.
- Add API4 InlayAsset with actions get, put.
- Entity Schema v2 upgrade

- ✨ New event: `civi.inlay.bundleupdate` this is emitted in a shutdown function
  whenever one or more Inlay bundles have been written. You might listen to
  this for the sake of updating a list sent to a CMS, for example.

## 1.3.10

- Add API4 InlayAsset with actions get, put.
- Entity Schema v2 upgrade

- ✨ New event: `civi.inlay.bundleupdate` this is emitted in a shutdown function
  whenever one or more Inlay bundles have been written. You might listen to
  this for the sake of updating a list sent to a CMS, for example.

## 1.3.10

- Add API4 InlayAsset with actions get, put.

## 1.3.9

- remove `type=module`! It requires sites to emit special headers
  `Access-Control-Allow-Origin` and breaks on sites without that!

## 1.3.8

- Changes to support better migration and validation of config between versions
  of type implementations.

- Suggest `<script>` tags sourcing inlay bundles now specify `type=module`. This
  should not hurt existing scripts but might be important to support ESM modules used within scripts.
  ⚠️ this turned out to be wrong!

- Inlay tidies up better if you uninstall it. (Warning, if not obvious: it will
  now delete all inlay-managed assets, as well as bundle scripts and inlay-specific database tables)

## 1.3.6 (was never released)

- Fix a PHP 8.1 warning in certain situations
- Adds concept of inlay instances being ON/OFF. Also "Broken". If an inlay is
  not **on** then the bundle will do nothing and any inlay-api requests will be denied.
- Adds updateBundle API parameter to Inlay.save Inlay.update Inlay.create
  which defaults TRUE (previous behaviour) but can be set FALSE if you don't want
  saving to update the bundle. This can be useful in an upgrader step.
- Adds Inlay.get API parameter to get the config array without validating
  (useful for upgrader scripts).
- Fixed a bug that meant certain API updates might give inlays a new public
  ID (meaning anyone using the original code would always get the out of date one!)
- Inlay .js bundles without config are now deleted when found, after
  bundle updates by cron.

## 1.3.5

- Same as 1.3.4 but with minified production build of the .js files. Saves ~3kB.

## 1.3.4

- Fix for false negative on clean url check under WordPress
  https://lab.civicrm.org/extensions/inlay/-/issues/11 Thanks @Upperholme

## 1.3.3

- You can now create a copy of an inlay from the **Administer » Inlays**

## 1.3.2

- Fix [issue #10](https://lab.civicrm.org/extensions/inlay/-/issues/10) - CORS errors if inlay used on same domain as CiviCRM.
- There’s now a simple preview page to be able to see how an inlay might look/work before placing it on another site. It’s a good idea to test on the site you’re actually wanting to use it on, but this will have its uses. Click the new _Preview_ link from the inlays list. [Enhanement #8](https://lab.civicrm.org/extensions/inlay/-/issues/8)

## 1.3.1

- Gracefully handle inlay types that have been disabled/uninstalled. Previously the inlay admin page (and any Inlay.get call) would crash; now the Inlay.get call will include an 'error' key on the returned data. Inlay.createBundle will delete the inlay bundle .js files of such inlays, and will issue a 'notice' to the log.
- Allow inlays to boot up immediately if the page has already completed loading. This is not the usual case, but is the case when another script adds in an Inlay script.
- You can now see console debug messages by calling `localStorage.setItem('inlayDebug', 1)` To disable: `localStorage.removeItem('inlayDebug)` Other inlay scripts may wish to use `CiviCRMInlay.debug()` to pass debug messages, instead of console.log

## 1.3.0 Type hints

More type hints have been added to the `\Civi\Inlay\Type` class. Inlays whose method signatures do not match these will hit trouble - update your code and test! (Hence the minor version jump.)

Also, implemented the default pattern for `setConfig()` which was always supposed to be the case according to the comments, but actually was left abstract!

## 1.2.1 Remove logging

1.2 introduced a new InlayConfigSet entity with logging enabled. On systems that upgraded to this version it's possible that CiviCRM did not create the appropriate `log_civicrm_inlay_config_set` table, and therefore it crashed when you tried to save a config set.

- If this has not affected you yet, great. Use this version.

- If you upgraded to 1.2, upgrading to 1.2.1 probably won't fix it alone; you'll need to run the following SQL manually:

```SQL
DROP TRIGGER civicrm_inlay_config_set_after_insert;
DROP TRIGGER civicrm_inlay_config_set_after_update;
DROP TRIGGER civicrm_inlay_config_set_after_delete;
```

## 1.2 Config Sets, Assets

This version introduces InlayConfigSet entities. These allow Inlays to define their own sets of configuration separate to the configuration of an inlay's instance. e.g. an inlay might define some config that is site-wide. Or it might define several sets of config to be reused by other inlays. See [original thinking](https://lab.civicrm.org/extensions/inlay/-/issues/4)

It also cleans up a little boilerplate requirements; you no longer need to set [`editURLTemplate`](https://lab.civicrm.org/extensions/inlay/-/blob/4e9307f80b56419a1e020a2dc0bdae11d8ba8d56/Civi/Inlay/Type.php#L26) if your inlay follows a prescribed pattern.

It also adds Asset Management allowing inlays to use named files. Files are given a unique filename with random hash so can't be guessed. See [original thinking](https://lab.civicrm.org/extensions/inlay/-/issues/5)

## 1.1 First stable release

Not released yet...

## Pre stable version

This version has been in use since 2020.
