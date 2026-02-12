<?php

/**
 * Class for Goonjcustom engine.
 */
class CRM_Goonjcustom_Engine {

  const QUEUE_NAME = 'goonjcustom.action';

  /**
   *
   */
  public static function processQueue(int $maxSeconds = 60): array {
    $returnValues = [
      'processed' => 0,
      'results' => [],
    ];

    $queue = \CRM_Queue_Service::singleton()->create([
      'name' => self::QUEUE_NAME,
      'type' => 'Sql',
    ]);

    $runner = new CRM_Queue_Runner([
      'title' => ts('Send Authorization Email Queue Runner'),
      'queue' => $queue,
      'errorMode' => CRM_Queue_Runner::ERROR_CONTINUE,
    ]);

    $maxSeconds = max(1, (int) $maxSeconds);
    $maxRunTime = time() + $maxSeconds;
    $continue = TRUE;

    // Loop to process the queue items.
    while (time() < $maxRunTime && $continue) {
      $result = $runner->runNext(FALSE);

      if (!$result['is_continue']) {
        $continue = FALSE;
      }

      if (isset($result['exception']) && $result['exception'] instanceof \Throwable) {
        $result['exception'] = [
          'type' => get_class($result['exception']),
          'message' => $result['exception']->getMessage(),
        ];
      }

      $returnValues['results'][] = $result;
      $returnValues['processed']++;
    }

    return $returnValues;
  }

}
