<?php

require_once 'Mail/RFC822.php';

use CRM_Ses_ExtensionUtil as E;
use Aws\Exception\AwsException;

class CRM_Ses_Mail extends Mail {

  /*
   * The AWS SDK for PHP v3 defines a number of error codes as being related
   * to throttling. We define most of these throttling related codes here for
   * use later in the file.
   * Ref: https://github.com/aws/aws-sdk-php/blob/e226dcc96c0a1165d9c8248ec637d1006b883609/src/RetryMiddleware.php#L28
   *
   * We're using this as published documentation does not include codes that
   * have been encountered in production.
   */
  const SES_THROTTLING_ERROR_CODES = [
    'RequestLimitExceeded',
    'Throttling',
    'ThrottlingException',
    'ThrottledException',
    'RequestThrottled',
    'BandwidthLimitExceeded',
    'RequestThrottledException',
    'TooManyRequestsException'
  ];

  /**
   * @return string
   *   Ex: 'smtp', 'sendmail', 'mail'.
   */
  public function getDriver() {
    return 'ses';
  }

  /**
   * Check if config is valid
   *
   * @return bool
   * @throws \Exception
   */
  public function checkConfig(): bool {
    $ses_access_key = Civi::settings()->get('ses_access_key');
    $ses_secret_key = Civi::settings()->get('ses_secret_key');
    $ses_region = Civi::settings()->get('ses_region');

    $errorMessage = 'To send email using the SES API please set it in the Administer > CiviMail > SES settings.';
    if (empty($ses_access_key)) {
      throw new Exception('No API key defined for SES. ' . $errorMessage);
    }
    if (empty($ses_secret_key)) {
      throw new Exception('No API secret defined for SES. ' . $errorMessage);
    }
    if (empty($ses_region)) {
      throw new Exception('No Region defined for SES. ' . $errorMessage);
    }
    return TRUE;
  }

  /**
   * Send an email using SES SendRawEmail() function
   *
   * @param mixed $recipients Either a comma-seperated list of recipients
   *              (RFC822 compliant), or an array of recipients,
   *              each RFC822 valid. This may contain recipients not
   *              specified in the headers, for Bcc:, resending
   *              messages, etc.
   *
   * @param array $headers The array of headers to send with the mail, in an
   *              associative array, where the array key is the
   *              header name (ie, 'Subject'), and the array value
   *              is the header value (ie, 'test'). The header
   *              produced from those values would be 'Subject:
   *              test'.
   *
   * @param string $body The full text of the message body, including any
   *               Mime parts, etc.
   *
   * @return mixed Returns true on success, or a PEAR_Error
   *               containing a descriptive error message on
   *               failure.
   */
  public function send($recipients, $headers, $body) {
    if (defined('CIVICRM_MAIL_LOG')) {
      CRM_Utils_Mail_Logger::filter($this, $recipients, $headers, $body);
      if (!defined('CIVICRM_MAIL_LOG_AND_SEND')) {
        return TRUE;
      }
    }

    // Sanitize and prepare headers for transmission
    if (!is_array($headers)) {
      return new PEAR_Error('SES: $headers must be an array');
    }

    // $headers['Bcc'] gets unset before calling this function because it assumes it won't get removed by the mailer.
    // But SES needs the full headers and then it removes it when processing.
    // So we have to add it back in. We can get it from the last element in the $recipients array:
    // $recipients = [
    // 0: To (single email)
    // 1: Cc (comma separated string)
    // 2: Bcc (comma separated string)
    // ]
    // BUT BE CAREFUL! To or Cc might not be defined so it won't always be element 2. It will always be the last element
    //   but we don't know if we actually have any Bcc addresses without checking the To, Cc headers first.
    // Eg. if you have To+Bcc then $recipients[0] is To and $recipients[1] is Bcc but if you have To+Cc then $recipients[1]
    // is Cc and if $recipients[2] is not set you have no Bcc and don't need to add the header... confused yet?
    // Also, if called via flexmailer Civi\FlexMailer\Listener then $recipients will be a string only containing the To address.

    if (is_array($recipients)) {
      // Work out which $recipients keys represent To, Cc and Bcc
      if (array_key_exists('To', $headers)) {
        $newRecipients['To'] = array_shift($recipients);
      }
      if (array_key_exists('Cc', $headers)) {
        $newRecipients['Cc'] = array_shift($recipients);
      }
      $newRecipients['Bcc'] = array_shift($recipients);
      if (!empty($newRecipients['Bcc'])) {
        // The order of headers might not matter (I didn't check if SES cares or not).
        // But we insert the Bcc header in the right place anyway. ie. after Cc or To or From.
        if (array_key_exists('Cc', $headers)) {
          $insertBccAfterHeader = 'Cc';
        }
        elseif (array_key_exists('To', $headers)) {
          $insertBccAfterHeader = 'To';
        }
        else {
          $insertBccAfterHeader = 'From';
        }
        foreach ($headers as $headerKey => $headerValue) {
          $newHeaders[$headerKey] = $headerValue;
          if ($headerKey === $insertBccAfterHeader) {
            $newHeaders['Bcc'] = $newRecipients['Bcc'];
          }
        }
        $headers = $newHeaders;
      }
    }

    $this->_sanitizeHeaders($headers);
    $headerElements = $this->prepareHeaders($headers);

    if (is_a($headerElements, 'PEAR_Error')) {
      return $headerElements;
    }

    list($from, $textHeaders) = $headerElements;

    // Invoke SesClient singleton
    $SesClient = CRM_Ses_SesClient::getInstance();

    // $recipient_emails = $this->formatRecipients($recipients);
    // $subject = $headers['Subject'];

    $raw_body = $textHeaders . "\r\n\r\n" . $body;

    if (preg_match('/<style\w+type="text\/css">/', $body)) {
      $body = preg_replace('/<style\w+type="text\/css">/', '<html><head><style type="text/css">', $body);
      $body = preg_replace('/<\/style>/', '</head></style>', $body);
    }

    // Send using exponential backoff if SES responds with a ThrottlingException
    // cf. https://aws.amazon.com/blogs/messaging-and-targeting/how-to-handle-a-throttling-maximum-sending-rate-exceeded-error/
    $maxRetries = 10;
    $retries = 0;
    $retryDelay = 50000; // 50ms delay

    for ($retries = 0; $retries < $maxRetries; $retries++) {
      try {
        $result = $SesClient->sendRawEmail([
          'RawMessage' => [
            'Charset' => 'UTF-8',
            'Data' => $raw_body,
          ],
        ]);
        return $result;
      } catch (AwsException $e) {
        $errorCode = $e->getAwsErrorCode();
        if (in_array($errorCode, self::SES_THROTTLING_ERROR_CODES)) {
          Civi::log('ses')->warning('Amazon SES maximum send rate exceeded. Throttling detected.');
          if ($retries < $maxRetries) {
            usleep($retryDelay * (2 ** $retries));
          } else {
            Civi::log('ses')->error('Maximum throttling retries reached. Email delivery failed.');
            return new PEAR_Error($e->getMessage());
          }
        } else {
          // Handle other AWS exceptions
          Civi::log('ses')->error('AWS exception encountered: ' . $e->getAwsErrorCode() . ': ' . $e->getAwsErrorMessage());
          return new PEAR_Error($e->getMessage());
        }
      } catch (Exception $e) {
        // Handle other exceptions
        return new PEAR_Error($e->getMessage());
      }
    }

    // $messageId = $result['MessageId'];
    // Civi::log()->debug("SES Email sent! Message ID: $messageId");
  }

  /**
   * Prepares a recipient list in the format SES expects.
   *
   * Copied from the Sparkpost extension. Not exactly sure if really necessary.
   *
   * @param mixed $recipients
   *   List of recipients, either as a string or an array.
   * @return array
   *   An array of recipients in the format that the SparkPost API expects.
   */
  public function formatRecipients($recipients) {
    // CiviCRM passes the recipients as an array of string, each string potentially containing
    // multiple addresses in either abbreviated or full RFC822 format, e.g.
    // $recipients:
    //   [0] nicolas@cividesk.com, Nicolas Ganivet <nicolas@cividesk.com>
    //   [1] "Ganivet, Nicolas" <nicolas@cividesk.com>
    //   [2] ""<nicolas@cividesk.com>,<nicolas@cividesk.com>
    // [0] are the most common cases, [1] note the , inside the quoted name, [2] are edge cases
    // cf. CRM_Utils_Mail::send() lines 161, 171 and 174 (assignments to the $to variable)
    if (!is_array($recipients)) {
      $recipients = [$recipients];
    }

    $result = [];

    foreach ($recipients as $recipientString) {
      // Best is to use the PEAR::Mail package to decapsulate as they have a class just for that!
      $rfc822 = new Mail_RFC822($recipientString);
      $matches = $rfc822->parseAddressList();

      foreach ($matches as $match) {
        $address = '';
        if (!empty($match->mailbox) && !empty($match->host)) {
          $address = $match->mailbox . '@' . $match->host;
        }
        if (!empty($match->personal)) {
          if ((substr($match->personal, 0, 1) == '"') && (substr($match->personal, -1) == '"')) {
            $address = $match->personal . ' <' . $address . '>';
          } else {
            $address = '"' . $match->personal . '" <' . $address . '>';
          }
        }
        if ($address) {
          $result[] = $address;
        }
      }
    }

    return $result;
  }

}
