<?php

/**
 * Class for Goonjcustom engine.
 */
class CRM_Goonjcustom_Engine {

  const QUEUE_NAME = 'goonjcustom.action';

  /**
   *
   */
  public static function processQueue($params) {
    $returnValues = [];

    $queue = \CRM_Queue_Service::singleton()->create([
      'name' => self::QUEUE_NAME,
      'type' => 'Sql',
    ]);

    $runner = new CRM_Queue_Runner([
      'title' => ts('Send Authorization Email Queue Runner'),
      'queue' => $queue,
      'errorMode' => CRM_Queue_Runner::ERROR_CONTINUE,
    ]);

    $maxRunTime = time() + 30;
    $continue = TRUE;

    // Loop to process the queue items.
    while (time() < $maxRunTime && $continue) {
      $result = $runner->runNext(FALSE);

      if (!$result['is_continue']) {
        $continue = FALSE;
      }

      $returnValues[] = $result;
    }

    return civicrm_api3_create_success($returnValues, $params, 'SendAuthorizationQueue', 'Run');
  }

}
