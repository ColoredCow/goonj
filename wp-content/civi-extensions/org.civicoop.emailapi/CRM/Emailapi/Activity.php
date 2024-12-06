<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

use \Civi\Api4\Email;

/**
 * Class CRM_Emailapi_Activity
 */
class CRM_Emailapi_Activity {

  public $contactEmails = [];

  /**
   * Create an email activity
   *
   * @param array $params
   *
   * @return int|NULL
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function createActivity($params) {
    if (empty($params['status_id'])) {
      // Create it as Cancelled - we update it to Completed if it successfully sends
      $params['status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'status_id', 'Cancelled');
    }

    if (!empty($params['contactId']) && empty($params['target_contact_id'])) {
      $params['target_contact_id'] = $params['contactId'];
    }

    $ccArray = isset($params['cc']) ? explode(',', $params['cc']) : [];
    $this->getEmailString($ccArray);
    $additionalDetails = empty($ccArray) ? '' : "\ncc : " . $this->getEmailUrlString($ccArray);

    $bccArray = isset($params['bcc']) ? explode(',', $params['bcc']) : [];
    $this->getEmailString($bccArray);
    $additionalDetails .= empty($bccArray) ? '' : "\nbcc : " . $this->getEmailUrlString($bccArray);

    // Save both text and HTML parts in details (if present)
    if (!empty($params['html']) && !empty($params['text'])) {
      $details = "-ALTERNATIVE ITEM 0-\n{$params['html']}{$additionalDetails}\n-ALTERNATIVE ITEM 1-\n{$params['text']}{$additionalDetails}\n-ALTERNATIVE END-\n";
    }
    else {
      $details = $params['html'] ?? $params['text'] ?? '';
      $details .= $additionalDetails;
    }

    // We must have a source contact. Try the logged in contact, or if not use the domain contact ID.
    $sourceContactID = CRM_Core_Session::getLoggedInContactID() ?? civicrm_api3('Domain', 'getvalue', ['return' => 'contact_id']);
    $activityParams = [
      'activity_type_id' => "Email",
      'source_contact_id' => $sourceContactID,
      'subject' => $params['subject'] ?? '',
      'details' => $details,
      'status_id' => $params['status_id'],
    ];
    if (!empty($params['case_id'])) {
      $activityParams['case_id'] = $params['case_id'];
    }

    // add the attachments to activity params here
    if (!empty($params['attachments'])) {
      // first process them
      $activityParams = array_merge($activityParams, $params['attachments']);
    }

    try {
      $activity = civicrm_api3('Activity', 'create', $activityParams);
      if (!empty($params['target_contact_id'])) {
        $activityTargetParams = [
          'activity_id' => $activity['id'],
          'contact_id' => $params['target_contact_id'],
          'record_type_id' => CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_ActivityContact', 'record_type_id', 'Activity Targets'),
        ];
        civicrm_api3('ActivityContact', 'create', $activityTargetParams);
      }
    }
    catch (Exception $e) {
      \Civi::log()->error('Failed to create Email activity. ' . $e->getMessage());
      return NULL;
    }
    return $activity['id'];
  }

  /**
   * Sugar to update the activity to completed.
   *
   * @param int $activityID
   */
  public function completeActivity($activityID) {
    $params = [
      'id' => $activityID,
      'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'status_id', 'Completed')
    ];
    civicrm_api3('Activity', 'create', $params);
  }
  /**
   * Get the string for the email IDs.
   *
   * @param array $emailIDs
   *   Array of email IDs.
   *
   * @return string
   *   e.g. "Smith, Bob<bob.smith@example.com>".
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  protected function getEmailString(array $emailIDs): string {
    if (empty($emailIDs)) {
      return '';
    }
    $emails = Email::get()
      ->addWhere('id', 'IN', $emailIDs)
      ->setCheckPermissions(FALSE)
      ->setSelect(['contact_id', 'email', 'contact.sort_name', 'contact.display_name'])->execute();
    $emailStrings = [];
    foreach ($emails as $email) {
      $this->contactEmails[$email['id']] = $email;
      $emailStrings[] = '"' . $email['contact.sort_name'] . '" <' . $email['email'] . '>';
    }
    return implode(',', $emailStrings);
  }

  /**
   * Get the url string.
   *
   * This is called after the contacts have been retrieved so we don't need to re-retrieve.
   *
   * @param array $emailIDs
   *
   * @return string
   *   e.g. <a href='{$contactURL}'>Bob Smith</a>'
   */
  protected function getEmailUrlString(array $emailIDs): string {
    $urlString = '';
    foreach ($emailIDs as $email) {
      $contactURL = CRM_Utils_System::url('civicrm/contact/view', ['reset' => 1, 'force' => 1, 'cid' => $this->contactEmails[$email]['contact_id']], TRUE);
      $urlString .= "<a href='{$contactURL}'>" . $this->contactEmails[$email]['contact.display_name'] . '</a>';
    }
    return $urlString;
  }


}
