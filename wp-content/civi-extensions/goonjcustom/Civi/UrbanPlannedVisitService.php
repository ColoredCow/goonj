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
        ['sendVisitFeedbackForm'],
        ['sendVisitDetails'],
      ],
    ];
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
  public static function sendVisitDetails(string $op, string $objectName, $objectId, &$objectRef) {
    $visitStatusDetails = self::checkAuthVisitStatusAndIds($objectName, $objectId, $objectRef);

    if (!$visitStatusDetails) {
      return;
    }

    $newAuthVisitStatus = $visitStatusDetails['newAuthVisitStatus'];
    $currentAuthVisitStatus = $visitStatusDetails['currentAuthVisitStatus'];

    if ($currentAuthVisitStatus !== $newAuthVisitStatus && $newAuthVisitStatus === 'authorized') {
      $visitId = $objectRef['id'] ?? NULL;
      if ($visitId === NULL) {
        return;
      }

      $visitFeedbackSent = EckEntity::get('Institution_Visit', TRUE)
        ->addSelect('Visit_Feedback.Visit_Email_Sent')
        ->addWhere('id', '=', $visitId)
        ->execute()->single();

      $isVisitEmailSent = $visitFeedbackSent['Visit_Feedback.Visit_Email_Sent'];

      if ($isVisitEmailSent !== NULL) {
        return;
      }

      $externalCoordinatingPocId = $objectRef['Urban_Planned_Visit.External_Coordinating_PoC'] ?? '';
      $visitGuide = $objectRef['Urban_Planned_Visit.Visit_Guide'];

      $externalCoordinatingGoonjPoc = Contact::get(FALSE)
        ->addSelect('email.email', 'display_name')
        ->addJoin('Email AS email', 'LEFT')
        ->addWhere('id', '=', $externalCoordinatingPocId)
        ->execute()->single();

      $externalCoordinatingGoonjPocEmail = $externalCoordinatingGoonjPoc['email.email'];
      $externalCoordinatingGoonjPocName = $externalCoordinatingGoonjPoc['display_name'];

      $visitGuide = Contact::get(FALSE)
        ->addSelect('email.email', 'display_name')
        ->addJoin('Email AS email', 'LEFT')
        ->addWhere('id', '=', $visitGuide)
        ->execute()->single();

      $visitGuideEmail = $visitGuide['email.email'];
      $visitGuideName = $visitGuide['display_name'];

      if (!$externalCoordinatingGoonjPocEmail) {
        throw new \Exception('External POC email missing');
      }

      $from = HelperService::getDefaultFromEmail();

      $mailParamsExternalPoc = [
        'subject' => 'Visit Details',
        'from' => $from,
        'toEmail' => $externalCoordinatingGoonjPocEmail,
        'replyTo' => $from,
        'html' => self::getFeedbackEmailHtml($externalCoordinatingGoonjPocName, $visitId),
      ];

      $mailParamsVisitGuide = [
        'subject' => 'Visit Details',
        'from' => $from,
        'toEmail' => $visitGuideEmail,
        'replyTo' => $from,
        'html' => self::getFeedbackEmailHtml($externalCoordinatingGoonjPocName, $visitId),
      ];

      // Send the first email.
      $emailSendResultToExternalPoc = \CRM_Utils_Mail::send($mailParamsExternalPoc);

      // Send the second email.
      $emailSendResultToVisitGuide = \CRM_Utils_Mail::send($mailParamsVisitGuide);

      If ($emailSendResultToExternalPoc) {
        EckEntity::update('Institution_Visit', FALSE)
          ->addValue('Visit_Feedback.Visit_Email_Sent', 1)
          ->addWhere('id', '=', $visitId)
          ->execute();
      }
    }
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
  public static function sendVisitFeedbackForm(string $op, string $objectName, $objectId, &$objectRef) {
    $visitStatusDetails = self::checkVisitStatusAndIds($objectName, $objectId, $objectRef);

    if (!$visitStatusDetails) {
      return;
    }

    $newVisitStatus = $visitStatusDetails['newVisitStatus'];
    $currentVisitStatus = $visitStatusDetails['currentVisitStatus'];

    if ($currentVisitStatus !== $newVisitStatus && $newVisitStatus === 'completed') {
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

      $externalCoordinatingGoonjPoc = Contact::get(FALSE)
        ->addSelect('email.email', 'display_name')
        ->addJoin('Email AS email', 'LEFT')
        ->addWhere('id', '=', $externalCoordinatingPocId)
        ->execute()->single();

      $externalCoordinatingGoonjPocEmail = $externalCoordinatingGoonjPoc['email.email'];
      $externalCoordinatingGoonjPocName = $externalCoordinatingGoonjPoc['display_name'];

      if (!$externalCoordinatingGoonjPocEmail) {
        throw new \Exception('External POC email missing');
      }

      $from = HelperService::getDefaultFromEmail();

      $mailParams = [
        'subject' => 'Visit Feedback',
        'from' => $from,
        'toEmail' => $externalCoordinatingGoonjPocEmail,
        'replyTo' => $from,
        'html' => self::getFeedbackEmailHtml($externalCoordinatingGoonjPocName, $visitId),
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

  /**
   *
   */
  private static function getFeedbackEmailHtml($externalCoordinatingGoonjPocName, $visitId) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $visitFeedbackFormUrl = $homeUrl . '/visit-feedback/#?Eck_Institution_Visit1=' . $visitId;

    $html = "
    <p>Dear $externalCoordinatingGoonjPocName,</p>
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
    $newAuthVisitStatus = $objectRef['Urban_Planned_Visit.Status'] ?? '';
    error_log("newAuthVisitStatus: " . print_r($newAuthVisitStatus, TRUE));

    if (!$newVisitStatus || !$objectId  || !$newAuthVisitStatus) {
      return NULL;
    }

    $visitSource = EckEntity::get('Institution_Visit', FALSE)
      ->addSelect('Urban_Planned_Visit.Visit_Status', 'Urban_Planned_Visit.Status')
      ->addWhere('id', '=', $objectId)
      ->execute()->single();

    $currentVisitStatus = $visitSource['Urban_Planned_Visit.Visit_Status'] ?? '';
    $currentAuthVisitStatus = $visitSource['Urban_Planned_Visit.Status'] ?? '';

    error_log("newAuthVisitStatus: " . print_r($newAuthVisitStatus, TRUE));
    error_log("currentAuthVisitStatus: " . print_r($currentAuthVisitStatus, TRUE));

    return [
      'newVisitStatus' => $newVisitStatus,
      'currentVisitStatus' => $currentVisitStatus,
      'newAuthVisitStatus' => $newAuthVisitStatus,
      'currentAuthVisitStatus' => $currentAuthVisitStatus,
    ];
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
  public static function checkAuthVisitStatusAndIds(string $objectName, $objectId, &$objectRef) {
    if ($objectName != 'Eck_Institution_Visit') {
      return NULL;
    }

    $newAuthVisitStatus = $objectRef['Urban_Planned_Visit.Status'] ?? '';

    if (!$newAuthVisitStatus || !$objectId) {
      return NULL;
    }

    $visitSource = EckEntity::get('Institution_Visit', FALSE)
      ->addSelect('Urban_Planned_Visit.Visit_Status', 'Urban_Planned_Visit.Status')
      ->addWhere('id', '=', $objectId)
      ->execute()->single();

    $currentAuthVisitStatus = $visitSource['Urban_Planned_Visit.Status'] ?? '';

    return [
      'newAuthVisitStatus' => $newAuthVisitStatus,
      'currentAuthVisitStatus' => $currentAuthVisitStatus,
    ];
  }

}
