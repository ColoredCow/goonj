<?php
/**
 * This extension allows CiviCRM to send emails and process bounces through
 * the Ses service.
 *
 */

use CRM_Ses_ExtensionUtil as E;

class CRM_Ses {

  /**
   * Creates the special Mailing for tracking transactional emails
   *
   * @return Boolean
   *   True if the mailing was created.
   */
  public static function createTransactionalMailing() {
    $result = civicrm_api3('Mailing', 'get', [
      'name' => 'Ses Transactional Emails',
    ]);
    $existingMailing = \Civi\Api4\Mailing::get(FALSE)
      ->addWhere('name', '=', 'Ses Transactional Emails')
      ->execute()
      ->first();

    if (empty($existingMailing)) {
      // Create entry in civicrm_mailing
      $mailingParams = [
        'subject' => E::ts('Ses Transactional Emails (do not delete)'),
        'name' => 'Ses Transactional Emails',
        'url_tracking' => TRUE,
        'forward_replies' => FALSE,
        'auto_responder' => FALSE,
        'open_tracking' => TRUE,
        'is_completed' => FALSE,
      ];

      $mailing = CRM_Mailing_BAO_Mailing::add($mailingParams);

      // Add entry in civicrm_mailing_job
      $saveJob = new CRM_Mailing_DAO_MailingJob();
      $saveJob->start_date = $saveJob->end_date = date('YmdHis');
      $saveJob->status = 'Complete';
      $saveJob->job_type = "Special: All Ses transactional emails";
      $saveJob->mailing_id = $mailing->id;
      $saveJob->save();

      return TRUE;
    }

    return FALSE;
  }

}
