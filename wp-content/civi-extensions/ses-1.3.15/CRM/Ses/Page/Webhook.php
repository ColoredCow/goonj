<?php

use Civi\Api4\Contact;
use CRM_Ses_ExtensionUtil as E;

/**
 * Simple Email Service webhook page class.
 *
 * Listens and processes bounce events from Amazon SNS
 */
class CRM_Ses_Page_Webhook extends CRM_Core_Page {

  /**
   * Verp Separator.
   *
   * @var string $verp_separator
   */
  protected $verp_separator;

  /**
   * CRM_Core_BAO_MailSettings::defaultLocalpart()
   *
   * @var string $localpart
   */
  protected $localpart;

  /**
   * The SES Notification object.
   *
   * @var object $snsEvent
   */
  protected $snsEvent;

  /**
   * The SES Message object.
   *
   * @var object $snsEventMessage
   */
  protected $snsEventMessage;

  /**
   * CiviCRM Bounce types.
   *
   * @var array $civi_bounce_types
   */
  protected $civi_bounce_types = [];

  /**
   * GuzzleHttp Client.
   *
   * @var object $client Guzzle\Client
   */
  protected $client;

  /**
   * See https://docs.aws.amazon.com/ses/latest/DeveloperGuide/notification-contents.html#bounce-types
   */
  public function getBounceTypeId($bounceType, $bounceSubType) {
    $sesBounceTypes['Undetermined']['Undetermined'] = 'Invalid';
    $sesBounceTypes['Permanent']['General'] = 'Invalid';
    $sesBounceTypes['Permanent']['NoEmail'] = 'Invalid';
    $sesBounceTypes['Permanent']['Suppressed'] = 'Invalid';
    $sesBounceTypes['Permanent']['OnAccountSuppressionList'] = 'Invalid';
    $sesBounceTypes['Transient']['General'] = 'Relay';
    $sesBounceTypes['Transient']['MailboxFull'] = 'Quota';
    $sesBounceTypes['Transient']['MessageTooLarge'] = 'Relay';
    $sesBounceTypes['Transient']['ContentRejected'] = 'Spam';
    $sesBounceTypes['Transient']['AttachmentRejected'] = 'Spam';
    $bounceTypeName = $sesBounceTypes[$bounceType][$bounceSubType];
    return array_search($bounceTypeName, $this->civi_bounce_types);
  }

  /**
   * Constructor.
   */
  public function __construct() {
    $this->client = new GuzzleHttp\Client();

    $this->verp_separator = Civi::settings()->get('verpSeparator');
    $this->localpart = CRM_Core_BAO_MailSettings::defaultLocalpart();
    $this->civi_bounce_types = $this->get_civi_bounce_types();
    // get json input
    $this->snsEvent = json_decode(file_get_contents('php://input'));
    // message object
    $this->snsEventMessage = json_decode($this->snsEvent->Message);

    parent::__construct();
  }

  /**
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function run() {
    // If page was loaded incorrectly (eg. via a browser snsEvent will be empty). Exit gracefully.
    // If we can't verify SNS signature then we add a log message and exit.
    if (empty($this->snsEvent) || !$this->verify_signature()) {
      CRM_Utils_System::civiExit();
    }

    switch ($this->snsEvent->Type) {
      case 'SubscriptionConfirmation':
        // Confirm the SNS subscription and exit
        $this->confirm_subscription();
        CRM_Utils_System::civiExit();
        break;

      case 'Notification':
        break;

      default:
        CRM_Utils_System::civiExit();
    }

    [$job_id, $event_queue_id, $hash] = $this->getVerpItemsFromSource();
    if ((empty($job_id) || empty($event_queue_id) || empty($hash)
      || !CRM_Mailing_Event_BAO_Queue::verify($job_id, $event_queue_id, $hash)) && $this->snsEventMessage->notificationType != 'Complaint') {
      \Civi::log()->error("ses: Unable to identify mailing - transactional? {$this->snsEventMessage->mail->source}. job_id={$job_id},event_queue_id={$event_queue_id},hash={$hash}" . print_r($this->snsEvent, TRUE));
      CRM_Utils_System::civiExit();
    }
    $bounce_params = [
      'job_id' => $job_id,
      'event_queue_id' => $event_queue_id,
      'hash' => $hash,
    ];

    switch ($this->snsEventMessage->notificationType) {
      case 'Bounce':
        $bounce_params = $this->set_bounce_type_params($bounce_params);
        if (!$this->checkIfBouncedEmailMatchesQueueEmail($event_queue_id, $bounce_params['emailAddresses'])) {
          CRM_Utils_System::civiExit();
        }
        if (empty($bounce_params['bounce_type_id'])) {
          // We couldn't classify bounce type - let CiviCRM try!
          $bounce_params['body'] = "Bounce Description: {$this->snsEventMessage->bounce->bounceType} {$this->snsEventMessage->bounce->bounceSubType}";
          civicrm_api3('Mailing', 'event_bounce', $bounce_params);
        }
        else {
          // We've classified the bounce type, record in CiviCRM
          CRM_Mailing_Event_BAO_Bounce::recordBounce($bounce_params);
        }
        break;

      case 'Complaint':
        $bounce_params = $this->map_complaint_types($bounce_params);
        // If we weren't able to map to a known event queue id let us still try and find the email in the database and set the contact on hold at least.
        if (empty($bounce_params['event_queue_id'])) {
          $contact_id = CRM_Core_DAO::singleValueQuery("SELECT contact_id FROM civicrm_email WHERE email = %1 AND contact_id IS NOT NULL LIMIT 1", [1 => [$bounce_params['emailAddress'], 'String']]);
          if (!empty($contact_id)) {
            Contact::update(FALSE)
              ->addValue('is_opt_out', TRUE)
              ->addWhere('id', '=', $contact_id)
              ->execute();
            \Civi::log()->info('ses: Set is_opt_out for contactID: ' . $contact_id);
            CRM_Utils_System::civiExit();
          }
        }
        if (!$this->checkIfBouncedEmailMatchesQueueEmail($event_queue_id, $bounce_params['emailAddresses'])) {
          CRM_Utils_System::civiExit();
        }
        // Opt out the contact and create entries for spam bounces (which only puts the email on hold).
        // This is because the contact likely reported the email as spam as a way to unsubscribe.
        // So opting out only the one email address instead of the contact risks getting any emails sent to their
        // secondary addresses flagged as spam as well, which can hurt our spam score.
        CRM_Mailing_Event_BAO_Bounce::recordBounce($bounce_params);
        $sql = "SELECT cc.id FROM civicrm_contact cc INNER JOIN civicrm_mailing_event_queue cmeq ON cmeq.contact_id = cc.id WHERE cmeq.id = %1";
        $sql_params = [1 => [$bounce_params['event_queue_id'], 'Integer']];
        $contact_id = CRM_Core_DAO::singleValueQuery($sql, $sql_params);

        if (!empty($contact_id)) {
          Contact::update(FALSE)
            ->addValue('is_opt_out', TRUE)
            ->addWhere('id', '=', $contact_id)
            ->execute();
          \Civi::log()->info('ses: Set is_opt_out for contactID: ' . $contact_id);
        }
        break;
    }
    CRM_Utils_System::civiExit();
  }

  /**
   * Currently the MailingEventQueue records a single email address but there may be multiple
   *  in the case of To, Cc, Bcc etc.
   * This function checks that the one referred in MailingEventQueue is actually bouncing
   *  and only records a bounce if it is.
   * It logs a warning for any other email addresses as they won't get recorded by CiviCRM currently.
   *
   * @param int $queueID
   * @param array $emailAddresses
   *
   * @return bool
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  protected function checkIfBouncedEmailMatchesQueueEmail(int $queueID, array $emailAddresses): bool {
    $mailingEventQueue = \Civi\Api4\MailingEventQueue::get(FALSE)
      ->addSelect('email.email')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('id', '=', $queueID)
      ->execute()
      ->first();

    $matches = FALSE;
    foreach ($emailAddresses as $email) {
      if ($mailingEventQueue['email.email'] === $email) {
        $matches = TRUE;
      }
      else {
        \Civi::log()->warning("Email: {$email} bounced but does not match queue {$queueID} email: {$mailingEventQueue['email.email']}");
      }
    }
    return $matches;
  }

  /**
   * Get verp items from a source address in format eg. b.13.6.1d49c3d4f888d58a@example.org
   *
   * @return array The verp items [ $job_id, $queue_id, $hash ]
   */
  protected function getVerpItemsFromSource(): array {
    if (property_exists($this->snsEventMessage->mail, 'commonHeaders')
      && property_exists($this->snsEventMessage->mail->commonHeaders, 'returnPath')) {
      $sourceAddress = $this->snsEventMessage->mail->commonHeaders->returnPath;
    }

    if (empty($sourceAddress)) {
      \Civi::log()->warning('SES: Could not find returnPath! SNS event: ' . print_r($this->snsEvent, TRUE));
      return [];
    }

    // The source address doesn't look like it has a verp in it so lets try and see if we have a X-CiviMail-Bounce Header.
    if (!str_contains($sourceAddress, $this->verp_separator) && property_exists($this->snsEventMessage->mail, 'headers')) {
      foreach ($this->snsEventMessage->mail->headers as $headers) {
        if ($headers['name'] === 'X-CiviMail-Bounce') {
          $sourceAddress = $headers['value'];
        }
      }
    }

    // Strip off the first/localpart
    $verpString = substr($sourceAddress, strlen($this->localpart) + 2);
    // Now strip off the domain
    $verpString = substr($verpString, 0, strpos($verpString, '@'));
    $verpItems = explode($this->verp_separator, $verpString);
    if (count($verpItems) > 1) {
      return $verpItems;
    }

    return [];
  }

  /**
   * Set bounce type params.
   *
   * @param array $bounce_params The params array
   *
   * @return array The params array
   */
  protected function set_bounce_type_params($bounce_params) {
    $bounce_params['bounce_type_id'] = $this->getBounceTypeId($this->snsEventMessage->bounce->bounceType, $this->snsEventMessage->bounce->bounceSubType);

    $reasonParts = [];
    $reasonParts[] = $this->snsEventMessage->bounce->bounceType;
    $reasonParts[] = $this->snsEventMessage->bounce->bounceSubType;
    foreach ($this->snsEventMessage->bounce->bouncedRecipients as $recipient) {
      $recipientReason = '';
      if (!empty($recipient->emailAddress)) {
        $recipientReason .= "email:{$recipient->emailAddress};";
        // See: https://lab.civicrm.org/extensions/ses/-/issues/6
        // SNS docs don't show that emailAddress might be formatted with displayname but real-world results do!
        // We are only interested in the actual email address so extract that
        $bouncedEmails[] = $this->verify_email_address($recipient->emailAddress);
      }
      if (!empty($recipient->action)) {
        $recipientReason .= "action:{$recipient->action};";
      }
      if (!empty($recipient->status)) {
        $recipientReason .= "status:{$recipient->status};";
      }
      if (!empty($recipient->diagnosticCode)) {
        $recipientReason .= "status:{$recipient->diagnosticCode};";
      }
      if (!empty($recipientReason)) {
        $reasonParts[] = "[$recipientReason]";
      }
    }
    $bounce_params['bounce_reason'] = "Bounce via SES: " . implode(" ", $reasonParts);
    $bounce_params['emailAddresses'] = $bouncedEmails ?? [];

    return $bounce_params;
  }

  /**
   * Parses a string and returns the first email address found
   * @param $email_string
   * @return string
   */
  private function verify_email_address($email_string) {
    $pattern = '/[a-zA-Z0-9._%-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
    $email = '';

    if (preg_match($pattern, $email_string, $matches)) {
      $email = $matches[0];
    }
    return $email;
  }

  /**
   * Map Amazon complaint types to Civi bounce types.
   *
   * @param array $params The params array
   *
   * @return array The params array
   */
  protected function map_complaint_types($params) {
    $params['bounce_type_id'] = array_search('Spam', $this->civi_bounce_types);

    foreach ($this->snsEventMessage->complaint->complainedRecipients as $recipient) {
      // See: https://lab.civicrm.org/extensions/ses/-/issues/8
      // SNS docs don't show that emailAddress might be formatted with displayname but real-world results do!
      // We are only interested in the actual email address so extract that
      $complaintEmails[] = $this->verify_email_address($recipient->emailAddress);
    }

    $reasonParts = [];
    if (!empty($this->snsEventMessage->complaint->userAgent)) {
      $reasonParts[] = $this->snsEventMessage->complaint->userAgent;
    }
    if (!empty($this->snsEventMessage->complaint->complaintFeedbackType)) {
      $reasonParts[] = $this->snsEventMessage->complaint->complaintFeedbackType;
    }
    if (!empty($this->snsEventMessage->complaint->complaintSubType)) {
      $reasonParts[] = $this->snsEventMessage->complaint->complaintSubType;
    }
    if (!empty($complaintEmails)) {
      $reasonParts[] = '[' . implode(";", $complaintEmails) . ']';
    }
    if ($reasonParts) {
      $params['bounce_reason'] = "Complaint via SES: " . implode(" ", $reasonParts);
    } else {
      $params['bounce_reason'] = "Complaint via SES (no further details)";
    }

    $params['emailAddresses'] = $complaintEmails ?? [];
    return $params;
  }

  /**
   * Confirm SNS subscription to topic.
   *
   * @see https://docs.aws.amazon.com/sns/latest/dg/sns-message-and-json-formats.html#http-subscription-confirmation-json
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function confirm_subscription() {
    // To confirm the subscription we must "visit" the provided SubscribeURL
    $this->client->request('POST', $this->snsEvent->SubscribeURL);
    \Civi::log()->info('ses: SNS subscription confirmed');
  }

  /**
   * Verify SNS Message signature.
   *
   * @see https://docs.aws.amazon.com/sns/latest/dg/SendMessageToHttp.verify.signature.html
   * @return bool true if successful
   */
  protected function verify_signature() {
    // keys needed for signature
    $keys_to_sign = ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'];
    // for SubscriptionConfirmation the keys are slightly different
    if ($this->snsEvent->Type == 'SubscriptionConfirmation')
      $keys_to_sign = ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];

    // build message to sign
    $message = '';
    foreach ($keys_to_sign as $key) {
      if (isset($this->snsEvent->$key))
        $message .= "{$key}\n{$this->snsEvent->$key}\n";
    }

    if (!isset($this->snsEvent->Signature) || !isset($this->snsEvent->SigningCertURL)) {
      \Civi::log()->error('ses: SNS signature verification failed! Missing Signature or SigningCertURL - check you have "Raw Message Delivery: Disabled" on the subscription');
      return FALSE;
    }

    // decode SNS signature
    $sns_signature = base64_decode($this->snsEvent->Signature);

    // get certificate from SigningCertURL and extract public key
    $public_key = openssl_get_publickey(file_get_contents($this->snsEvent->SigningCertURL));

    // verify signature
    $signed = openssl_verify($message, $sns_signature, $public_key, OPENSSL_ALGO_SHA1);

    if ($signed && $signed != -1)
      return TRUE;

    \Civi::log()->error('ses: SNS signature verification failed!');
    return FALSE;
  }

  /**
   * Get CiviCRM bounce types.
   *
   * @return array
   */
  protected function get_civi_bounce_types() {
    if (!empty($this->civi_bounce_types)) return $this->civi_bounce_types;

    $query = 'SELECT id,name FROM civicrm_mailing_bounce_type';
    $dao = CRM_Core_DAO::executeQuery($query);

    $civi_bounce_types = [];
    while ($dao->fetch()) {
      $civi_bounce_types[$dao->id] = $dao->name;
    }

    return $civi_bounce_types;
  }
}
