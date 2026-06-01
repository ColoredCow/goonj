<?php
/**
 * Class for CiviRules engine
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use Civi\Api4\CiviRulesRuleAction;
use CRM_Civirules_ExtensionUtil as E;

class CRM_Civirules_Engine {

  const QUEUE_NAME = 'org.civicoop.civirules.action';

  /**
   * Trigger a rule.
   *
   * The trigger will check the conditions and if conditions are valid then the actions are executed
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return bool true when conditions are valid; false when conditions are not valid
   */
  public static function triggerRule(CRM_Civirules_Trigger $trigger, CRM_Civirules_TriggerData_TriggerData $triggerData): bool {
    try {
      $triggerData->setTrigger($trigger);

      // The Entity ID should have been set by one of the TriggerData classes
      if (empty($triggerData->getEntityId())) {
        \CRM_Core_Error::deprecatedWarning('CiviRules: The entityID for Entity: ' . $triggerData->getEntity() . ' should be set by the calling class.');
        $triggerData->setEntityId($triggerData->getEntityData($triggerData->getEntity())['id']);
      }

      if ($triggerData->getEntity() === 'Contact') {
        if (empty($triggerData->getContactId())) {
          \CRM_Core_Error::deprecatedWarning('CiviRules: The Contact contactID should be set by the calling class.');
          $triggerData->setContactId($triggerData->getEntityId());
        }
      }
    } catch (Throwable $e) {
      // Catch *any* error when loading the triggerData and log it.
      \Civi::log('civirules')->error('CiviRules: Failed loading triggerData for ruleID: ' . $trigger->getRuleId() . ' with error: ' . $e->getMessage());
      return FALSE;
    }

    // Check if the conditions are valid
    try {
      $isRuleValid = self::areConditionsValid($triggerData);
    } catch (Throwable $e) {
      // Catch *any* error when executing the conditions and log it.
      \Civi::log('civirules')->error('CiviRules: One or more conditions is failing for ruleID: ' . $trigger->getRuleId() . ' with error: ' . $e->getMessage());
      return FALSE;
    }

    try {
      if ($isRuleValid) {
        // Log and execute the actions for the rule
        self::logRule($triggerData);
        self::executeActions($triggerData);
        return TRUE;
      }
    } catch (Throwable $e) {
      // Catch *any* error when executing the actions and log it.
      \Civi::log('civirules')->error('CiviRules: Failed executing actions for ruleID: ' . $trigger->getRuleId() . ' with error: ' . $e->getMessage());
      return FALSE;
    }

    return FALSE;
  }

  /**
   * Method to execute the actions
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return void
   */
  protected static function executeActions(CRM_Civirules_TriggerData_TriggerData $triggerData): void {
    $ruleActions = CiviRulesRuleAction::get(FALSE)
      ->addWhere('rule_id', '=', $triggerData->getTrigger()->getRuleId())
      ->addWhere('is_active', '=', TRUE)
      ->addOrderBy('weight', 'ASC')
      ->addOrderBy('id', 'ASC')
      ->execute();
    foreach ($ruleActions as $ruleAction) {
      self::executeAction($triggerData, $ruleAction);
    }
  }

  /**
   * Method to execute a single action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @param array $ruleAction
   *
   * @return void
   */
  public static function executeAction(CRM_Civirules_TriggerData_TriggerData &$triggerData, array $ruleAction): void {
    $actionEngine = CRM_Civirules_ActionEngine_Factory::getEngine($ruleAction, $triggerData);

    //determine if the action should be executed with a delay
    $delay = self::getActionDelay($ruleAction, $actionEngine);
    if ($delay instanceof DateTime) {
      $triggerData->isDelayedExecution = TRUE;
      $triggerData->delayedSubmitDateTime = CRM_Utils_Time::date('YmdHis');
      self::delayAction($delay, $actionEngine);
    } else {
      //there is no delay so process action immediately
      $triggerData->isDelayedExecution = FALSE;
      try {
        $actionEngine->execute();
      }
      catch (Throwable $e) {
        CRM_Civirules_Utils_LoggerFactory::logError(E::ts('Failed to execute action'),  $e->getMessage(), $triggerData, $actionEngine->getRuleAction());
      }
    }
  }

  /**
   * Process delayed actions
   *
   * @param int $maxRunTime
   *
   * @return array
   */
  public static function processDelayedActions(int $maxRunTime = 30): array {
    $queue = CRM_Queue_Service::singleton()->create([
      'type' => 'Civirules',
      'name' => self::QUEUE_NAME,
      'reset' => FALSE, //do not flush queue upon creation
    ]);

    $returnValues = [];

    // retrieve the queue
    $runner = new CRM_Queue_Runner([
      'title' => E::ts('Process delayed civirules actions'), // title for the queue
      'queue' => $queue, // the queue object
      'errorMode'=> CRM_Queue_Runner::ERROR_CONTINUE, // continue on error otherwise the queue will hang
    ]);

    $stopTime = time() + $maxRunTime; // stop executing next item after 30 seconds
    while((time() < $stopTime) && $queue->numberOfItems() > 0) {
      $result = $runner->runNext(FALSE);
      $returnValues[] = $result;

      if (!$result['is_continue']) {
        break;
      }
    }

    return $returnValues;
  }

  /**
   * Executes a delayed action
   *
   * @return bool
   */
  public static function executeDelayedAction(): bool {
    try {
      $args = func_get_args();
      /* @var \CRM_Civirules_ActionEngine_AbstractActionEngine $actionEngine */
      $actionEngine = $args[1];
      $triggerData = $actionEngine->getTriggerData();
      if ($actionEngine->ignoreConditionsOnDelayedProcessing()) {
        $processAction = TRUE;
      } else {
        $entity = $triggerData->getEntity();
        if ($entity) {
          try {
            $entityData = civicrm_api3($entity, 'get', ['id' => $triggerData->getEntityId(), 'sequential' => 1]);
            if ($entityData['count'] === 0) {
              // Since this is a delayed action, it's possible the entity has
              // been deleted by now, so don't compare against the original.
              $triggerData->setEntityData($entity, []);
            }
            elseif ($entityData['count'] > 1) {
              // Unlikely because we're getting via entity id, but just in case.
              // Since probably will never see this, don't bother with ts().
              throw new \CRM_Core_Exception('Expected one ' . $entity . ' but found ' . $entityData['count']);
            }
            else {
              $entityData = $entityData['values'][0];
              $entityData = self::useCanonicalFieldNames($entity, $entityData);
              $triggerData->setEntityData($entity, $entityData);
            }
          }
          catch (Exception $e) {
            // leave $triggerData as is
          }
        }
        $processAction = self::areConditionsValid($triggerData);
      }
      if ($processAction) {
        $actionEngine->execute();
      }
    } catch (Throwable $e) {
      CRM_Civirules_Utils_LoggerFactory::logError(E::ts('Failed to execute delayed action'),  $e->getMessage(), $triggerData, $actionEngine->getRuleAction());
    }
    return TRUE;
  }

  /**
   * Modify entity data to use canonical and not unique field names.
   * This is necessary because CiviRules uses canonical field names, but `executeDelayedAction()` calls API3, which uses unique field names.
   */
  public static function useCanonicalFieldNames(string $entityName, array $entityData) : array {
    $fixedEntityData = [];
    $fieldData = civicrm_api3($entityName, 'getfields')['values'];
    $lookupTable = array_combine(array_keys($fieldData), array_column($fieldData, 'name'));
    foreach ($entityData as $fieldName => $value) {
      if (isset($lookupTable[$fieldName])) {
        $fixedEntityData[$lookupTable[$fieldName]] = $value;
      }
      else {
        $fixedEntityData[$fieldName] = $value;
      }
    }
    return $fixedEntityData;
  }

  /**
   * Save an action into a queue for delayed processing
   *
   * @param \DateTime $delayTo
   * @param CRM_Civirules_ActionEngine_AbstractActionEngine $actionEngine
   *
   * @return void
   */
  public static function delayAction(DateTime $delayTo, CRM_Civirules_ActionEngine_AbstractActionEngine $actionEngine): void {
    $queue = CRM_Queue_Service::singleton()->create([
      'type' => 'Civirules',
      'name' => self::QUEUE_NAME,
      'reset' => FALSE, // do not flush queue upon creation
    ]);

    // create a task with the action and eventData as parameters
    $task = new CRM_Queue_Task(
      ['CRM_Civirules_Engine', 'executeDelayedAction'], // call back method
      [$actionEngine] // parameters
    );

    // save the task with a delay
    $dao              = new CRM_Queue_DAO_QueueItem();
    $dao->queue_name  = $queue->getName();
    $dao->submit_time = CRM_Utils_Time::date('YmdHis');
    $dao->data        = serialize($task);
    $dao->weight      = 0; // weight, normal priority
    $dao->release_time = $delayTo->format('YmdHis');
    $dao->save();
  }

  /**
   * Returns false when action could not be delayed or return a DateTime
   * This DateTime object holds the date and time till when the action should be delayed
   *
   * The delay is calculated by a separate delay class. See CRM_Civirules_DelayDelay
   *
   * @param $ruleAction
   * @param CRM_Civirules_ActionEngine_AbstractActionEngine $actionEngine
   *
   * @return bool|\DateTime
   */
  public static function getActionDelay($ruleAction, CRM_Civirules_ActionEngine_AbstractActionEngine $actionEngine) {
    $delayedTo = new DateTime();
    $now = new DateTime();
    if (!empty($ruleAction['delay'])) {
      $delayClass = unserialize(($ruleAction['delay']));
      if ($delayClass instanceof CRM_Civirules_Delay_Delay) {
        $delayedTo = $delayClass->delayTo($delayedTo, $actionEngine->getTriggerData());
      }
    }

    $actionDelayedTo = $actionEngine->delayTo($delayedTo);
    if ($actionDelayedTo instanceof DateTime) {
      if ($now < $actionDelayedTo) {
        return $actionDelayedTo;
      }
      return FALSE;
    } elseif ($delayedTo instanceof DateTime and $now < $delayedTo) {
      return $delayedTo;
    }
    return FALSE;
  }

  /**
   * Method to check if all conditions are valid
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return bool
   */
  public static function areConditionsValid(CRM_Civirules_TriggerData_TriggerData $triggerData): bool {
    $isValid = TRUE;
    $firstCondition = TRUE;

    $ruleConditions = $triggerData->getTrigger()->getRuleConditions();
    foreach ($ruleConditions as $ruleConditionId => $ruleCondition) {
      if ($firstCondition) {
        // Always check the first condition
        $isValid = self::checkCondition($ruleCondition, $triggerData);
        $conditionsValid[$ruleConditionId] = $ruleConditionId . '=' . ($isValid ? 'true' : 'false');
        $firstCondition = FALSE;
        // We always check the next condition because it might have condition_link=OR.
        continue;
      }
      switch ($ruleCondition['condition_link']) {
        case 'AND':
          // If the previous condition evaluated to TRUE then we need this condition to be TRUE as well
          if (!$isValid) {
            // Previous condition was not valid so conditions are not met.
            // Don't check any more conditions
            break 2;
          }
          $isValid = self::checkCondition($ruleCondition, $triggerData);
          $conditionsValid[$ruleConditionId] = $ruleCondition['condition_link'] . $ruleConditionId . '=' . ($isValid ? 'true' : 'false');
          break;

        case 'OR':
          if ($isValid) {
            // Previous condition was valid so conditions are met.
            $conditionsValid[$ruleConditionId] = $ruleCondition['condition_link'] . $ruleConditionId . '=' . ('notchecked');
            break;
          }
          $isValid = self::checkCondition($ruleCondition, $triggerData);
          $conditionsValid[$ruleConditionId] = $ruleCondition['condition_link'] . $ruleConditionId . '=' . ($isValid ? 'true' : 'false');
          break;

        default:
          \Civi::log('civirules')->error(
            'CiviRules: RuleID: ' . $triggerData->getTrigger()->getRuleId() . ', ConditionID: ' . $ruleConditionId
            . ' has invalid condition_link operator: ' . $ruleCondition['condition_link']);
          $conditionsValid[$ruleConditionId] = $ruleCondition['condition_link'] . $ruleConditionId . '=invalid condition_link operator';
          $isValid = FALSE;
          break 2;
      }
    }

    if ($triggerData->getTrigger()->getRuleDebugEnabled()) {
      // Debugging - log validation of conditions
      if (!empty($ruleConditions)) {
        $context = [];
        $context['rule_id'] = $triggerData->getTrigger()->getRuleId();
        $context['conditions_valid'] = ($isValid ? 'true' : 'false') . '; Detail: ' . implode(';', $conditionsValid ?? []);
        $context['contact_id'] = $triggerData->getContactId();
        $context['entity_id'] = $triggerData->getEntityId();
        CRM_Civirules_Utils_LoggerFactory::log("Rule {$context['rule_id']}: Conditions: {$context['conditions_valid']}",
          $context,
          \Psr\Log\LogLevel::DEBUG);
      }
    }

    return $isValid;
  }

  /**
   * Method to check condition
   *
   * @param array $ruleCondition
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return bool
   * @throws \Exception
   */
  public static function checkCondition(array $ruleCondition, CRM_Civirules_TriggerData_TriggerData $triggerData): bool {
    $condition = CRM_Civirules_BAO_Condition::getConditionObjectById($ruleCondition['condition_id'], FALSE);
    if (!$condition) {
      return FALSE;
    }
    $condition->setRuleConditionData($ruleCondition);
    return (bool) $condition->isConditionValid($triggerData);
  }

  /**
   * This function writes a record to the log table to indicate that this rule for this trigger is triggered
   *
   * The data this function stores is required by the cron type events.
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  protected static function logRule(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $trigger = $triggerData->getTrigger();
    $reactOnEntity = $trigger->getReactOnEntity();
    $daoClass = $reactOnEntity->daoClass;
    if(!empty($daoClass)) {
      $table = $daoClass::getTableName();
    }

    $ruleLog = \Civi\Api4\CiviRulesRuleLog::create(FALSE)
      ->addValue('rule_id', $trigger->getRuleId());
    if ($triggerData->getContactId()) {
      $ruleLog->addValue('contact_id', $triggerData->getContactId());
    }
    if (!empty($table)) {
      $ruleLog->addValue('entity_table', $table);
    }
    if ($triggerData->getEntityId()) {
      $ruleLog->addValue('entity_id', $triggerData->getEntityId());
    }
    $ruleLog->execute();
  }

}
