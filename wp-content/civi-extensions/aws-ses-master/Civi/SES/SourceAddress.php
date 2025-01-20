<?php

namespace Civi\SES;

/**
 * We should really be able to rely on a core function for this.
 */
class SourceAddress {

  public static function parse($address) {
    $mailSettings = \Civi\Api4\MailSettings::get(FALSE)
      ->addWhere('is_default', '=', TRUE)
      ->execute()->first();

    $vs = preg_quote(\Civi::settings()->get('verpSeparator'));
    $regex = '/^' . preg_quote($mailSettings['localpart']) . '(b|c|e|o|r|u)' . $vs . '(\d+)' . $vs . '(\d+)' . $vs . '([0-9a-f]{16})@' . preg_quote($mailSettings['domain']) . '$/';
    $matches = array();

    preg_match($regex, $address, $matches);

    if (!$matches) {
      return FALSE;
    }

    list($match, $action, $job, $queue, $hash) = $matches;

    $params = array(
      'job_id' => $job,
      'event_queue_id' => $queue,
      'hash' => $hash,
    );

    return $params;
  }

}
