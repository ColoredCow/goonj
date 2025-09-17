<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\Email;
use Civi\Api4\MessageTemplate;
use Civi\Api4\OptionValue;
use Civi\Api4\Relationship;
use Civi\Api4\StateProvince;
use Civi\Core\Service\AutoSubscriber;

/**
 *
 */
class InductionService extends AutoSubscriber {
  const INDUCTION_ACTIVITY_TYPE_NAME = 'Induction';
  const INDUCTION_DEFAULT_STATUS_NAME = 'To be scheduled';
  const RELATIONSHIP_TYPE_NAME = 'Induction Coordinator of';

  private static $volunteerId = NULL;
  private static $volunteerInductionAssigneeEmail = NULL;
  private static $transitionedVolunteerId = NULL;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_pre' => [
        ['hasIndividualChangedToVolunteer'],
        ['sendActivityEmailToVolunteer'],
      ],
      '&hook_civicrm_post' => [
            ['volunteerCreated'],
            ['createInductionForVolunteer'],
            ['createInductionForTransitionedVolunteer'],
            ['sendInductionEmailToVolunteer'],
            ['sendInductionEmailForTransitionedVolunteer'],
      ],
      '&hook_civicrm_custom' => [
        ['volunteerInductionAssignee'],
      ],
    ];
  }

  private const OTHER_TEMPLATE_TITLE = 'Volunteer Acitvity Email For Other';

  /**
   *
   */
  public static function sendActivityEmailToVolunteer(string $op, string $objectName, $objectId, &$objectRef): bool {
    if ($op !== 'create' || $objectName !== 'AfformSubmission' || ($objectRef['afform_name'] ?? '') !== 'afformScheduleInductionForm') {
      return FALSE;
    }

    try {
      $data = $objectRef['data'] ?? [];
      $activityId   = $data['Activity1'][0]['fields']['id'];
      $statusId   = $data['Activity1'][0]['fields']['status_id'];
      \Civi::log()->info('ActivityEmail: Email sent', ['to' => $data, 'template' => $data]);
      $individualId = $data['Individual1'][0]['fields']['id'];

      if ($statusId !== 2) {
        return FALSE;
      }

      if (!$activityId || !$individualId) {
        return FALSE;
      }

      $selectedValue = $data['Individual1'][0]['fields']['Volunteer_fields.Selected_Activity_By_Urban_Ops'];

      $selectedLabel = '';
      if (!empty($selectedValue)) {
        $field = CustomField::get(FALSE)
          ->addSelect('option_group_id')
          ->addWhere('custom_group_id:name', '=', 'Volunteer_fields')
          ->addWhere('name', '=', 'Which_activities_are_you_interested_in_')
          ->execute()
          ->first();

        $opt = OptionValue::get(FALSE)
          ->addSelect('label', 'value')
          ->addWhere('option_group_id', '=', (int) $field['option_group_id'])
          ->addWhere('value', '=', $selectedValue)
          ->execute()
          ->first();

        $selectedLabel = trim((string) ($opt['label'] ?? ''));
      }

      $contact = Contact::get(FALSE)
        ->addSelect('display_name')
        ->addWhere('id', '=', $individualId)
        ->execute()
        ->first();

      if (!$contact) {
        error_log("ActivityEmail ERROR: Contact not found: {$individualId}");
        return FALSE;
      }
      $contactName = trim((string) ($contact['display_name'] ?? ''));

      $emailRow = Email::get(FALSE)
        ->addSelect('email')
        ->addWhere('contact_id', '=', $individualId)
        ->addWhere('is_primary', '=', TRUE)
        ->execute()
        ->first();

      $toEmail = trim((string) ($emailRow['email'] ?? ''));
      if ($toEmail === '') {
        return FALSE;
      }

      $activity = Activity::get(FALSE)
        ->addSelect('id')
        ->addWhere('id', '=', $activityId)
        ->execute()
        ->first();

      if (!$activity) {
        return FALSE;
      }

      $ccEmails = [];
      $assigneeNames = [];

      $assignId = (int) ($data['Activity1'][0]['fields']['Induction_Fields.Assign'] ?? 0);
      if ($assignId) {
        $assignEmailRow = Email::get(FALSE)
          ->addSelect('email')
          ->addWhere('contact_id', '=', $assignId)
          ->addWhere('is_primary', '=', TRUE)
          ->execute()
          ->first();

        $assignEmail = trim((string) ($assignEmailRow['email'] ?? ''));
        if ($assignEmail !== '') {
          $ccEmails[] = $assignEmail;
        }
        $assignContactRow = Contact::get(FALSE)
          ->addSelect('display_name')
          ->addWhere('id', '=', $assignId)
          ->execute()
          ->first();

        if (!empty($assignContactRow['display_name'])) {
          $assigneeNames[] = trim((string) $assignContactRow['display_name']);
        }
      }

      $cityPocName = '';
      if (!empty($assigneeNames)) {
        $cityPocName = implode(', ', array_unique($assigneeNames));
      }

      $applyTokens = function ($str) use ($contactName, $cityPocName) {
        if ($str === NULL || $str === '') {
          return $str;
        }
        $patterns = [
          '/\{\{\s*(?:acitivity|activity)\.display_name\s*\}\}/i',
          '/\{\{\s*(?:acitivity|activity)\.city_poc\s*\}\}/i',
        ];
        $replacements = [
          $contactName,
          $cityPocName,
        ];
        return preg_replace($patterns, $replacements, $str);
      };

      $isOthers = (bool) preg_match('/^\s*others?\b/i', $selectedLabel);
      $templateTitleCandidate = $isOthers ? self::OTHER_TEMPLATE_TITLE : ($selectedLabel ?: self::OTHER_TEMPLATE_TITLE);

      $normalizeTitle = function (string $s): string {
        $map = [
          "\xE2\x80\x98" => "'",
          "\xE2\x80\x99" => "'",
          "\xE2\x80\x9C" => '"',
          "\xE2\x80\x9D" => '"',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
      };

      $templateTitle = $normalizeTitle($templateTitleCandidate);

      $template = NULL;

      try {
        $template = civicrm_api3('MessageTemplate', 'getsingle', [
          'msg_title' => $templateTitle,
        ]);
      }
      catch (\Throwable $e) {
      }

      if (!$template) {
        try {
          $res = civicrm_api3('MessageTemplate', 'get', [
            'sequential' => 1,
            'options' => ['limit' => 1],
            'msg_title' => ['LIKE' => $templateTitle],
          ]);
          if (!empty($res['count'])) {
            $template = $res['values'][0] ?? NULL;
          }
        }
        catch (\Throwable $e) {
          // ignore; try fallback.
        }
      }

      if (!$template && !$isOthers) {
        try {
          $res = civicrm_api3('MessageTemplate', 'get', [
            'sequential' => 1,
            'options' => ['limit' => 1],
            'msg_title' => self::OTHER_TEMPLATE_TITLE,
          ]);
          if (!empty($res['count'])) {
            $template = $res['values'][0] ?? NULL;
          }
        }
        catch (\Throwable $e) {
        }
      }

      if (!$template) {
        return FALSE;
      }

      $subject = trim((string) ($template['msg_subject'] ?? ''));
      $html    = (string) ($template['msg_html'] ?? '');
      $text    = trim((string) ($template['msg_text'] ?? ''));

      if ($html !== '' && $text === '') {
        $text = \CRM_Utils_String::htmlToText($html);
      }
      if ($html === '' && $text === '') {
        return FALSE;
      }

      $subject = $applyTokens($subject);
      $html    = $applyTokens($html);
      $text    = $applyTokens($text);

      $from = HelperService::getDefaultFromEmail();
      $params = [
        'from'    => $from,
        'toName'  => $contactName,
        'toEmail' => $toEmail,
        'subject' => $subject,
        'html'    => $html,
        'text'    => $text,
      ];
      if (!empty($ccEmails)) {
        $params['cc'] = implode(',', $ccEmails);
      }

      \CRM_Utils_Mail::send($params);

      self::createEmailActivity(
        targetContactId: $individualId,
        subject: $subject,
        html: $html,
        text: $text,
        parentActivityId: $activityId ?: NULL,
        assigneeId: $individualId,
      );

      return TRUE;

    }
    catch (\Throwable $e) {
      \Civi::log()->info('ActivityEmail: Email sent', ['to' => $toEmail, 'template' => $templateTitle]);
      return FALSE;
    }
  }

  /**
   *
   */
  private static function createEmailActivity(
    int $targetContactId,
    string $subject,
    ?string $html = NULL,
    ?string $text = NULL,
    ?int $parentActivityId = NULL,
    ?int $assigneeId = NULL,
  ): bool {
    try {

      $payload = [
        'activity_type_id:name' => 'Email',
        'subject'               => $subject ?: 'Email sent',
        'details'               => ($html ?: $text ?: ''),
        'status_id:name'        => 'Completed',
        'activity_date_time'    => date('Y-m-d H:i:s'),
        'source_contact_id'     => $targetContactId,
        'target_contact_id'     => [$targetContactId],
      ];

      if (!empty($parentActivityId)) {
        $payload['parent_id'] = (int) $parentActivityId;
      }
      if (!empty($assigneeId)) {
        $payload['assignee_contact_id'] = [(int) $assigneeId];
      }

      Activity::create(FALSE)
        ->setValues($payload)
        ->execute();

      \Civi::log()->info('ActivityEmail: Follow-up Activity created', [
        'for'       => $targetContactId,
        'parent_id' => $parentActivityId,
        'type'      => 'Email',
      ]);

      return TRUE;
    }
    catch (\Throwable $e) {
      \Civi::log()->info('ActivityEmail: Failed to create follow-up Activity', [
        'for'   => $targetContactId,
        'error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   *
   */
  public static function volunteerCreated(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'Individual') {
      return FALSE;
    }

    \Civi::log()->info('Individual created: ', [
      'id' => $objectId,
      'subtypes' => $objectRef->contact_sub_type,
    ]);

    $subTypes = $objectRef->contact_sub_type;

    if (empty($subTypes)) {
      return FALSE;
    }

    // The ASCII control character \x01 represents the "Start of Header".
    // It is used to separate values internally by CiviCRM for multiple subtypes.
    $subtypes = explode("\x01", $subTypes);
    $subtypes = array_filter($subtypes);

    if (!in_array('Volunteer', $subtypes)) {
      return FALSE;
    }

    self::$volunteerId = $objectId;

    \Civi::log()->info('Volunteer set: ', [
      'id' => self::$volunteerId,
    ]);
  }

  /**
   * Common logic for creating an induction.
   */
  private static function createInduction(int $contactId, int $stateId) {
    if (self::inductionExists($contactId)) {
      \Civi::log()->info('Induction already exists for contact', ['id' => $contactId]);
      return FALSE;
    }

    $office = self::findOfficeForState($stateId);
    if (!$office) {
      \Civi::log()->info('Cannot find office for: ' . $stateId);
      return FALSE;
    }

    $coordinatorId = self::findCoordinatorForOffice($office['id']);
    if (!$coordinatorId) {
      \Civi::log()->info('Cannot found induction coordinator for office:', ['id' => $office['id']]);
      return FALSE;
    }

    $sourceContactId = self::getCurrentUserOrVolunteer($contactId);
    if (!$sourceContactId) {
      \Civi::log()->info('Cannot found sourceContactId', ['id' => $sourceContactId]);
      return FALSE;
    }

    $targetContactId = ($sourceContactId === $contactId) ? $contactId : $contactId;
    if (!$targetContactId) {
      \Civi::log()->info('Cannot found targetContactId', ['id' => $targetContactId]);
      return FALSE;
    }

    $placeholderActivityDate = self::getPlaceholderActivityDate();

    // Fetch induction activities for the target contact.
    $contactInductionExists = Activity::get(FALSE)
      ->addWhere('activity_type_id:name', '=', 'Induction')
      ->addWhere('target_contact_id', '=', $targetContactId)
      ->execute();

    // Check if an induction activity already exists.
    if ($contactInductionExists->count() > 0) {
      return;
    }

    Activity::create(FALSE)
      ->addValue('activity_type_id:name', self::INDUCTION_ACTIVITY_TYPE_NAME)
      ->addValue('status_id:name', self::INDUCTION_DEFAULT_STATUS_NAME)
      ->addValue('source_contact_id', $sourceContactId)
      ->addValue('target_contact_id', $targetContactId)
      ->addValue('Induction_Fields.Assign', $coordinatorId)
      ->addValue('activity_date_time', $placeholderActivityDate)
      ->addValue('Induction_Fields.Goonj_Office', $office['id'])
      ->execute();

    return TRUE;
  }

  /**
   * Handles induction creation for a volunteer.
   */
  public static function createInductionForVolunteer(string $op, string $objectName, int $objectId, &$objectRef) {

    if ($op !== 'create' || $objectName !== 'Address' || self::$volunteerId !== $objectRef->contact_id || !$objectRef->is_primary) {
      return FALSE;
    }

    $stateId = $objectRef->state_province_id;

    if (!$stateId) {
      \Civi::log()->info('state not found', ['VolunteerId' => self::$volunteerId, 'stateId' => $stateId]);
      return FALSE;
    }

    if (self::$volunteerId & $stateId) {
      self::createInduction(self::$volunteerId, $stateId);
    }
  }

  /**
   * Get the current user ID or volunteer ID.
   */
  private static function getCurrentUserOrVolunteer($volunteerId) {
    $session = \CRM_Core_Session::singleton();
    return $session->get('userID') ?: $volunteerId;
  }

  /**
   * Find office based on state.
   */
  public static function findOfficeForState($stateId) {
    return Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_type', '=', 'Organization')
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addWhere('Goonj_Office_Details.Induction_Catchment', 'CONTAINS', $stateId)
      ->execute()->first();
  }

  /**
   * Find coordinator for office.
   */
  public static function findCoordinatorForOffice($officeId) {
    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $officeId)
      ->addWhere('relationship_type_id:name', '=', self::RELATIONSHIP_TYPE_NAME)
      ->addWhere('is_active', '=', TRUE)
      ->execute();

    $coordinatorCount = $coordinators->count();

    return $coordinatorCount > 1
      ? $coordinators->itemAt(rand(0, $coordinatorCount - 1))['contact_id_a']
      : $coordinators->first()['contact_id_a'];
  }

  /**
   * Common logic to send an email.
   */
  private static function sendInductionEmail($volunteerId) {
    \Civi::log()->info('Initiating induction email process', ['volunteerId' => $volunteerId]);

    if (self::isEmailAlreadySent($volunteerId)) {
      \Civi::log()->info('Induction email already sent', ['volunteerId' => $volunteerId]);
      return FALSE;
    }

    if (empty($volunteerId)) {
      \Civi::log()->info('Volunteer ID is empty');
      return;
    }

    $contact = Contact::get(FALSE)
      ->addSelect('address_primary.state_province_id')
      ->addWhere('id', '=', $volunteerId)
      ->execute()->first();

    \Civi::log()->info('Contact fetched', ['contact' => $contact]);

    $stateId = $contact['address_primary.state_province_id'];
    if (!$stateId) {
      \Civi::log()->info('State not found', ['contactId' => $contact['id'], 'StateId' => $stateId]);
      return FALSE;
    }

    \Civi::log()->info('Fetched state for volunteer', ['volunteerId' => $volunteerId, 'stateId' => $stateId]);

    $inductionType = self::fetchTypeOfInduction($volunteerId);
    \Civi::log()->info('Induction type determined', ['volunteerId' => $volunteerId, 'inductionType' => $inductionType]);

    $templateName = ($inductionType === 'Offline')
        ? 'New_Volunteer_Registration%'
        : 'New_Volunteer_Registration_Online%';

    $template = MessageTemplate::get(FALSE)
      ->addWhere('msg_title', 'LIKE', $templateName)
      ->setLimit(1)
      ->execute()->single();

    if (!$template) {
      \Civi::log()->info('No email template found', ['templateName' => $templateName]);
      return FALSE;
    }

    \Civi::log()->info('Email template found', ['templateId' => $template['id']]);

    $emailParams = [
      'contact_id' => $volunteerId,
      'template_id' => $template['id'],
      'cc' => self::$volunteerInductionAssigneeEmail,
    ];

    $inductionActivity = Activity::get(FALSE)
      ->addWhere('activity_type_id:name', '=', self::INDUCTION_ACTIVITY_TYPE_NAME)
      ->addWhere('status_id:name', 'IN', ['Scheduled', 'Completed', 'To be scheduled', 'Cancelled'])
      ->addWhere('target_contact_id', '=', $volunteerId)
      ->setLimit(1)
      ->execute();

    if ($inductionActivity->count() === 0) {
      \Civi::log()->info('No induction activity found, creating new one');
      self::createInduction($volunteerId, $stateId);
    }
    else {
      \Civi::log()->info('Induction activity already exists');
    }

    \Civi::log()->info('Queuing induction email');
    self::queueInductionEmail($emailParams);

    \Civi::log()->info('Marking email as sent in custom field');
    Contact::update(FALSE)
      ->addValue('Individual_fields.Volunteer_Registration_Email_Sent', 1)
      ->addWhere('id', '=', $volunteerId)
      ->execute();

    \Civi::log()->info('Induction email process completed', ['volunteerId' => $volunteerId]);
    return TRUE;
  }

  /**
   * Queue the induction email to be processed later.
   */
  private static function queueInductionEmail($params) {
    \Civi::log()->info('Preparing to queue induction email', ['contactId' => $params['contact_id']]);

    try {
      $queue = \Civi::queue(\CRM_Goonjcustom_Engine::QUEUE_NAME, [
        'type' => 'Sql',
        'error' => 'abort',
        'runner' => 'task',
      ]);

      \Civi::log()->info('Queue initialized for induction email', ['contactId' => $params['contact_id']]);

      $queue->createItem(new \CRM_Queue_Task(
            [self::class, 'processQueuedInductionEmail'],
            [$params]
        ), [
          'weight' => 1,
        ]);

      \Civi::log()->info('Induction email queued for contact', ['contactId' => $params['contact_id']]);
    }
    catch (\CRM_Core_Exception $ex) {
      \Civi::log()->error('Failed to queue induction email due to CiviCRM error', [
        'contactId' => $params['contact_id'],
        'error' => $ex->getMessage(),
      ]);
    }
  }

  /**
   * Process the queued induction email task.
   */
  public static function processQueuedInductionEmail($queue, $params) {
    try {
      $result = civicrm_api3('Email', 'send', $params);
      if ($result['is_error']) {
        throw new \CRM_Core_Exception($result['error_message']);
      }
      \Civi::log()->info('Successfully sent queued induction email', [
        'params' => $params,
      ]);
      return TRUE;
    }
    catch (\Exception $ex) {
      \Civi::log()->error('Failed to send queued induction email', [
        'params' => $params,
        'error' => $ex->getMessage(),
      ]);
      // Rethrow the exception for the queue system to handle.
      throw $ex;
    }
  }

  /**
   * Handles sending induction email to a volunteer.
   */
  public static function sendInductionEmailToVolunteer(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'Email' || !$objectId || $objectRef->contact_id !== self::$volunteerId) {
      return;
    }
    \Civi::log()->info('Checking conditions for sending induction email');

    self::sendInductionEmail(self::$volunteerId);
  }

  /**
   * Handles sending induction email to an individual.
   */
  public static function sendInductionEmailForTransitionedVolunteer(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'edit' || $objectName !== 'Individual' || (int) self::$transitionedVolunteerId !== (int) $objectRef->id) {
      return FALSE;
    }

    $contacts = Contact::get(FALSE)
      ->addWhere('contact_sub_type:name', '=', 'Volunteer')
      ->execute();
    $contact = $contacts->first();
    if (!empty($contact)) {
      // Return the contact if it exists.
      return FALSE;
    }
    self::sendInductionEmail(self::$transitionedVolunteerId);
  }

  /**
   * Get placeholder time for induction activity.
   * Calculate the date and time 3 days from today at 11:00 AM.
   * If the resulting date is on a weekend (Saturday or Sunday), adjust to the next Monday at 11:00 AM.
   *
   * @return string The formatted date and time for 3 days later or the next Monday at 11 AM.
   */
  private static function getPlaceholderActivityDate() {
    $date = new \DateTime();
    $date->modify('+3 days');
    $dayOfWeek = $date->format('N');

    if ($dayOfWeek >= 6) {
      $date->modify('next monday');
    }

    $date->setTime(11, 0);
    return $date->format('Y-m-d H:i:s');
  }

  /**
   * This hook is called after the database write on a custom table.
   *
   * @param string $op
   *   The type of operation being performed.
   * @param string $objectName
   *   The custom group ID.
   * @param int $objectId
   *   The entityID of the row in the custom table.
   * @param object $objectRef
   *   The parameters that were sent into the calling function.
   */
  public static function volunteerInductionAssignee($op, $groupID, $entityID, &$params) {
    if ($op !== 'create') {
      return;
    }

    if (!($inductionFields = self::findInductionOfficeFields($params))) {
      return;
    }

    if (!$inductionFields['Assign']) {
      return;
    }

    $assignee = Contact::get(FALSE)
      ->addSelect('email.email')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('id', '=', $inductionFields['Assign']['value'])
      ->addWhere('email.is_primary', '=', TRUE)
      ->setLimit(1)
      ->execute()->single();

    self::$volunteerInductionAssigneeEmail = $assignee['email.email'];

  }

  /**
   *
   */
  private static function findInductionOfficeFields(array $array) {
    $filteredItems = array_filter($array, fn($item) => $item['entity_table'] === 'civicrm_activity');

    if (empty($filteredItems)) {
      return FALSE;
    }

    $inductionOfficeFields = CustomField::get(FALSE)
      ->addSelect('name')
      ->addWhere('custom_group_id:name', '=', 'Induction_Fields')
      ->addWhere('name', 'IN', ['Goonj_Office', 'Assign'])
      ->execute();

    if ($inductionOfficeFields->count() === 0) {
      return FALSE;
    }

    $inductionOfficeFieldValues = [];

    foreach ($inductionOfficeFields as $field) {
      $fieldIndex = array_search(TRUE, array_map(fn($item) =>
      $item['entity_table'] === 'civicrm_activity' &&
      $item['custom_field_id'] == $field['id'],
      $filteredItems
      ));

      $inductionOfficeFieldValues[$field['name']] = $fieldIndex !== FALSE ? $filteredItems[$fieldIndex] : FALSE;
    }

    return $inductionOfficeFieldValues;
  }

  /**
   * Check if induction activity already exists for the contact.
   */
  private static function inductionExists($contactId) {
    $inductionActivity = Activity::get(FALSE)
      ->addWhere('activity_type_id:name', '=', self::INDUCTION_ACTIVITY_TYPE_NAME)
      ->addWhere('status_id:name', 'IN', ['Scheduled', 'Completed', 'To be scheduled', 'Cancelled'])
      ->addWhere('target_contact_id', '=', $contactId)
      ->setLimit(1)
      ->execute();

    return $inductionActivity->count() > 0;
  }

  /**
   * Check if induction email has already been sent.
   * Adjust the logic based on actual email activity type.
   */
  private static function isEmailAlreadySent($contactId) {

    if ($contactId) {
      $contactDetails = Contact::get(FALSE)
        ->addSelect('Individual_fields.Volunteer_Registration_Email_Sent')
        ->addWhere('id', '=', $contactId)
        ->execute()->single();
    }
    $isEmailSent = $contactDetails['Individual_fields.Volunteer_Registration_Email_Sent'] ?? NULL;

    if (!empty($isEmailSent)) {
      // Email already sent.
      return TRUE;
    }

    return FALSE;
  }

  /**
   *
   */
  public static function hasIndividualChangedToVolunteer($op, $objectName, $id, &$params) {
    if ($op !== 'edit' || $objectName !== 'Individual') {
      return FALSE;
    }
    // Check if 'entryURL' is set and contains '/contribute'.
    if (isset($params['entryURL']) && strpos($params['entryURL'], '/contribute') !== FALSE) {
      // Return if '/contribute' is found in the entryURL.
      return FALSE;
    }

    $newSubtypes = $params['contact_sub_type'] ?? [];

    if (!is_array($newSubtypes)) {
      $newSubtypes = [$newSubtypes];
    }
    // Check if "Volunteer" is present in the contact_sub_type array.
    if (!in_array('Volunteer', $newSubtypes)) {
      // Exit the function if "Volunteer" is not present.
      return;
    }

    $contacts = Contact::get(FALSE)
      ->addSelect('contact_sub_type')
      ->addWhere('id', '=', $id)
      ->execute()->single();

    if ($contacts['contact_sub_type' === 'Volunteer']) {
      return;
    }
    self::$transitionedVolunteerId = $contacts['id'];
  }

  /**
   *
   */
  public static function createInductionForTransitionedVolunteer(string $op, string $objectName, int $objectId, &$objectRef) {

    if ($op !== 'edit' || $objectName !== 'Individual' || (int) self::$transitionedVolunteerId !== (int) $objectRef->id) {
      return FALSE;
    }

    $contact = Contact::get(FALSE)
      ->addSelect('address.state_province_id')
      ->addJoin('Address AS address', 'LEFT')
      ->addWhere('id', '=', $objectId)
      ->execute()->single();

    $stateId = $contact['address.state_province_id'];
    if (!$stateId) {
      \Civi::log()->info(['State not found :', ['contactId' => $contact['id'], 'StateId' => $stateId]]);
      return FALSE;
    }

    if (self::$transitionedVolunteerId & $stateId) {
      self::createInduction(self::$transitionedVolunteerId, $stateId);
    }
  }

  /**
   *
   */
  public static function sendFollowUpEmails() {
    $followUpDays = 7;
    $followUpTimestamp = strtotime("-{$followUpDays} days");

    // Retrieve the email template for follow-up.
    $template = MessageTemplate::get(FALSE)
      ->addSelect('id', 'msg_subject')
      ->addWhere('msg_title', 'LIKE', 'Induction_slot_booking_follow_up_email%')
      ->execute()->single();

    $batchSize = 25;
    $offset = 0;

    do {
      // Retrieve a batch of unscheduled induction activities older than 7 days.
      $unscheduledInductionActivities = Activity::get(FALSE)
        ->addSelect('id', 'source_contact_id', 'created_date')
        ->addWhere('activity_type_id:name', '=', 'Induction')
        ->addWhere('status_id:name', '=', 'To be scheduled')
        ->addWhere('Induction_Fields.Follow_Up_Email_Sent', '=', 0)
        ->addWhere('created_date', '<', date('Y-m-d H:i:s', $followUpTimestamp))
        ->setLimit($batchSize)
        ->setOffset($offset)
        ->execute();
      // Process each activity in the batch.
      foreach ($unscheduledInductionActivities as $activity) {
        // Check if a reschedule email has already been sent and handled.
        if (self::handleRescheduleEmailActivity($activity['source_contact_id'], $activity['id'])) {
          continue;
        }

        $contactsDetails = Contact::get(FALSE)
          ->addSelect('Individual_fields.Induction_slot_booking_follow_up_email_sent')
          ->addWhere('id', '=', $activity['source_contact_id'])
          ->execute();

        $contacts = $contactsDetails->first();

        if (empty($contacts)) {
          continue;
        }

        $isMailSent = $contacts['Individual_fields.Induction_slot_booking_follow_up_email_sent'] ?? NULL;

        if (in_array($isMailSent, [NULL, FALSE], TRUE)) {

          $emailParams = [
            'contact_id' => $activity['source_contact_id'],
            'template_id' => $template['id'],
          ];

          $emailSent = civicrm_api3('Email', 'send', $emailParams);

          $contact = Contact::update(FALSE)
            ->addValue('Individual_fields.Induction_slot_booking_follow_up_email_sent', 1)
            ->addWhere('id', '=', $activity['source_contact_id'])
            ->execute();
          $emailSentDate = new \DateTime();

          $timeIn12Hours = (clone $emailSentDate)->modify('+12 hours');

          $formattedEmailSentDate = $emailSentDate->format('Y-m-d H:i:s');
          $results = Activity::update(FALSE)
            ->addValue('Induction_Fields.Follow_Up_Email_Sent', 1)
            ->addValue('Induction_Fields.Follow_Up_Email_Sent_Date', $formattedEmailSentDate)
            ->addWhere('id', '=', $activity['id'])
            ->execute();

        }
      }

      $offset += $batchSize;

    } while (count($unscheduledInductionActivities) === $batchSize);
  }

  /**
   *
   */
  public static function updateInductionStatusNoShow() {
    $followUpDays = 30;
    $followUpTimestamp = strtotime("-$followUpDays days");
    $batchSize = 25;
    $offset = 0;

    try {
      // Fetch the follow-up message template.
      $template = MessageTemplate::get(FALSE)
        ->addSelect('id', 'msg_subject')
        ->addWhere('msg_title', 'LIKE', 'Induction_slot_booking_follow_up_email%')
        ->execute()->single();

      if (!$template) {
        throw new \Exception('Follow-up email template not found.');
      }

      $unscheduledInductionContactIds = Activity::get(FALSE)
        ->addSelect('source_contact_id')
        ->addWhere('activity_type_id:name', '=', 'Induction')
        ->addWhere('status_id:name', '=', 'To be scheduled')
        ->addWhere('Induction_Fields.Follow_Up_Email_Sent', '=', 1)
        ->addWhere('Induction_Fields.Follow_Up_Email_Sent_Date', '<', date('Y-m-d H:i:s', $followUpTimestamp))
        ->execute()->column('source_contact_id');

      do {
        // Fetch email activities older than 30 days.
        $contacts = Contact::get(FALSE)
          ->addWhere('Individual_fields.Induction_slot_booking_follow_up_email_sent', '=', 1)
          ->addWhere('id', 'IN', $unscheduledInductionContactIds)
          ->setLimit($batchSize)
          ->setOffset($offset)->execute();

        foreach ($contacts as $contact) {
          // Fetch the associated induction activity.
          $inductionActivities = Activity::get(FALSE)
            ->addSelect('id', 'source_contact_id', 'status_id:name')
            ->addWhere('activity_type_id:name', '=', 'Induction')
            ->addWhere('source_contact_id', '=', $contact['id'])
            ->addWhere('status_id:name', '=', 'To be scheduled')
            ->execute();

          $inductionActivity = $inductionActivities->first();

          if (!$inductionActivity) {
            \Civi::log()->info('No induction activity found for source contact', [
              'source_contact_id' => $contact['id'],
            ]);
            continue;
          }

          // Update the induction status to 'No_show'.
          $updateResult = Activity::update(FALSE)
            ->addValue('status_id:name', 'No_show')
            ->addWhere('id', '=', $inductionActivity['id'])
            ->execute();
        }

        // Increment the offset by the batch size.
        $offset += $batchSize;
      } while (count($contacts) === $batchSize);

    }
    catch (\Exception $e) {
      \Civi::log()->error('Error in updating induction status: ' . $e->getMessage());
      throw $e;
    }
  }

  /**
   *
   */
  public static function sendInductionRescheduleEmail() {
    $rescheduleEmailDelayDays = 1;
    $rescheduleEmailTimestamp = strtotime("-{$rescheduleEmailDelayDays} days");

    // Retrieve the email template for reschedule-email.
    $template = MessageTemplate::get(FALSE)
      ->addSelect('id', 'msg_subject')
      ->addWhere('msg_title', 'LIKE', 'Induction_reschedule_slot_booking%')
      ->execute()->single();

    $batchSize = 25;
    $offset = 0;

    do {
      // Retrieve a batch of not visited induction activities older than 1 days.
      $notVisitedInductionActivities = Activity::get(FALSE)
        ->addSelect('id', 'source_contact_id', 'created_date')
        ->addWhere('activity_type_id:name', '=', 'Induction')
        ->addWhere('status_id:name', '=', 'Not Visited')
        ->addWhere('modified_date', '<', date('Y-m-d H:i:s', $rescheduleEmailTimestamp))
        ->setLimit($batchSize)
        ->setOffset($offset)
        ->execute();

      foreach ($notVisitedInductionActivities as $activity) {
        $contactsDetails = Contact::get(FALSE)
          ->addSelect('Individual_fields.Induction_Reschedule_Email_Sent')
          ->addWhere('id', '=', $activity['source_contact_id'])
          ->execute();

        $contacts = $contactsDetails->first();

        if (empty($contacts)) {
          continue;
        }

        $isMailSent = $contacts['Individual_fields.Induction_Reschedule_Email_Sent'] ?? NULL;

        if (!empty($isMailSent)) {
          // If an email has been sent, mark activity as 'No Show'.
          $updateResult = Activity::update(FALSE)
            ->addValue('status_id:name', 'No_show')
            ->addWhere('id', '=', $activity['id'])
            ->execute();
          continue;
        }

        // If no email exists, mark activity for to be scheduled and send the email.
        $updateResult = Activity::update(FALSE)
          ->addValue('status_id:name', 'To be scheduled')
          ->addWhere('id', '=', $activity['id'])
          ->execute();

        $emailParams = [
          'contact_id' => $activity['source_contact_id'],
          'template_id' => $template['id'],
        ];

        $emailResult = civicrm_api3('Email', 'send', $emailParams);
        $contact = Contact::update(FALSE)
          ->addValue('Individual_fields.Induction_Reschedule_Email_Sent', 1)
          ->addWhere('id', '=', $activity['source_contact_id'])
          ->execute();
      }

      $offset += $batchSize;

    } while (count($notVisitedInductionActivities) === $batchSize);
  }

  /**
   *
   */
  public static function handleRescheduleEmailActivity($contactId, $activityId) {
    $template = MessageTemplate::get(FALSE)
      ->addSelect('id', 'msg_subject')
      ->addWhere('msg_title', 'LIKE', 'Induction_reschedule_slot_booking%')
      ->execute()->single();

    $contactsDetails = Contact::get(FALSE)
      ->addSelect('Individual_fields.Induction_Reschedule_Email_Sent')
      ->addWhere('id', '=', $contactId)
      ->execute();

    $contacts = $contactsDetails->first();

    // Check if the contact exists before proceeding.
    if (empty($contacts)) {
      // Return false if contact does not exist.
      return FALSE;
    }

    $isMailSent = $contacts['Individual_fields.Induction_Reschedule_Email_Sent'] ?? NULL;

    if (!empty($isMailSent)) {
      // Update the activity status to 'No_show' if a reschedule email was sent.
      $updateResult = Activity::update(FALSE)
        ->addValue('status_id:name', 'No_show')
        ->addWhere('id', '=', $activityId)
        ->execute();

      // Return true if the update was successful.
      if ($updateResult) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   *
   */
  public static function sendRemainderEmails() {
    $startOfDay = (new \DateTime())->setTime(0, 0, 0);
    $endOfDay = (new \DateTime())->setTime(23, 59, 59);

    $scheduledInductionActivities = Activity::get(FALSE)
      ->addSelect('source_contact_id', 'Induction_Fields.Assign')
      ->addWhere('activity_type_id:name', '=', 'Induction')
      ->addWhere('status_id:name', '=', 'Scheduled')
      ->addWhere('activity_date_time', '>=', $startOfDay->format('Y-m-d H:i:s'))
      ->addWhere('activity_date_time', '<=', $endOfDay->format('Y-m-d H:i:s'))
      ->execute();

    foreach ($scheduledInductionActivities as $scheduledInductionActivity) {
      try {
        $inductionType = self::fetchTypeOfInduction($scheduledInductionActivity['source_contact_id']);

        $templateName = ($inductionType === 'Offline')
                ? 'Remainder_For_Volunteer_Induction_Scheduled_for_Today%'
                : 'Remainder_For_Volunteer_Induction_Scheduled_Online_for_Today%';

        $template = MessageTemplate::get(FALSE)
          ->addWhere('msg_title', 'LIKE', $templateName)
          ->setLimit(1)
          ->execute()
          ->single();

        $searchableSubject = str_replace('{contact.first_name}', '%', $template['msg_subject']);

        $contactsDetails = Contact::get(FALSE)
          ->addSelect('Individual_fields.Induction_Remainder_Email_Sent_on_Induction_Day')
          ->addWhere('id', '=', $scheduledInductionActivity['source_contact_id'])
          ->execute();

        $contacts = $contactsDetails->first();

        if (empty($contacts)) {
          continue;
        }

        $isMailSent = $contacts['Individual_fields.Induction_Remainder_Email_Sent_on_Induction_Day'] ?? NULL;

        if (in_array($isMailSent, [NULL, FALSE], TRUE)) {
          $emailParams = [
            'contact_id'  => $scheduledInductionActivity['source_contact_id'],
            'template_id' => $template['id'],
          ];

          $result = civicrm_api3('Email', 'send', $emailParams);

          $contact = Contact::update(FALSE)
            ->addValue('Individual_fields.Induction_Remainder_Email_Sent_on_Induction_Day', 1)
            ->addWhere('id', '=', $scheduledInductionActivity['source_contact_id'])
            ->execute();

        }
      }
      catch (\Exception $e) {
        // Log the error for debugging purposes.
        \Civi::log()->error('Failed to send reminder email', [
          'contact_id' => $scheduledInductionActivity['source_contact_id'],
          'error'      => $e->getMessage(),
        ]);
      }
    }
  }

  /**
   * Fetch the type of induction for a given volunteer.
   *
   * @param int $volunteerId
   *   The ID of the volunteer.
   *
   * @return string The type of induction ('Offline' or 'Online').
   */
  public static function fetchTypeOfInduction($volunteerId) {
    $inductionType = 'Offline';

    // Fetch contact data.
    if (empty($volunteerId)) {
      return;
    }
    $contactData = Contact::get(FALSE)
      ->addSelect('address_primary.state_province_id', 'address_primary.city', 'Individual_fields.Created_Date')
      ->addWhere('id', '=', $volunteerId)
      ->execute()
      ->single();

    $contactStateId = intval($contactData['address_primary.state_province_id']);
    $contactCityFormatted = ucwords(strtolower($contactData['address_primary.city']));

    // States with mixed induction types.
    $statesWithMixedInductionTypes = StateProvince::get(FALSE)
      ->addWhere('country_id.name', '=', 'India')
      ->addWhere('name', 'IN', ['Bihar', 'Jharkhand', 'Orissa'])
      ->execute()
      ->column('id');

    // Check if the contact's state and city match special conditions.
    if (in_array($contactStateId, $statesWithMixedInductionTypes)) {
      $contactCity = isset($contactData['address_primary.city']) ? strtolower($contactData['address_primary.city']) : '';
      if (in_array($contactCity, ['patna', 'ranchi', 'bhubaneshwar'])) {
        return $inductionType;
      }
      $inductionType = 'Online';
      return $inductionType;
    }

    $officeContact = Contact::get(FALSE)
      ->addWhere('contact_sub_type', 'CONTAINS', 'Goonj_Office')
      ->addClause('OR', ['Goonj_Office_Details.Other_Induction_Cities', 'CONTAINS', $contactCityFormatted], ['address_primary.city', 'CONTAINS', $contactCityFormatted])
      ->execute();

    // If no Goonj office exists, induction is online.
    if ($officeContact->count() === 0) {
      $inductionType = 'Online';
      return $inductionType;
    }
    $officeDetails = $officeContact->first();

    if (!empty($officeDetails)) {
      return $inductionType;
    }
    else {
      $inductionType = 'Online';
      return $inductionType;
    }
  }

}
