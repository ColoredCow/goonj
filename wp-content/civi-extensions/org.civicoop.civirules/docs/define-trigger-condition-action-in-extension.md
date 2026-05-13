# How to define a new Trigger, Condition, Action using Managed Entities

## Create your trigger / condition / action

How to do that is not described here.

## Export the new trigger / condition / action as a Managed Entity

Check the database / API for the ID of your action and then export using civix:

`civix export CiviRulesAction {action ID}`

That will create a new file in your extension `managed/CiviRulesAction_myactionname.mgd.php`.

## Allow installation of extension without CiviRules

If your extension provides other functionality (eg. an API) that is not dependent on CiviRules you need to do
some things to make CiviRules optional:

### info.xml

Remove the scan-classes mixin.

### myextension.php

Add a custom "scanClasses" hook that excludes the Civirules classes in your extension. Example: 

```php
function membershiprenew_civicrm_scanClasses(&$classes) {
  \Civi\Core\ClassScanner::scanFolders($classes, __DIR__, 'CRM', '_', ';(CivirulesActions);');
  \Civi\Core\ClassScanner::scanFolders($classes, __DIR__, 'Civi', '\\');
}
```

### CiviRulesAction_myactionname.mgd.php:

#### In the mgd.php include a check for CiviRules extension:

```php
<?php

if (empty(\Civi\Api4\Extension::get(FALSE)

  ->addWhere('file', '=', 'civirules')
  ->addWhere('status:name', '=', 'installed')
  ->execute()
  ->first())) {
  return;
}

return [
  ...
```

#### Make sure that the cleanup and update policy is set to "always".

This ensures that if CiviRules is installed/uninstalled the triggers/conditions/actions will be removed,
otherwise you will get a crash with "class not found" next time Managed Entities are reconciled.

```php
    'cleanup' => 'always',
    'update' => 'always',
```
