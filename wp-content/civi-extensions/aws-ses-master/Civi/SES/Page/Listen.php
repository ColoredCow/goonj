<?php

namespace Civi\SES\Page;

use GuzzleHttp\Client;

class Listen extends \CRM_Core_Page {
  protected $message;

  public function run() {
    if (!$this->validateSecret()) {
      http_response_code(401);
      echo 'Please pass the correct secret.';
      \CRM_Utils_System::civiExit();
    }

    $this->message = json_decode(file_get_contents('php://input'));
    if ($this->message->Type == 'SubscriptionConfirmation') {
      $this->confirmSubscription();
    }
    elseif ($this->message->Type == 'Notification') {
      $notification = json_decode($this->message->Message);
      $this->processNotification($notification);
    }
  }

  protected function validateSecret() {
    $storedSecret = \Civi::settings()->get('aws_ses_secret');
    $passedSecret = \CRM_Utils_Request::retrieve('secret', 'String');
    return $storedSecret === $passedSecret;
  }

  protected function confirmSubscription() {
    $client = new Client();
    $client->get($this->message->SubscribeURL);
  }

  protected function processNotification($notification) {
    if ($notification->notificationType == 'Bounce') {
      $bounce = new \Civi\SES\Bounce($notification->bounce, $notification->mail);
      $bounce->process();
    }
    elseif ($notification->notificationType == 'Complaint') {
      $complaint = new \Civi\SES\Complaint($notification->complaint, $notification->mail);
      $complaint->process();
    }
  }

}
