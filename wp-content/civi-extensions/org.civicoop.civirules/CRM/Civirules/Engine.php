<?php
/**
 * Class for CiviRules engine
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

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

      // Check if the conditions are valid
      $isRuleValid = self::areConditionsValid($triggerData);

      if ($isRuleValid) {
        // Log and execute the actions for the rule
        self::logRule($triggerData);
        self::executeActions($triggerData);
        return TRUE;
      }
    } catch (Exception $e) {
      $message = "Error on {file} (Line {line})\r\n\r\n{exception_message}";
      $context = [];
      $context['line'] = $e->getLine();
      $context['file'] = $e->getFile();
      $context['exception_message'] = $e->getMessage();
      CRM_Civirules_Utils_LoggerFactory::logError('Failed to execute rule',  $message, $triggerData, $context);
    }
    return FALSE;
  }

  /**
   * Method to execute the actions
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  protected static function executeActions(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $actionParams = [
      'rule_id' => $triggerData->getTrigger()->getRuleId(),
    ];
    $ruleActions = CRM_Civirules_BAO_RuleAction::getValues($actionParams);
    foreach ($ruleActions as $ruleAction) {
      self::executeAction($triggerData, $ruleAction);
    }
  }

  /**
   * Method to execute a single action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @param array $ruleAction
   */
  public static function executeAction(CRM_Civirules_TriggerData_TriggerData &$triggerData, $ruleAction) {
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
      catch (Exception $e) {
        CRM_Civirules_Utils_LoggerFactory::logError("Failed to execute action",  $e->getMessage(), $triggerData);
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
            $entityData = civicrm_api3($entity, 'getsingle', ['id' => $triggerData->getEntityId()]);
            $entityData = self::useCanonicalFieldNames($entity, $entityData);
            $triggerData->setEntityData($entity, $entityData);
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
    } catch (Exception $e) {
      CRM_Civirules_Utils_LoggerFactory::logError('Failed to execute delayed action',  $e->getMessage(), $triggerData);
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
   */
  public static function delayAction(DateTime $delayTo, CRM_Civirules_ActionEngine_AbstractActionEngine $actionEngine) {
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
        $isValid = self::checkCondition($ruleCondition, $triggerData);
        $firstCondition = FALSE;
      } elseif ($ruleCondition['condition_link'] == 'AND') {
        if ($isValid) {
          $isValid = self::checkCondition($ruleCondition, $triggerData);
        }
      } elseif ($ruleCondition['condition_link'] == 'OR') {
        if (!$isValid) {
          $isValid = self::checkCondition($ruleCondition, $triggerData);
        }
      } else {
        $isValid = FALSE; // we should never reach this statement
      }
      $conditionsValid[$ruleConditionId] = "$ruleConditionId=" . ($isValid ? 'true' : 'false');
    }

    if ($triggerData->getTrigger()->getRuleDebugEnabled()) {
      // Debugging - log validation of conditions
      if (!empty($ruleConditions)) {
        $context = [];
        $context['rule_id'] = $triggerData->getTrigger()->getRuleId();
        $context['conditions_valid'] = implode(';', $conditionsValid);
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
   * @todo: think of a better handling for cron type events
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   */
  protected static function logRule(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $trigger = $triggerData->getTrigger();
    $reactOnEntity = $trigger->getReactOnEntity();
    $daoClass = $reactOnEntity->daoClass;
    if($daoClass) {
      $table = $daoClass::getTableName();
    }
    $ruleId = $trigger->getRuleId();
    $contactId = $triggerData->getContactId();

    $params = [];
    if ($triggerData->getEntityId() && $table && $contactId) {
      $sql = "INSERT INTO `civirule_rule_log` (`rule_id`, `contact_id`, `entity_table`, `entity_id`, `log_date`) VALUES (%1, %2, %3, %4, NOW())";
      $params[1] = [$ruleId, 'Integer'];
      $params[2] = [$contactId, 'Integer'];
      $params[3] = [$table, 'String'];
      $params[4] = [$triggerData->getEntityId(), 'Integer'];
    } elseif ($triggerData->getEntityId() && $table) {
      $sql = "INSERT INTO `civirule_rule_log` (`rule_id`, `entity_table`, `entity_id`, `log_date`) VALUES (%1, %2, %3, NOW())";
      $params[1] = [$ruleId, 'Integer'];
      $params[2] = [$table, 'String'];
      $params[3] = [$triggerData->getEntityId(), 'Integer'];
    } elseif ($contactId) {
      $sql = "INSERT INTO `civirule_rule_log` (`rule_id`, `contact_id`, `log_date`) VALUES (%1, %2, NOW())";
      $params[1] = [$ruleId, 'Integer'];
      $params[2] = [$contactId, 'Integer'];
    } else {
      $sql = "INSERT INTO `civirule_rule_log` (`rule_id`, `log_date`) VALUES (%1, NOW())";
      $params[1] = [$ruleId, 'Integer'];
    }

    if (empty($ruleId)) {
      CRM_Civirules_Utils_LoggerFactory::logError("Failed log rule", "RuleId not set", $triggerData);
    } elseif ($sql) {
      CRM_Core_DAO::executeQuery($sql, $params);
    }
  }

}
