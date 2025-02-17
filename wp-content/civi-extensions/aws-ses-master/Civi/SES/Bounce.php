<?php

namespace Civi\SES;

class Bounce {
  protected $bounce;
  protected $mail;

  public function __construct($bounce, $mail) {
    $this->bounce = $bounce;
    $this->mail = $mail;
  }

  public function process() {
    $params = SourceAddress::parse($this->mail->source);

    // The email might not have been sent via CiviMail and therefore might not
    // have a CiviMail style source address
    if (!$params) {
      // let's process bounces for non civimail emails
      $this->processNonCiviMailBounces();
      return;
    }

    $params['bounce_type_id'] = BounceType::getId($this->bounce->bounceType, $this->bounce->bounceSubType);
    $params['body'] = '-empty-';

    civicrm_api3('Mailing', 'event_bounce', $params);
  }

  /**
   * Process non CiviMail bounces
   */
  protected function processNonCiviMailBounces() {
    // Process only permanent or hard bounces and ensure bouncedRecipients is not empty
    if (!in_array($this->bounce->bounceType, ['Permanent', 'HARD']) || empty($this->bounce->bouncedRecipients)) {
      return;
    }

    foreach ($this->bounce->bouncedRecipients as $recipient) {
      // update the bounce email to on_hold
      \Civi\Api4\Email::update(FALSE)
        ->addValue('on_hold:name', 1)
        ->addWhere('email', '=', $recipient->emailAddress)
        ->execute();
    }
  }

}
