# How to create a trigger for a custom entity

If you have an extension with a custom entity (such as the expense extension, which as an expense and expense line entity) you can also quite easily create triggers for those entities.

Not much is need, you need to make civirules aware of those entities. CiviRules already has helper functions for this.

This how to explains how to add triggers based on the expense extension. This extension contains 2 entities: Expense and ExpenseLine.

### Step 1 - Add the Trigger to the Database

New method using Managed Entities: [Defining Triggers/Conditions/Actions in extension](./define-trigger-condition-action-in-extension.md).

#### Old method (using json files)

Create a file in your extension folder with the filename `civirules/triggers.json` with the following contents:

```json
[
  {"name":"new_expense","label":"Expense is added","object_name":"Expense","op":"create","class_name":null,"cron":"0"},
  {"name":"changed_expense","label":"Expense is changed","object_name":"Expense","op":"edit","class_name":null,"cron":"0"},
  {"name":"deleted_expense","label":"Expense is deleted","object_name":"Expense","op":"delete","class_name":null,"cron":"0"},
  {"name":"new_expense_line","label":"Expense Line is added","object_name":"ExpenseLine","op":"create","class_name":"CRM_Expenses_Civirules_ExpenseLinePostTrigger","cron":"0"},
  {"name":"changed_expense_line","label":"Expense Line is changed","object_name":"ExpenseLine","op":"edit","class_name":"CRM_Expenses_Civirules_ExpenseLinePostTrigger","cron":"0"},
  {"name":"deleted_expense_line","label":"Expense Line is deleted","object_name":"ExpenseLine","op":"delete","class_name":"CRM_Expenses_Civirules_ExpenseLinePostTrigger","cron":"0"}
]
```

Each entity has 3 triggers, one for when the entity is added, one for when the entity is changed and one for when the entity is deleted. In the example above we have 6 triggers because the expense extension contains 2 entities: Expense and ExpenseLine.

We set the `object_name` to the name of the entity which is either `Expense` or `ExpenseLine`.
For expense line we use our own trigger class so that we can add the `Expense` entity to the trigger data. So we set the `class_name` to `CRM_Expenses_Civirules_ExpenseLinePostTrigger`. If no `class_name` is provided civirules will use the class `CRM_Civirules_Trigger_Post`.
And as this is not a cron trigger we set `cron` to `0`.

## Create a custom Trigger class for ExpenseLine

Create a file under `CRM\Expenses\Civirules` with the name `ExpenseLinePostTrigger.php` and add the following:

```php
<?php

class CRM_Expenses_Civirules_ExpenseLinePostTrigger extends CRM_Civirules_Trigger_Post {

  /**
   * Returns an array of additional entities provided in this trigger
   *
   * @return array of CRM_Civirules_TriggerData_EntityDefinition
   */
  protected function getAdditionalEntities() {
    $entities = parent::getAdditionalEntities();
    $entities[] = new CRM_Civirules_TriggerData_EntityDefinition('Expense', 'Expense', 'CRM_Expenses_DAO_Expense' , 'Expense');
    return $entities;
  }

  /**
   * Alter the trigger data with extra data
   *
   * @param \CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  public function alterTriggerData(CRM_Civirules_TriggerData_TriggerData &$triggerData) {
    $expenseLine = $triggerData->getEntityData('ExpenseLine');
    try {
      $expense = civicrm_api3('Expense', 'getsingle', ['id' => $expenseLine['expense_id']]);
      $triggerData->setEntityData('Expense', $expense);
    } catch (\Exception $e) {
      // Do nothing.
    }

    parent::alterTriggerData($triggerData);
  }

}

```

This class has two functions. The function `getAdditionalEntities` specifies that this trigger also contains data of the expense.
The function `alterTriggerData` alters the data provided in the trigger and looks up the expense based on the expense line.

## Make the triggers known to civirules.

Now we use the hook `managed_entity` to let civirules know about our triggers. We also have to check whether CiviRules is installed otherwise the class and function could not be found and this results in a fatal error.

Change the your extension.php file. In our example it is expenses.php:

```php

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function expenses_civicrm_managed(&$entities) {
  _expenses_civix_civicrm_managed($entities);

  // Load the triggers when civirules is installed.
  if (_expenses_is_civirules_installed()) {
     CRM_Civirules_Utils_Upgrader::insertTriggersFromJson(E::path('civirules/triggers.json'));
  }
}

/**
 * Function to check whether civirules is installed.
 *
 * @return bool
 */
function _expenses_is_civirules_installed() {
  if (civicrm_api3('Extension', 'get', ['key' => 'civirules', 'status' => 'installed'])['count']) {
    return true;
  } elseif (civicrm_api3('Extension', 'get', ['key' => 'org.civicoop.civirules', 'status' => 'installed'])['count']) {
    return true;
  }
  return false;
}
```

## See also

* [Trigger](./trigger.md)
