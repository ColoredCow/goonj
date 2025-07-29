<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Api4\Address;
use Civi\Api4\Contact;
use Civi\Api4\Contribution;
use Civi\Api4\Event;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Core\Service\AutoSubscriber;
use Civi\Traits\QrCodeable;

/**
 *
 */
class GoonjInitiatedEventsService extends AutoSubscriber {
  use QrCodeable;

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_pre' => [
        ['generateGoonjEventsQr'],
        ['handleEventMaterialContributionDelete'],
        ['handleEventMonetaryContributionDelete'],
      ],
      '&hook_civicrm_tabset' => 'goonjEventsTabset',
      '&hook_civicrm_post' => [
        ['assignChapterGroupToIndividual'],
        ['updateEventMonetaryContributionTotalAmount'],
        ['updateEventMonetaryContributorsCount'],
        ['updateEventMatrialContributorsCount'],
      ],
      '&hook_civicrm_alterMailParams' => [
        ['alterReceiptMail'],
        ['handleOfflineReceipt'],
      ],
    ];
  }

  /**
   *
   */
  public static function handleEventMonetaryContributionDelete(string $op, string $objectName, $objectId, &$params) {
    if ($op !== 'delete' || $objectName !== 'Contribution') {
      return;
    }

    static $processed = [];

    if (isset($processed[$objectId])) {
      return;
    }
    $processed[$objectId] = TRUE;

    try {
      $contribution = Contribution::get(FALSE)
        ->addSelect(
        'id',
        'contact_id',
        'Contribution_Details.Events.id',
        'contribution_status_id:name',
        'total_amount',
        'payment_instrument_id:name'
        )
        ->addWhere('id', '=', $objectId)
        ->setLimit(1)
        ->execute()
        ->first();

      if (
        !$contribution ||
        $contribution['contribution_status_id:name'] !== 'Completed' ||
        empty($contribution['Contribution_Details.Events.id']) ||
        empty($contribution['contact_id'])
      ) {
        return;
      }

      $contactId = (int) $contribution['contact_id'];
      $eventId = $contribution['Contribution_Details.Events.id'];
      $amount = (float) $contribution['total_amount'];
      $paymentMethod = $contribution['payment_instrument_id:name'];

      $otherContributions = Contribution::get(FALSE)
        ->addSelect('contact_id')
        ->addWhere('Contribution_Details.Events.id', '=', $eventId)
        ->addWhere('contribution_status_id:name', '=', 'Completed')
        ->addWhere('id', '!=', $objectId)
        ->execute();

      $monetaryContactIds = [];
      $currentContactStillExists = FALSE;

      foreach ($otherContributions as $c) {
        if (!empty($c['contact_id'])) {
          $cid = (int) $c['contact_id'];
          $monetaryContactIds[] = $cid;

          if ($cid === $contactId) {
            $currentContactStillExists = TRUE;
          }
        }
      }

      $materialActivities = Activity::get(FALSE)
        ->addSelect('source_contact_id')
        ->addWhere('Material_Contribution.Event', '=', $eventId)
        ->execute();

      $materialContactIds = [];
      foreach ($materialActivities as $a) {
        if (!empty($a['source_contact_id'])) {
          $materialContactIds[] = (int) $a['source_contact_id'];
        }
      }

      $allContactIds = array_unique(array_merge($monetaryContactIds, $materialContactIds));
      $totalUniqueContributors = count($allContactIds);
      $uniqueMonetaryCount = count(array_unique($monetaryContactIds));

      $existing = Event::get(FALSE)
        ->addSelect(
        'Goonj_Events_Outcome.Online_Monetary_Contribution',
        'Goonj_Events_Outcome.Cash_Contribution'
        )
        ->addWhere('id', '=', $eventId)
        ->execute()
        ->first();

      $onlineCurrent = (float) ($existing['Goonj_Events_Outcome.Online_Monetary_Contribution'] ?? 0);
      $cashCurrent = (float) ($existing['Goonj_Events_Outcome.Cash_Contribution'] ?? 0);

      $update = Event::update()
        ->addWhere('id', '=', $eventId);

      if (!$currentContactStillExists) {
        $update->addValue('Goonj_Events_Outcome.Number_of_unique_monetary_contributors', $uniqueMonetaryCount);
      }

      if (!in_array($contactId, $materialContactIds, TRUE) && !$currentContactStillExists) {
        $update->addValue('Goonj_Events_Outcome.Number_of_Contributors', $totalUniqueContributors);
      }

      if ($paymentMethod === 'Credit Card') {
        $update->addValue('Goonj_Events_Outcome.Online_Monetary_Contribution', max(0, $onlineCurrent - $amount));
      }

      if (in_array($paymentMethod, ['Cash', 'Check'], TRUE)) {
        $update->addValue('Goonj_Events_Outcome.Cash_Contribution', max(0, $cashCurrent - $amount));
      }

      $update->execute();

    }
    catch (\Exception $e) {
    }
  }

  /**
   *
   */
  public static function handleEventMaterialContributionDelete(string $op, string $objectName, $objectId, &$params) {
    if ($op !== 'delete' || $objectName !== 'Activity') {
      return;
    }

    try {
      $activity = Activity::get(FALSE)
        ->addSelect('id', 'source_contact_id', 'custom.*')
        ->addWhere('id', '=', $objectId)
        ->setLimit(1)
        ->execute()
        ->first();

      if (
      !$activity ||
      empty($activity['Material_Contribution.Event']) ||
      empty($activity['source_contact_id'])
      ) {
        return;
      }

      $eventId = $activity['Material_Contribution.Event'];
      $contactId = (int) $activity['source_contact_id'];

      $materialActivities = Activity::get(FALSE)
        ->addSelect('source_contact_id')
        ->addWhere('Material_Contribution.Event', '=', $eventId)
        ->addWhere('id', '!=', $objectId)
        ->execute();

      $materialContactIds = [];
      $currentContactStillExists = FALSE;

      foreach ($materialActivities as $a) {
        if (!empty($a['source_contact_id'])) {
          $cid = (int) $a['source_contact_id'];
          $materialContactIds[] = $cid;

          if ($cid === $contactId) {
            $currentContactStillExists = TRUE;
          }
        }
      }

      $monetaryContributions = Contribution::get(FALSE)
        ->addSelect('contact_id')
        ->addWhere('Contribution_Details.Events.id', '=', $eventId)
        ->addWhere('contribution_status_id:name', '=', 'Completed')
        ->execute();

      $monetaryContactIds = [];
      foreach ($monetaryContributions as $c) {
        if (!empty($c['contact_id'])) {
          $monetaryContactIds[] = (int) $c['contact_id'];
        }
      }

      $allContactIds = array_unique(array_merge($materialContactIds, $monetaryContactIds));
      $totalUniqueContributors = count($allContactIds);
      $uniqueMaterialCount = count(array_unique($materialContactIds));

      $update = Event::update()
        ->addWhere('id', '=', $eventId)
        ->addValue('Goonj_Events_Outcome.Number_of_Material_Contributors', $uniqueMaterialCount)
        ->addValue('Goonj_Events_Outcome.Number_of_Contributors', $totalUniqueContributors);

      $update->execute();

    }
    catch (\Exception $e) {
    }
  }

  /**
   *
   */
  public static function updateUniqueContributorsCount(int $eventId) {
    $materialActivities = Activity::get(FALSE)
      ->addSelect('source_contact_id')
      ->addWhere('Material_Contribution.Event', '=', $eventId)
      ->execute();

    $materialContactIds = [];
    foreach ($materialActivities as $activity) {
      if (!empty($activity['source_contact_id'])) {
        $materialContactIds[] = $activity['source_contact_id'];
      }
    }

    $monetaryContributions = Contribution::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('Contribution_Details.Events.id', '=', $eventId)
      ->addWhere('contribution_status_id:name', '=', 'Completed')
      ->execute();

    $monetaryContactIds = [];
    foreach ($monetaryContributions as $contribution) {
      if (!empty($contribution['contact_id'])) {
        $monetaryContactIds[] = $contribution['contact_id'];
      }
    }

    $allContactIds = array_unique(array_merge($materialContactIds, $monetaryContactIds));
    $uniqueCount = count($allContactIds);

    try {
      Event::update()
        ->addValue('Goonj_Events_Outcome.Number_of_Contributors', $uniqueCount)
        ->addWhere('id', '=', $eventId)
        ->execute();

    }
    catch (\Exception $e) {
    }
  }

  /**
   *
   */
  public static function updateEventMatrialContributorsCount(string $op, string $objectName, $objectId, &$objectRef) {
    if ($op !== 'create' || $objectName !== 'AfformSubmission') {
      return;
    }

    $dataRaw = $objectRef->data ?? NULL;

    if (!$dataRaw) {
      return;
    }

    $dataDecoded = json_decode($dataRaw, TRUE);

    if (!is_array($dataDecoded)) {
      return;
    }

    $activityFields = $dataDecoded['Activity1'][0]['fields'] ?? [];
    $eventId = $activityFields['Material_Contribution.Event'] ?? NULL;

    if (!$eventId || !$activityFields) {
      return;
    }

    $activities = Activity::get(FALSE)
      ->addSelect('source_contact_id')
      ->addWhere('Material_Contribution.Event', '=', $eventId)
      ->execute();

    $uniqueContactIds = [];

    foreach ($activities as $activity) {
      if (!empty($activity['source_contact_id'])) {
        $uniqueContactIds[$activity['source_contact_id']] = TRUE;
      }
    }

    $uniqueCount = count($uniqueContactIds);

    self::updateUniqueContributorsCount($eventId);

    try {
      Event::update()
        ->addValue('Goonj_Events_Outcome.Number_of_unique_material_contributors', $uniqueCount)
        ->addWhere('id', '=', $eventId)
        ->execute();
    }
    catch (\Exception $e) {
    }
  }

  /**
   *
   */
  public static function updateEventMonetaryContributorsCount(string $op, string $objectName, $objectId, &$objectRef) {
    static $processed = [];

    if ($objectName !== 'Contribution' || empty($objectRef->id)) {
      return;
    }

    $contributionId = $objectRef->id;
    if (isset($processed[$contributionId])) {
      return;
    }
    $processed[$contributionId] = TRUE;

    $contribution = Contribution::get(FALSE)
      ->addSelect('contact_id', 'Contribution_Details.Events.id')
      ->addWhere('id', '=', $contributionId)
      ->addWhere('contribution_status_id:name', '=', 'Completed')
      ->execute()
      ->first();

    if (!$contribution || empty($contribution['Contribution_Details.Events.id'])) {
      return;
    }

    $eventId = $contribution['Contribution_Details.Events.id'];

    if (!$eventId) {
      return;
    }

    self::updateUniqueContributorsCount($eventId);

    $allContributions = Contribution::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('Contribution_Details.Events.id', '=', $eventId)
      ->addWhere('contribution_status_id:name', '=', 'Completed')
      ->execute();

    if (!$allContributions) {
      return;
    }

    $contactIds = array_column(iterator_to_array($allContributions), 'contact_id');
    $uniqueContactIds = array_unique($contactIds);
    $newCount = count($uniqueContactIds);

    Event::update(FALSE)
      ->addValue('Goonj_Events_Outcome.Number_of_unique_monetary_contributors', $newCount)
      ->addWhere('id', '=', $eventId)
      ->execute();

  }

  /**
   *
   */
  public static function updateEventMonetaryContributionTotalAmount(string $op, string $objectName, $objectId, &$objectRef) {
    static $processed = [];

    if ($objectName !== 'Contribution' || empty($objectRef->id)) {
      return;
    }

    $contributionId = $objectRef->id;
    if (isset($processed[$contributionId])) {
      return;
    }
    $processed[$contributionId] = TRUE;

    $contribution = Contribution::get(FALSE)
      ->addSelect(
        'contribution_status_id:name',
        'total_amount',
        'Contribution_Details.Events.id',
        'payment_instrument_id:name'
      )
      ->addWhere('id', '=', $contributionId)
      ->addWhere('contribution_status_id:name', '=', 'Completed')
      ->execute()
      ->first();

    if (!$contribution || empty($contribution['Contribution_Details.Events.id'])) {
      return;
    }

    $eventId = $contribution['Contribution_Details.Events.id'];
    $totalAmount = (float) $contribution['total_amount'];
    $paymentMethod = $contribution['payment_instrument_id:name'];

    $existing = Event::get(FALSE)
      ->addSelect(
        'Goonj_Events_Outcome.Online_Monetary_Contribution',
        'Goonj_Events_Outcome.Cash_Contribution'
      )
      ->addWhere('id', '=', $eventId)
      ->execute()
      ->first();

    $onlineCurrent = (float) ($existing['Goonj_Events_Outcome.Online_Monetary_Contribution'] ?? 0);
    $cashCurrent = (float) ($existing['Goonj_Events_Outcome.Cash_Contribution'] ?? 0);
    $update = Event::update(FALSE)
      ->addWhere('id', '=', $eventId);

    if ($paymentMethod === 'Credit Card') {
      $onlineNew = $onlineCurrent + $totalAmount;
      $update->addValue('Goonj_Events_Outcome.Online_Monetary_Contribution', $onlineNew);
    }

    if (in_array($paymentMethod, ['Cash', 'Check'], TRUE)) {
      $cashNew = $cashCurrent + $totalAmount;
      $update->addValue('Goonj_Events_Outcome.Cash_Contribution', $cashNew);
    }

    $update->execute();
  }

  /**
   *
   */
  public static function sendEventOutcomeEmail($event) {
    try {
      $eventId = $event['id'];
      $eventCode = $event['title'];
      $addresses = Address::get(FALSE)
        ->addWhere('id', '=', $event['loc_block_id.address_id'])
        ->setLimit(1)
        ->execute()->first();
      $eventAddress = \CRM_Utils_Address::format($addresses);

      $eventAttendedById = $event['Goonj_Events.Goonj_Coordinating_POC_Main_'];
      $outcomeEmailSent = $event['Goonj_Events_Outcome.Outcome_Email_Sent'];

      $startDate = new \DateTime($event['start_date']);

      $today = new \DateTimeImmutable();
      $endOfToday = $today->setTime(23, 59, 59);

      if (!$outcomeEmailSent && $startDate <= $endOfToday) {
        $eventAttendedBy = Contact::get(FALSE)
          ->addSelect('email.email', 'display_name')
          ->addJoin('Email AS email', 'LEFT')
          ->addWhere('id', '=', $eventAttendedById)
          ->execute()->first();

        $attendeeEmail = $eventAttendedBy['email.email'];
        $attendeeName = $eventAttendedBy['display_name'];
        $from = HelperService::getDefaultFromEmail();

        if (!$attendeeEmail) {
          throw new \Exception('Attendee email missing');
        }

        $mailParams = [
          'subject' => 'Goonj Events Notification: ' . $eventCode,
          'from' => $from,
          'toEmail' => $attendeeEmail,
          'replyTo' => $from,
          'html' => self::getOutcomeEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress),
        ];

        $emailSendResult = \CRM_Utils_Mail::send($mailParams);

        if ($emailSendResult) {
          Event::update(FALSE)
            ->addValue('Goonj_Events_Outcome.Outcome_Email_Sent', TRUE)
            ->addWhere('id', '=', $eventId)
            ->execute();
        }
      }
    }
    catch (\Exception $e) {
      \Civi::log()->error("Error in sendEventsOutcomeEmail for $eventId " . $e->getMessage());
    }

  }

  /**
   *
   */
  private static function getOutcomeEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    // Construct the full URLs for the forms.
    $eventOutcomeFormUrl = $homeUrl . 'goonj-initiated-events-outcome/#?Event1=' . $eventId . '&Goonj_Events_Outcome.Filled_By=' . $eventAttendedById;

    $html = "
	<p>Dear $attendeeName,</p>
	<p>Thank you for coordinating the <strong>$eventCode</strong> Please fill the below form after the event is over:</p>
  <p><a href=\"$eventOutcomeFormUrl\">Event Outcome Form</a><br></p>
	<p>We appreciate your cooperation.</p>
	<p>Warm Regards,<br>Team goonj</p>";

    return $html;
  }

  /**
   * This hook is called after a database write operation on entities.
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
  public static function generateGoonjEventsQr(string $op, string $objectName, $objectId, &$objectRef) {
    if ($objectName !== 'Event') {
      return;
    }

    try {
      $eventId = $objectRef['id'] ?? NULL;
      if (!$eventId) {
        \Civi::log()->warning('Event ID is missing from object reference.' . $objectId);
        return;
      }

      // Fetch event details with the QR Code field.
      $events = Event::get(FALSE)
        ->addSelect('Event_QR.QR_Code')
        ->addWhere('id', '=', $eventId)
        ->execute();

      $event = $events->first();
      if (!$event) {
        \Civi::log()->warning('Event not found..' . $eventId);
        return;
      }

      $eventQrCode = $event['Event_QR.QR_Code'] ?? NULL;
      if (!empty($eventQrCode)) {
        \Civi::log()->info('QR Code already exists for the event.', ['eventId' => $eventId]);
        return;
      }

      // Generate base URL for QR Code.
      $baseUrl = \CRM_Core_Config::singleton()->userFrameworkBaseURL;
      $qrCodeData = "{$baseUrl}actions/events/{$eventId}";
      // Define save options for custom group and field.
      $saveOptions = [
        'customGroupName' => 'Event_QR',
        'customFieldName' => 'QR_Code',
      ];

      // Generate and save the QR Code.
      self::generateQrCode($qrCodeData, $eventId, $saveOptions);
      \Civi::log()->info('QR Code generated and saved successfully.', ['eventId' => $eventId]);
    }
    catch (\Exception $e) {
      \Civi::log()->error('Error generating QR Code for event.', [
        'errorMessage' => $e->getMessage(),
        'objectName' => $objectName,
        'objectId' => $objectId,
      ]);
    }
  }

  /**
   *
   */
  public static function goonjEventsTabset($tabsetName, &$tabs, $context) {

    if ($tabsetName !== 'civicrm/event/manage') {
      return;
    }

    if (!isset($context['event_id'])) {
      return;
    }

    $eventId = $context['event_id'];

    $event = Event::get(FALSE)
      ->addSelect('event_type_id:name')
      ->addWhere('id', '=', $eventId)
      ->setLimit(1)
      ->execute()
      ->first();

    if (!$event) {
      \Civi::log()->error('Event not found', ['EventId' => $eventId]);
      return;
    }

    $eventType = $event['event_type_id:name'];

    if ($eventType === 'Rural Planned Visit') {
      return;
    }

    $restrictedRoles = ['account_team', 'ho_account'];

    $isAdmin = \CRM_Core_Permission::check('admin');

    $hasRestrictedRole = !$isAdmin && \CRM_Core_Permission::checkAnyPerm($restrictedRoles);

    if ($hasRestrictedRole) {
      unset($tabs['view']);
      unset($tabs['edit']);
    }

    $eventID = $context['event_id'];

    $tabConfigs = [
      'outcome' => [
        'id' => 'outcome',
        'title' => ts('Outcome'),
        'active' => 1,
        'module' => 'afsearchEventsOutcomeDetails',
        'directive' => 'afsearch-events-outcome-details',
        'template' => 'CRM/Goonjcustom/Tabs/Events/Outcome.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin', 'sanjha_team', 'project_team_ho', 'project_team_chapter', 'njpc_ho_team', 'project_ho_and_accounts'],
      ],
      'feedback' => [
        'id' => 'feedback',
        'title' => ts('Feedback'),
        'active' => 1,
        'module' => 'afsearchGoonjInitiatedEventsFeedbackView',
        'directive' => 'afsearch-goonj-initiated-events-feedback-view',
        'template' => 'CRM/Goonjcustom/Tabs/Events/Feedback.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin', 'sanjha_team', 'project_team_ho', 'project_team_chapter', 'njpc_ho_team', 'project_ho_and_accounts'],
      ],
      'materialContributions' => [
        'id' => 'material_contributions',
        'title' => ts('Material Contributions'),
        'active' => 1,
        'template' => 'CRM/Goonjcustom/Tabs/Events/MaterialContribution.tpl',
        'module' => 'afsearchEventsMaterialContributions',
        'directive' => 'afsearch-events-material-contributions',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops', 'urban_ops_admin', 'sanjha_team', 'project_team_ho', 'project_team_chapter', 'njpc_ho_team', 'project_ho_and_accounts'],
      ],
      'monetaryContribution' => [
        'id' => 'monetary_contributions',
        'title' => ts('Monetary Contribution'),
        'active' => 1,
        'module' => 'afsearchEventsMonetaryContribution',
        'directive' => 'afsearch-events-monetary-contribution',
        'template' => 'CRM/Goonjcustom/Tabs/Events/MonetaryContribution.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'account_team', 'ho_account', 'project_ho_and_accounts'],
      ],
    ];

    foreach ($tabConfigs as $key => $config) {
      $isAdmin = \CRM_Core_Permission::check('admin');

      if (!\CRM_Core_Permission::checkAnyPerm($config['permissions'])) {
        // Does not permission; just continue.
        continue;
      }

      $tabs[$key] = [
        'id' => $key,
        'title' => $config['title'],
        'active' => 1,
        'template' => $config['template'],
        'module' => $config['module'],
        'directive' => $config['directive'],
        'entity' => $config['entity'],
        'valid' => 1,
      ];

      \Civi::service('angularjs.loader')->addModules($config['module']);
    }
  }

  /**
   *
   */
  public static function sendGoonjInitiatedFeedbackEmail($eventsArray) {
    $updatedEventIds = [];

    foreach ($eventsArray as $event) {
      try {
        $eventId = $event['id'];
        $eventCode = $event['title'];
        $addresses = Address::get(FALSE)
          ->addWhere('id', '=', $event['loc_block_id.address_id'])
          ->setLimit(1)
          ->execute()->first();
        $eventAddress = \CRM_Utils_Address::format($addresses);
        $feedbackEmailSent = $event['Goonj_Events_Feedback.Last_Reminder_Sent'];
        $eventAttendedById = $event['participant.created_id'];

        $endDate = new \DateTime($event['end_date']);
        $today = new \DateTimeImmutable();
        $endOfToday = $today->setTime(23, 59, 59);
        $endOfTodayFormatted = $today->format('Y-m-d');
        $endDateFormatted = $endDate->format('Y-m-d');

        if (!$feedbackEmailSent && $endDateFormatted <= $endOfTodayFormatted) {
          $eventAttendedBy = Contact::get(FALSE)
            ->addSelect('email.email', 'display_name')
            ->addJoin('Email AS email', 'LEFT')
            ->addWhere('id', '=', $eventAttendedById)
            ->execute()->first();

          $attendeeEmail = $eventAttendedBy['email.email'];
          $attendeeName = $eventAttendedBy['display_name'];
          $from = HelperService::getDefaultFromEmail();

          if (!$attendeeEmail) {
            throw new \Exception('Attendee email missing');
          }

          $mailParams = [
            'subject' => 'Goonj Events Notification: ' . $eventCode,
            'from' => $from,
            'toEmail' => $attendeeEmail,
            'replyTo' => $from,
            'html' => self::getParticipantsFeedbackEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress),
          ];

          $emailSendResult = \CRM_Utils_Mail::send($mailParams);

          if ($emailSendResult) {
            // If email is successfully sent, store the eventId for later update.
            $updatedEventIds[] = $eventId;
          }
        }
      }
      catch (\Exception $e) {
        \Civi::log()->error("Error in sendEventsFeedbackEmail for $eventId " . $e->getMessage());
      }
    }

    if (!empty($updatedEventIds)) {
      Event::update(FALSE)
        ->addValue('Goonj_Events_Feedback.Last_Reminder_Sent', TRUE)
        ->addWhere('id', 'IN', $updatedEventIds)
        ->execute();
    }
  }

  /**
   *
   */
  private static function getParticipantsFeedbackEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();

    $eventFeedBackFormUrl = $homeUrl . 'goonj-events-feedbacks/#?Events_Feedback.Event=' . $eventId . '&source_contact_id=' . $eventAttendedById;

    $html = "
    <p>Dear $attendeeName,</p>
    <p>Thank you for attending <strong>$eventCode</strong>. Your presence and contributions made this event a huge success.</p>
    <p>We value your feedback to continue improving the experience of such gatherings and tailoring our future events. Kindly take a few moments to share your experience with us by filling out this <a href=\"$eventFeedBackFormUrl\">form link</a>.</p>
    <p>Warm Regards,<br>Team Goonj</p>";

    return $html;
  }

  /**
   *
   */
  public static function assignChapterGroupToIndividual(string $op, string $objectName, $objectId, &$objectRef) {

    if ($op !== 'create' || $objectName !== 'Participant') {
      return FALSE;
    }

    $contactId = $objectRef->contact_id;

    if (!$contactId) {
      \Civi::log()->info("Missing Contact ID ");
      return FALSE;
    }

    $contactQuery = Contact::get(FALSE)
      ->addSelect('address_primary.state_province_id')
      ->addWhere('id', '=', $contactId)
      ->execute();
    $contact = $contactQuery->first();
    $stateProvinceId = $contact['address_primary.state_province_id'];

    if (empty($contact)) {
      return FALSE;
    }

    $groupContacts = GroupContact::get(TRUE)
      ->addSelect('*', 'group_id:name')
      ->addWhere('contact_id', '=', $contact['id'])
      ->execute()->first();

    if ($groupContacts) {
      return FALSE;
    }

    $groupId = self::getChapterGroupForState($stateProvinceId);

    if ($groupId) {
      self::addContactToGroup($contactId, $groupId);
    }
  }

  /**
   *
   */
  private static function getChapterGroupForState($stateId) {
    $stateContactGroup = Group::get(FALSE)
      ->addSelect('id')
      ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
      ->addWhere('Chapter_Contact_Group.Contact_Catchment', 'CONTAINS', $stateId)
      ->execute()->first();

    if (!$stateContactGroup) {
      $stateContactGroup = Group::get(FALSE)
        ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
        ->addWhere('Chapter_Contact_Group.Fallback_Chapter', '=', 1)
        ->execute()->first();

    }

    return $stateContactGroup ? $stateContactGroup['id'] : NULL;
  }

  /**
   *
   */
  private static function addContactToGroup($contactId, $groupId) {
    if ($contactId & $groupId) {
      $groupContact = GroupContact::get(FALSE)
        ->addWhere('contact_id', '=', $contactId)
        ->addWhere('group_id', '=', $groupId)
        ->execute()->first();
      if ($groupContact) {
        return FALSE;
      }
      try {
        GroupContact::create(FALSE)
          ->addValue('contact_id', $contactId)
          ->addValue('group_id', $groupId)
          ->addValue('status', 'Added')
          ->execute();
      }
      catch (Exception $e) {
        \Civi::log()->error("Error adding contact_id: $contactId to group_id: $groupId. Exception: " . $e->getMessage());
      }
    }
  }

  /**
   *
   */
  public static function alterReceiptMail(&$params, $context) {
    // Handle event_online_receipt workflow.
    if (!empty($params['workflow']) && $params['workflow'] === 'event_online_receipt') {
      // Extract donor name or use a default value.
      $donorName = !empty($params['toName']) ? $params['toName'] : 'Valued Supporter';
      $contributionID = NULL;

      try {
        if (!empty($params['tplParams']['lineItem'][0])) {
          foreach ($params['tplParams']['lineItem'][0] as $lineItem) {
            if (!empty($lineItem['contribution_id'])) {
              $contributionID = $lineItem['contribution_id'];
              break;
            }
          }
        }
        if (empty($contributionID)) {
          \Civi::log()->warning('Unable to find contribution ID in event receipt parameters');
        }
      }
      catch (\Exception $e) {
        \Civi::log()->error('Error extracting contribution ID: ' . $e->getMessage());
      }

      $params['cc'] = 'priyanka@goonj.org, accounts@goonj.org';

      try {
        $contribution = Contribution::get(FALSE)
          ->addSelect('invoice_number')
          ->addWhere('id', '=', $contributionID)
          ->execute()->single();

        $receiptNumber = $contribution['invoice_number'];

      }
      catch (\Exception $e) {
        \Civi::log()->error('Failed to retrieve contribution: ' . $e->getMessage(), [
          'contributionID' => $contributionID,
        ]);
        $params['toEmail'] = NULL;
        $params['text'] = '';
        $params['html'] = '';
        $params['subject'] = '';
        return;
      }

      $params['text'] = "Dear $donorName,\n\nThank you for your contribution.\n\nThese contributions go a long way in sustaining our operations and implementing series of initiatives all across.\nThe receipt No. ($receiptNumber) for the same is enclosed with the details of 80G exemptions and our PAN No.\n\nFor updates on our activities and new campaigns, please visit our website www.goonj.org and our FB page https://www.facebook.com/goonj.org, which are regularly updated.\n\nThank you once again for joining the journey.\n\nWith best regards,\nTeam Goonj";

      $params['html'] = "
              <p>Dear <strong>$donorName</strong>,</p>
              <p>Thank you for your contribution.</p>
              <p>These contributions go a long way in sustaining our operations and implementing series of initiatives all across.</p>
              <p>The receipt No. (<strong>$receiptNumber</strong>) for the same is enclosed with the details of 80G exemptions and our PAN No.</p>
              <p>For updates on our activities and new campaigns, please visit our website <a href='https://www.goonj.org'>www.goonj.org</a> and our FB page <a href='https://www.facebook.com/goonj.org'>https://www.facebook.com/goonj.org</a>, which are regularly updated.</p>
              <p>Thank you once again for joining the journey.</p>
              <p>With best regards,<br>Team Goonj</p>
          ";
    }
  }

  /**
   *
   */
  public static function handleOfflineReceipt(&$params, $context) {
    if (!empty($params['workflow']) && $params['workflow'] === 'event_offline_receipt') {
      // Extract donor name or use a default value.
      $donorName = !empty($params['toName']) ? $params['toName'] : 'Valued Supporter';
      $contributionID = $params['tplParams']['contributionID'] ?? NULL;
      $params['cc'] = 'priyanka@goonj.org, accounts@goonj.org';

      try {
        $contribution = Contribution::get(FALSE)
          ->addSelect('invoice_number', 'contribution_page_id:label')
          ->addWhere('id', '=', $contributionID)
          ->execute()->single();

        $receiptNumber = $contribution['invoice_number'];

      }
      catch (\Exception $e) {
        \Civi::log()->error('Failed to retrieve contribution: ' . $e->getMessage(), [
          'contributionID' => $contributionID,
        ]);
        return;
      }

      $params['text'] = "Dear $donorName,\n\nThank you for your contribution.\n\nThese contributions go a long way in sustaining our operations and implementing series of initiatives all across.\nThe receipt No. ($receiptNumber) for the same is enclosed with the details of 80G exemptions and our PAN No.\n\nFor updates on our activities and new campaigns, please visit our website www.goonj.org and our FB page https://www.facebook.com/goonj.org, which are regularly updated.\n\nThank you once again for joining the journey.\n\nWith best regards,\nTeam Goonj";

      $params['html'] = "
              <p>Dear <strong>$donorName</strong>,</p>
              <p>Thank you for your contribution.</p>
              <p>These contributions go a long way in sustaining our operations and implementing series of initiatives all across.</p>
              <p>The receipt No. (<strong>$receiptNumber</strong>) for the same is enclosed with the details of 80G exemptions and our PAN No.</p>
              <p>For updates on our activities and new campaigns, please visit our website <a href='https://www.goonj.org'>www.goonj.org</a> and our FB page <a href='https://www.facebook.com/goonj.org'>https://www.facebook.com/goonj.org</a>, which are regularly updated.</p>
              <p>Thank you once again for joining the journey.</p>
              <p>With best regards,<br>Team Goonj</p>
          ";
    }
  }

}
