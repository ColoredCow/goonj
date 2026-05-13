<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_Civirules_Utils_LoggerFactory {

  private static $logger = null;

  private static $loggerHookInvoked = false;

  /**
   * @return \Psr\Log\LoggerInterface|NULL
   */
  public static function getLogger() {
    if (empty(self::$logger) && self::$loggerHookInvoked === false) {
      $hook = CRM_Civirules_Utils_HookInvoker::singleton();
      $hook->hook_civirules_getlogger(self::$logger);
      self::$loggerHookInvoked = true;
      if (empty(self::$logger)) {
        self::$logger = Civi::log();
      }
    }
    return self::$logger;
  }

  public static function log($message, $context=[], $level=\Psr\Log\LogLevel::INFO) {
    $logger = CRM_Civirules_Utils_LoggerFactory::getLogger();
    if (empty($logger)) {
      return;
    }
    $logger->log($level, $message, $context);
  }

  /**
   * @param string $reason
   * @param string $original_error
   * @param \CRM_Civirules_TriggerData_TriggerData $triggerData
   * @param array $context
   *
   * @return void
   */
  public static function logError(string $reason, string $original_error, CRM_Civirules_TriggerData_TriggerData $triggerData, $context=[]) {
    $logger = CRM_Civirules_Utils_LoggerFactory::getLogger();
    if (empty($logger)) {
      return;
    }
    $context['rule_id'] = $triggerData->getTrigger()->getRuleId();
    $context['rule_title'] = $triggerData->getTrigger()->getRuleTitle();
    $context['original_error'] = $original_error;
    $context['contact_id'] = $triggerData->getContactId();
    $context['reason'] = $reason;
    $error = E::ts(
      "Rule: '%1' with id %2 failed for contact %3 because: %4",
      [
        1 => $context['rule_title'],
        2 => $context['rule_id'],
        3 => $context['contact_id'],
        4 => $context['reason'],
      ]
    );
    $logger->error($error, $context);
  }

}
