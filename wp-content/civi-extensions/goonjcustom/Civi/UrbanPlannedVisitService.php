<?php

namespace Civi;

use Civi\Core\Service\AutoSubscriber;
use Civi\Api4\Contact;
use Civi\Api4\EckEntity;


/**
 *
 */
class UrbanPlannedVisitService extends AutoSubscriber {
  private static $individualId = NULL;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
    // @todo
    //   '&hook_civicrm_post' => [
    //     ['assignChapterGroupToIndividualForUrbanPlannedVisit'],
    //   ],.
    ];
  }

  /**
   *
   */
  public static function sendOutcomeEmail($visit) {
    $visitId = $visit['id'];
    $coordinatingGoonjPOCId = $visit['Urban_Planned_Visit.Coordinating_Goonj_POC'];

    $coordinatingGoonjPOC = Contact::get(FALSE)
      ->addSelect('email.email', 'display_name')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('id', '=', $coordinatingGoonjPOCId)
      ->execute()->single();

    $coordinatingGoonjPOCEmail = $coordinatingGoonjPOC['email.email'];
    $coordinatingGoonjPOCName = $coordinatingGoonjPOC['display_name'];

    if (!$coordinatingGoonjPOCEmail) {
      throw new \Exception('POC email missing');
    }

    $from = HelperService::getDefaultFromEmail();

    $mailParams = [
      'subject' => 'Urban Planned Visit',
      'from' => $from,
      'toEmail' => $coordinatingGoonjPOCEmail,
      'replyTo' => $from,
      'html' => self::getOutcomeEmailHtml($coordinatingGoonjPOCName, $visitId),
    ];
    $emailSendResult = \CRM_Utils_Mail::send($mailParams);

    if ($emailSendResult) {
      EckEntity::update('Institution_Visit', FALSE)
        ->addValue('Visit_Outcome.Outcome_Email_Sent', 1)
        ->addWhere('id', '=', $visitId)
        ->execute();
    }
  }

  /**
   *
   */
  private static function getOutcomeEmailHtml($coordinatingGoonjPOCName, $visitId) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $visitOutcomeFormUrl = $homeUrl . '/visit-outcome/#?Eck_Institution_Visit1=' . $visitId;

    $html = "
    <p>Dear $coordinatingGoonjPOCName,</p>
    <p>. Please fills out the below form:</p>
    <ol>
        <li><a href=\"$visitOutcomeFormUrl\">Camp Outcome Form</a><br>
    </ol>
    <p>We appreciate your cooperation.</p>
    <p>Warm Regards,<br>Urban Relations Team</p>";

    return $html;
  }

}
