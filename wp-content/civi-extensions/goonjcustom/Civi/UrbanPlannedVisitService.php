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
      '&hook_civicrm_pre' => [
        ['updateVisitStatusAfterAuth'],
      ],
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

  /**
   * This hook is called after a db write on entities.
   *
   * @param string $op
   *   The type of operation being performed.
   * @param string $objectName
   *   The name of the object.
   * @param int $objectId
   *   The unique identifier for the object.
   * @param object $objectRef
   *   The reference to the object.
   */
  public static function updateVisitStatusAfterAuth(string $op, string $objectName, $objectId, &$objectRef) {
    $visitStatusDetails = self::checkVisitStatusAndIds($objectName, $objectId, $objectRef);

    if (!$visitStatusDetails) {
      return;
    }

    $newVisitStatus = $visitStatusDetails['newVisitStatus'];
    $currentVisitStatus = $visitStatusDetails['currentVisitStatus'];

    if ($currentVisitStatus !== $newVisitStatus) {
      if ($newVisitStatus === 'completed') {
        $visitId = $objectRef['id'] ?? NULL;
        if ($visitId === NULL) {
          return;
        }

        $visitFeedbackSent = EckEntity::get('Institution_Visit', TRUE)
          ->addSelect('Visit_Feedback.Feedback_Email_Sent')
          ->addWhere('id', '=', $visitId)
          ->execute()->single();

        $isVisitFeedbackSent = $visitFeedbackSent['Visit_Feedback.Feedback_Email_Sent'];

        if ($isVisitFeedbackSent !== NULL) {
          return;
        }

        $externalCoordinatingPocId = $objectRef['Urban_Planned_Visit.External_Coordinating_PoC'] ?? '';

        $externalCoordinatingGoonjPOC = Contact::get(FALSE)
          ->addSelect('email.email', 'display_name')
          ->addJoin('Email AS email', 'LEFT')
          ->addWhere('id', '=', $externalCoordinatingPocId)
          ->execute()->single();

        $externalCoordinatingGoonjPOCEmail = $externalCoordinatingGoonjPOC['email.email'];
        $externalCoordinatingGoonjPOCName = $externalCoordinatingGoonjPOC['display_name'];

        if (!$externalCoordinatingGoonjPOCEmail) {
          throw new \Exception('External POC email missing');
        }

        $from = HelperService::getDefaultFromEmail();

        $mailParams = [
          'subject' => 'Visit Feedback',
          'from' => $from,
          'toEmail' => $externalCoordinatingGoonjPOCEmail,
          'replyTo' => $from,
          'html' => self::getFeedbackEmailHtml($externalCoordinatingGoonjPOCName, $visitId),
        ];
        $emailSendResult = \CRM_Utils_Mail::send($mailParams);

        if ($emailSendResult) {
          EckEntity::update('Institution_Visit', FALSE)
            ->addValue('Visit_Feedback.Feedback_Email_Sent', 1)
            ->addWhere('id', '=', $visitId)
            ->execute();
        }
      }
    }
  }

  /**
   *
   */
  private static function getFeedbackEmailHtml($externalCoordinatingGoonjPOCName, $visitId) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $visitFeedbackFormUrl = $homeUrl . '/visit-feedback/#?Eck_Institution_Visit1=' . $visitId;

    $html = "
    <p>Dear $externalCoordinatingGoonjPOCName,</p>
    <p>. Please fills out the below form:</p>
    <ol>
        <li><a href=\"$visitFeedbackFormUrl\">Feedback Form</a><br>
    </ol>
    <p>We appreciate your cooperation.</p>
    <p>Warm Regards,<br>Urban Relations Team</p>";

    return $html;
  }

  /**
   * Check the status and return status details.
   *
   * @param string $objectName
   *   The name of the object being processed.
   * @param int $objectId
   *   The ID of the object being processed.
   * @param array &$objectRef
   *   A reference to the object data.
   *
   * @return array|null
   *   An array containing the new and current visit status if valid, or NULL if invalid.
   */
  public static function checkVisitStatusAndIds(string $objectName, $objectId, &$objectRef) {
    if ($objectName != 'Eck_Institution_Visit') {
      return NULL;
    }

    $newVisitStatus = $objectRef['Urban_Planned_Visit.Visit_Status'] ?? '';

    if (!$newVisitStatus || !$objectId) {
      return NULL;
    }

    $visitSource = EckEntity::get('Institution_Visit', FALSE)
      ->addSelect('Urban_Planned_Visit.Visit_Status')
      ->addWhere('id', '=', $objectId)
      ->execute()->single();

    $currentVisitStatus = $visitSource['Urban_Planned_Visit.Visit_Status'] ?? '';

    return [
      'newVisitStatus' => $newVisitStatus,
      'currentVisitStatus' => $currentVisitStatus,
    ];
  }

}
