<?php

require_once 'ses.civix.php';

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
  require_once $autoload;
}

use CRM_Ses_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 */
function ses_civicrm_config(&$config): void {
  _ses_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 */
function ses_civicrm_install(): void {
  _ses_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 */
function ses_civicrm_enable(): void {
  _ses_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_alterMailer
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterMailParams
 */
function ses_civicrm_alterMailer(&$mailer, $driver, $params): void {
  // Only process emails if the Outbound method is set to "mail()"
  // otherwise we assume maybe mails are sent by SMTP, or "logged to DB"
  if ($driver == 'mail') {
    $ses = new CRM_Ses_Mail($params);
    try {
      $ses->checkConfig();
      $mailer = $ses;
    }
    catch (Exception $e) {
      \Civi::log('ses')->info('SES is not configured. Falling back to mail(). ' . $e->getMessage());
    }

  }
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function ses_civicrm_managed(&$entities): void {
  // if we have not enabled CiviMail Extension then we can't use Mailing API or similar.
  if (!_isCiviMailEnabled()) {
    return;
  }
  CRM_Ses::createTransactionalMailing();
}

/**
 * Implements hook_civicrm_alterMailParams().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterMailParams
 */
function ses_civicrm_alterMailParams(&$params, $context = NULL): void {
  // if we have not enabled CiviMail Extension then we can't use Mailing API or similar.
  if (!_isCiviMailEnabled()) {
    return;
  }
  switch ($context) {
    case 'civimail':
    case 'flexmailer':
      // Do nothing, we use the verp address in the returnPath
      // But maybe we should set a header instead?
      break;

    case 'messageTemplate':
      // Do nothing (Message templates call this hook twice. We catch those in
      // the 2nd phase where the context is singleEmail)
      break;

    case 'singleEmail':
    case 'testEmail':
      // This is the case for messageTemplate sending after tokenisation, as
      // well as other specialist single email sends.
      // testEmail context happens when clicking "save and send test email" from outbound mail settings.

      // Create meta data for transactional email
      $transactionalMailing = \Civi\Api4\Mailing::get(FALSE)
        ->addSelect('id', 'open_tracking', 'mailing_job.id')
        ->addJoin('MailingJob AS mailing_job', 'LEFT')
        ->addWhere('name', '=', 'SES Transactional Emails')
        ->execute()
        ->first();

      if (empty($transactionalMailing)) {
        Civi::log()->debug('SES: the mailing for transactional emails was not found. Bounces will not be tracked. Disable/enable the SES extension to re-create the mailing.');
        return;
      }

      // Find the Contact ID
      $contactID = NULL;
      $emailID = NULL;
      if (!empty($params['contact_id'])) {
        $contactID = $params['contact_id'];
      }
      elseif (!empty($params['contactId'])) {
        // Contribution/Event confirmation
        $contactID = $params['contactId'];
      }
      else {
        // As last option from email address
        // @todo Does this exclude deleted contacts?
        $emails = \Civi\Api4\Email::get(FALSE)
          ->addSelect('id', 'contact_id')
          ->addWhere('email', '=', trim($params['toEmail']))
          ->execute();
        if ($emails->count() === 1) {
          $contactID = $emails->first()['contact_id'];
          $emailID = $emails->first()['id'];
        }
      }

      if (!$contactID) {
        // Not particularly useful, but devs can insert a backtrace here if they want to debug the cause.
        // Example: for context = singleEmail, we end up here. We should probably fix core.
        Civi::log()->warning('SES: alterMailParams: Context: ' . $context . '; contactID is empty. Not adding returnPath/bounce info for this transactional email: ' . ($params['toEmail'] ?? '') . '; params: ' . print_r($params, TRUE));
        return;
      }

      // We have a contact ID!
      // Make sure we have an Email ID
      if (!$emailID) {
        $email = \Civi\Api4\Email::get(FALSE)
          ->addSelect('id', 'contact_id', 'email')
          ->addWhere('contact_id', '=', $contactID)
          ->execute()
          ->first();
        $emailID = $email['id'];
        if (empty($params['toEmail'])) {
          \Civi::log()->warning("SES: toEmail is empty for contactID {$contactID}. Using default emailID {$emailID} for contact: {$email['email']}");
          $params['toEmail'] = $email['email'];
        }
        elseif ($params['toEmail'] !== $email['email']) {
          \Civi::log()->warning("SES: toEmail {$params['toEmail']} does not match contact email {$email['email']} for {$contactID}. Wrong email ID will be logged in MailingEventQueue. Context: {$context}. Params: " . print_r($params,TRUE));
        }
        if (empty($emailID)) {
          \Civi::log()->warning("SES: emailID is NULL for {$contactID}");
        }
      }

      $eventQueue = CRM_Mailing_Event_BAO_Queue::create([
        'job_id' => $transactionalMailing['mailing_job.id'],
        'contact_id' => $contactID,
        'email_id' => $emailID,
        'mailing_id' => $transactionalMailing['id'],
      ]);

      // Add m to differentiate from bulk mailing
      $emailDomain = CRM_Core_BAO_MailSettings::defaultDomain();
      $verpSeparator = CRM_Core_Config::singleton()->verpSeparator;
      $params['returnPath'] = implode($verpSeparator, ['m', $eventQueue->job_id, $eventQueue->id, $eventQueue->hash]) . "@$emailDomain";

      // add custom headers
      // MJW: Not sure why this is needed?
      // $params['headers']['X-My-Header'] = "This is the mail system at host " . "@$emailDomain" . "I am sorry to have to inform you that your message could not be delivered to one or more recipients. It is attached below. ";

      // add a tracking img if enabled.
      if ($transactionalMailing['open_tracking'] && !empty($params['html'])) {
        $params['html'] .= "\n" . '<img src="' . CRM_Utils_System::externUrl('extern/open', "q=$eventQueue->id")
          . '" width="1" height="1" alt="" border="0">';
      }
      break;

    default:
      // Make noise if we find something unexpected.
      Civi::log()->notice("Undocumented hook_civicrm_alterMailParams context value: " . json_encode($context));
  }
}

/**
 * Check to see if the CiviMail Extension (Component) is installed.
 */
function _isCiviMailEnabled(): bool {
  $extensionCheck = \Civi\Api4\Extension::get(FALSE)
    ->addWhere('key', '=', 'civi_mail')
    ->execute();
  if (count($extensionCheck) == 0 || $extensionCheck[0]['status'] != 'installed') {
    return FALSE;
  }
  return TRUE;
}
