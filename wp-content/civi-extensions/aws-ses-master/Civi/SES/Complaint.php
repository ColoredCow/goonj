<?php

namespace Civi\SES;

class Complaint {
  protected $complaint;
  protected $mail;

  public function __construct($complaint, $mail) {
    $this->complaint = $complaint;
    $this->mail = $mail;
  }

  public function process() {
    // let's opt out contact for complaints
    foreach ($this->complaint->complainedRecipients as $recipient) {
      $this->optoutContactFromMailings($recipient->emailAddress);
    }

    // let's record the complaint as bounce event
    $params = SourceAddress::parse($this->mail->source);

    // The email might not have been sent via CiviMail and therefore might not
    // have a CiviMail style source address
    if (!$params) {
      // let's process complaints for non civimail emails
      $this->processNonCiviMailComplaints();
      return;
    }

    $params['bounce_type_id'] = BounceType::getId('Complaint', 'Complaint');
    $params['body'] = '-empty-';

    civicrm_api3('Mailing', 'event_bounce', $params);
  }

  /**
   * Function to optout contact from mailings
   *
   * @param string $email
   *
   * @return void
   */
  protected function optoutContactFromMailings($email) {
    // find the contact(s) with this email and optout
    $emails = \Civi\Api4\Email::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('email', '=', $email)
      ->execute();

    foreach ($emails as $email) {
      // opt out contact
      \Civi\Api4\Contact::update(FALSE)
        ->addValue('is_opt_out', TRUE)
        ->addWhere('id', '=', $email['contact_id'])
        ->execute();
    }
  }

  /**
   * Process non CiviMail complaints
   */
  protected function processNonCiviMailComplaints() {
    foreach ($this->complaint->complainedRecipients as $recipient) {
      // update the complaint email to on_hold
      \Civi\Api4\Email::update(FALSE)
        ->addValue('on_hold:name', 1)
        ->addWhere('email', '=', $recipient->emailAddress)
        ->execute();
    }
  }

}
