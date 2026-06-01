<?php

require_once 'civirules.civix.php';
if (!interface_exists("\\Psr\\Log\\LoggerInterface")) {
  require_once('psr/log/LoggerInterface.php');
}
if (!class_exists("\\Psr\\Log\\LogLevel")) {
  require_once('psr/log/LogLevel.php');
}

use Civi\Core\ClassScanner;
use CRM_Civirules_ExtensionUtil as E;

/**
 * Hook to add the symfony event listeners.
 *
 * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
 *
 * @throws \CRM_Core_Exception
 */
function civirules_civicrm_container(\Symfony\Component\DependencyInjection\ContainerBuilder $container) {
  $container->addCompilerPass(new \Civi\ConfigItems\CiviRulesCompilerPass());
  // Add the symfony listeners.
  // We need to eventID parameter to prevent overwriting of original data in case we
  // have a rule based on edit activity and action to edit a second activity.
  // See this PR: https://lab.civicrm.org/extensions/civirules/-/merge_requests/96
  $container->findDefinition('dispatcher')
    ->addMethodCall('addListener', [
      'civi.dao.preInsert',
      'civirules_trigger_preinsert'
    ])
    ->addMethodCall('addListener', [
      'civi.dao.postInsert',
      'civirules_trigger_postinsert'
    ])
    ->addMethodCall('addListener', [
      'civi.dao.preUpdate',
      'civirules_trigger_preupdate'
    ])
    ->addMethodCall('addListener', [
      'civi.dao.postUpdate',
      'civirules_trigger_postupdate'
    ])
    ->addMethodCall('addListener', [
      'civi.dao.preDelete',
      'civirules_trigger_predelete'
    ])
    ->addMethodCall('addListener', [
      'civi.dao.postDelete',
      'civirules_trigger_postdelete'
    ]);
}

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function civirules_civicrm_config(&$config) {
  _civirules_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function civirules_civicrm_install() {
  _civirules_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function civirules_civicrm_enable() {
  _civirules_civix_civicrm_enable();
}

/**
 * By default we use the Symfony event for preInsert, preUpdate, postInsert etc.
 * However there a couple of entities which do not work yet with the symfony events.
 *
 * In the future this should be refactored so that this would be simplified
 * so that the inner workings are unambiguously with symfony events but that also depends
 * on CiviCRM core changes.
 *
 * @param $op
 * @param $objectName
 * @param $objectId
 * @param $params
 */
function civirules_civicrm_pre($op, $objectName, $objectId, &$params) {
  CRM_Civirules_Utils_ContributionTrigger::pre($op, $objectName, $objectId, $params);
  // New style pre/post Delete/Insert/Update events exist from 5.34.
  if (civirules_use_prehook($op, $objectName, $objectId, $params)) {
    try {
      CRM_Civirules_Utils_PreData::pre($op, $objectName, $objectId, $params, 1);
      CRM_Civirules_Utils_CustomDataFromPre::pre($op, $objectName, $objectId, $params, 1);
    } catch (\Exception $ex) {
      // Do nothing.
    }
  }
}

/**
 * By default we use the Symfony event for preInsert, preUpdate, postInsert etc.
 * However there a couple of entities which do not work yet with the symfony events.
 *
 * In the future this should be refactored so that this would be simplified
 * so that the inner workings are unambiguously with symfony events but that also depends
 * on CiviCRM core changes.
 *
 * @param $op
 * @param $objectName
 * @param $objectId
 * @param $objectRef
 */
function civirules_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  if (civirules_use_posthook($op, $objectName, $objectId, $objectRef)) {
    civirules_instanciate_post_trigger($op, $objectName, $objectId, $objectRef, 1);
  }
}

/**
 * Function to check whether the pre hook could be called from a symfony
 * preInsert, preUpdate, preDelete event. Or whether this should be called from
 * the traditional hook_civicrm_pre.
 *
 * @param $op
 * @param $objectName
 * @param $objectId
 * @param $params
 *
 * @return bool
 */
function civirules_use_prehook($op, $objectName, $objectId, &$params) {
  if ($objectName == 'GroupContact') {
    return TRUE;
  }
  return FALSE;
}

/**
 * Function to check whether the post trigger could be called from a symfony
 * postInsert, postUpdate, postDelete event. Or whether this should be called from
 * the traditional hook_civicrm_post.
 *
 * @param $op
 * @param $objectName
 * @param $objectId
 * @param $objectRef
 *
 * @return bool
 */
function civirules_use_posthook($op, $objectName, $objectId, &$objectRef) {
  if ($objectName == 'GroupContact' && is_array($objectRef)) {
    // GroupContact with the objectRef as an array of contact ids does
    // call hook_civicrm_post directly and does not invoke a civicrm event.
    return TRUE;
  }
  return FALSE;
}

/**
 * This event is called before an entity is inserted in the database.
 *
 * @param \Civi\Core\DAO\Event\PreUpdate $event
 */
function civirules_trigger_preinsert(\Civi\Core\DAO\Event\PreUpdate $event) {
  try {
    $objectName = CRM_Civirules_Utils::getObjectNameFromObject($event->object);
    $objectId = $event->object->id;
    $eventID = $event->eventID ?? 1;
    $params = [];
    CRM_Core_DAO::storeValues($event->object, $params);
    CRM_Civirules_Utils_PreData::pre('create', $objectName, $objectId, $params, $eventID);
    CRM_Civirules_Utils_CustomDataFromPre::pre('create', $objectName, $objectId, $params, $eventID);
  } catch (\Exception $ex) {
    // Do nothing.
  }
}

/**
 * This event is called after an entity is inserted in the database.
 *
 * @param $event
 */
function civirules_trigger_postinsert($event) {
  $objectName = CRM_Civirules_Utils::getObjectNameFromObject($event->object);
  $eventID = $event->eventID ?? 1;
  if (!civirules_use_posthook('create', $objectName, $event->object->id, $event->object)) {
    civirules_instanciate_post_trigger('create', $objectName, $event->object->id, $event->object, $eventID);
  }
}

/**
 * This event is called before an entity is updated in the database.
 *
 * @param \Civi\Core\DAO\Event\PreUpdate $event
 */
function civirules_trigger_preupdate(\Civi\Core\DAO\Event\PreUpdate $event) {
  try {
    $objectName = CRM_Civirules_Utils::getObjectNameFromObject($event->object);
    if (empty($objectName)) {
      // If the object name is empty, we cannot proceed with the pre-update.
      return;
    }
    $objectId = $event->object->id;
    $eventID = $event->eventID ?? 1;
    $params = [];
    CRM_Core_DAO::storeValues($event->object, $params);
    CRM_Civirules_Utils_PreData::pre('edit', $objectName, $objectId, $params, $eventID);
    CRM_Civirules_Utils_CustomDataFromPre::pre('edit', $objectName, $objectId, $params, $eventID);
  } catch (\Exception $ex) {
    // Do nothing.
  }
}

/**
 * This event is called after an entity is updated in the database.
 *
 * @param $event
 */
function civirules_trigger_postupdate($event) {
  $objectName = CRM_Civirules_Utils::getObjectNameFromObject($event->object);
  $eventID = $event->eventID ?? 1;
  if (!civirules_use_posthook('edit', $objectName, $event->object->id, $event->object)) {
    civirules_instanciate_post_trigger('edit', $objectName, $event->object->id, $event->object, $eventID);
  }
}

/**
 * This event is called before an entity is deleted.
 *
 * @param \Civi\Core\DAO\Event\PreDelete $event
 */
function civirules_trigger_predelete(\Civi\Core\DAO\Event\PreDelete $event) {
  try {
    $objectName = CRM_Civirules_Utils::getObjectNameFromObject($event->object);
    $objectId = $event->object->id;
    $eventID = $event->eventID ?? 1;
    $params = [];
    CRM_Core_DAO::storeValues($event->object, $params);
    CRM_Civirules_Utils_PreData::pre('delete', $objectName, $objectId, $params, $eventID);
    CRM_Civirules_Utils_CustomDataFromPre::pre('delete', $objectName, $objectId, $params, $eventID);
  } catch (\Exception $ex) {
    // Do nothing.
  }
}

/**
 * This event is called after an entity is deleted.
 *
 * @param $event
 */
function civirules_trigger_postdelete($event) {
  $objectName = CRM_Civirules_Utils::getObjectNameFromObject($event->object);
  $eventID = $event->eventID ?? 1;
  if (!civirules_use_posthook('delete', $objectName, $event->object->id, $event->object)) {
    civirules_instanciate_post_trigger('delete', $objectName, $event->object->id, $event->object, $eventID);
  }
}

/**
 * Function to call the post trigger.
 * It either creates a callback when a transaction is active or it calls triggers directly.
 *
 * @param $op
 * @param $objectName
 * @param $objectId
 * @param $objectRef
 * @param $eventId
 */
function civirules_instanciate_post_trigger($op, $objectName, $objectId, $objectRef, $eventId) {
  try {
    if (CRM_Core_Transaction::isActive()) {
      CRM_Core_Transaction::addCallback(CRM_Core_Transaction::PHASE_POST_COMMIT, 'civirules_call_post_trigger', [
        $op,
        $objectName,
        $objectId,
        $objectRef,
        $eventId
      ]);
    }
    else {
      civirules_call_post_trigger($op, $objectName, $objectId, $objectRef, $eventId);
    }
  } catch (\Exception $ex) {
    // Do nothing.
  }
}

/**
 * This callback is called from the post hooks or after the transaction is completed.
 *
 * @param $op
 * @param $objectName
 * @param $objectId
 * @param $objectRef
 * @param $eventID
 */
function civirules_call_post_trigger($op, $objectName, $objectId, $objectRef, $eventID) {
  try {
    CRM_Civirules_Trigger_Post::post($op, $objectName, $objectId, $objectRef, $eventID);
  } catch (\Exception $ex) {
    // Do nothing.
  }
}

/**
 * This is the pre hook before custom data has been changed.
 *
 * @param $op
 * @param $groupID
 * @param $entityID
 * @param $params
 */
function civirules_civicrm_customPre($op, $groupID, $entityID, &$params) {
  try {
    CRM_Civirules_Utils_PreData::customPre($op, $groupID, $entityID, $params, 1);
  } catch (\Exception $ex) {
    // Do nothing.
  }
}

/**
 * This is the post hook after custom data has been changed.
 *
 * @param $op
 * @param $groupID
 * @param $entityID
 * @param $params
 *
 * @throws \CRM_Core_Exception
 */
function civirules_civicrm_custom($op, $groupID, $entityID, &$params) {
  /**
   * Fix/Hack for issue #208 (https://github.com/CiviCooP/org.civicoop.civirules/issues/208)
   *
   * To reproduce:
   * - create a custom data set for contacts that supports multiple records
   * - create a rule that triggers on custom data changing
   * - add a record to that custom data set for a contact
   * - delete the record
   * - observe the logs
   *
   * This returns the error: "Expected one Contact but found 25"
   * Traced to CRM/CivirulesPostTrigger/ContactCustomDataChanged.php where there is an api call to contacts getsingle. The issue is that when the custom data record is deleted, there is no remaining entity_id with which to retrieve the contact, and so no id is passed to the getsingle call.
   *
   * The fix is to check whether the $op is delete and whether $entityID is empty and then check
   * whether the contactID is provided in the url.
   */
  if ($op == 'delete' && empty($entityID)) {
    $contactId = CRM_Utils_Request::retrieve('contactId', 'Positive');
    if (!empty($contactId)) {
      $entityID = $contactId;
    }
  }
  /** End ugly hack */

  CRM_CivirulesPostTrigger_CaseCustomDataChanged::custom($op, $groupID, $entityID, $params);
  CRM_CivirulesPostTrigger_ContactCustomDataChanged::custom($op, $groupID, $entityID, $params);
  CRM_CivirulesPostTrigger_IndividualCustomDataChanged::custom($op, $groupID, $entityID, $params);
  CRM_CivirulesPostTrigger_OrganizationCustomDataChanged::custom($op, $groupID, $entityID, $params);
  CRM_CivirulesPostTrigger_HouseholdCustomDataChanged::custom($op, $groupID, $entityID, $params);
}

function civirules_civirules_alter_trigger_data(CRM_Civirules_TriggerData_TriggerData &$triggerData) {
  //also add the custom data which is passed to the pre hook (and not the post)
  CRM_Civirules_Utils_CustomDataFromPre::addCustomDataToTriggerData($triggerData);
}

/**
 * Implements hook_civicrm_apiWrappers()
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_apiWrappers/
 */
function civirules_civicrm_apiWrappers(&$wrappers, $apiRequest) {
  if ($apiRequest['entity'] == 'Contact' && $apiRequest['action'] == 'create') {
    $wrappers[] = new CRM_Civirules_TrashRestoreApiWrapper();
  }
}

/**
 * Implements hook_civicrm_permission().
 */
function civirules_civicrm_permission(&$permissions) {
  $permissions['administer CiviRules'] = [
    'label' => E::ts('CiviRules: administer CiviRules extension'),
    'description' => E::ts('Perform all CiviRules administration tasks in CiviCRM'),
  ];
}

/**
 * We can't use mixin scan-classes directly because we need to exclude the ConfigItems classes
 *   since that will fail if ConfigItems extension is not installed because scan-classes
 *   picks them up automatically.
 *
 * @param $classes
 *
 * @return void
 *
 * Implements hook_civicrm_scanClasses
 *
 * @see CRM_Utils_Hook::scanClasses()
 */
function civirules_civicrm_scanClasses(&$classes) {
  ClassScanner::scanFolders($classes, __DIR__, 'CRM', '_');
  ClassScanner::scanFolders($classes, __DIR__, 'Civi', '\\', ';(ConfigItems);');
}

/**
 * Intercept form functions
 * @param $formName
 * @param $form
 */
function civirules_civicrm_buildForm($formName, &$form) {
  switch ($formName) {
    case 'CRM_Civirules_Form_Rule':
      Civi::service('angularjs.loader')->addModules([
        'afsearchRuleConditions',
        'afsearchRuleActions',
        'afsearchRuleTriggerHistory'
      ]);
      break;
  }
}
