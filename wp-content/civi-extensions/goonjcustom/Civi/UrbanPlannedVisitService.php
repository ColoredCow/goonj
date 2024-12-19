<?php

namespace Civi;

use Civi\Core\Service\AutoSubscriber;
use Civi\Api4\Contact;


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
    $coordinatingGoonjPOCId = $visit['Urban_Planned_Visit.Coordinating_Goonj_POC'];

    $coordinatingGoonjPOC = Contact::get(FALSE)
      ->addSelect('email.email', 'display_name')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('id', '=', $coordinatingGoonjPOCId)
      ->execute()->single();

    $coordinatingGoonjPOCEmail = $coordinatingGoonjPOC['email.email'];
    $coordinatingGoonjPOCName = $coordinatingGoonjPOC['display_name'];
    error_log("coordinatingGoonjPOCEmail: " . print_r($coordinatingGoonjPOCEmail, TRUE));
    error_log("coordinatingGoonjPOCName: " . print_r($coordinatingGoonjPOCName, TRUE));

    if (!$coordinatingGoonjPOCEmail) {
      throw new \Exception('POC email missing');
    }

    $from = HelperService::getDefaultFromEmail();

    $mailParams = [
      'subject' => 'Urban Planned Visit',
      'from' => $from,
      'toEmail' => $coordinatingGoonjPOCEmail,
      'replyTo' => $from,
      'html' => self::getOutcomeEmailHtml($coordinatingGoonjPOCName),
    ];
    \CRM_Utils_Mail::send($mailParams);
  }

  /**
   *
   */
  private static function getOutcomeEmailHtml($coordinatingGoonjPOCName) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $visitOutcomeFormUrl = $homeUrl . '/visit-outcome-form/';

    $html = "
    <p>Dear $coordinatingGoonjPOCName,</p>
    <p>Thank you for attending the visit. Please fills out the below form:</p>
    <ol>
        <li><a href=\"$visitOutcomeFormUrl\">Camp Outcome Form</a><br>
        This feedback form should be filled out after the camp/drive ends, once you have an overview of the event's outcomes.</li>
    </ol>
    <p>We appreciate your cooperation.</p>
    <p>Warm Regards,<br>Urban Relations Team</p>";

    return $html;
  }

}
