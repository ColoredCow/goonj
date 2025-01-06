<?php

namespace Civi;

use Civi\Core\Service\AutoSubscriber;
use Civi\Api4\Event;
use Civi\Api4\Address;
use Civi\Api4\Contact;

/**
 *
 */
class RuralPlannedVisitService extends AutoSubscriber {

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_tabset' => 'ruralPlannedVisitTabset',
      '&hook_civicrm_buildForm' => 'goonjParticipantRegistration'
    ];
  }

  /**
   *
   */
  public static function ruralPlannedVisitTabset($tabsetName, &$tabs, $context) {

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
    }

    $eventType = $event['event_type_id:name'];

    if ($eventType !== 'Rural Planned Visit') {
      return;
    }

    $restrictedRoles = ['account_team', 'ho_account'];

    $isAdmin = \CRM_Core_Permission::check('admin');

    $hasRestrictedRole = !$isAdmin && \CRM_Core_Permission::checkAnyPerm($restrictedRoles);

    if ($hasRestrictedRole) {
      unset($tabs['view']);
      unset($tabs['edit']);
    }

    if (empty($context['event_id'])) {
      \Civi::log()->debug('No Event ID Found in Context');
      return;
    }

    $eventID = $context['event_id'];

    $tabConfigs = [
      'logistics' => [
        'id' => 'logistics',
        'title' => ts('Logistics'),
        'active' => 1,
        'module' => 'afsearchRuralLogisticsDetails',
        'directive' => 'afsearch-rural-logistics-details',
        'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'report' => [
        'id' => 'outcome report',
        'title' => ts('Outcome Report'),
        'active' => 1,
        'module' => 'afsearchRuralPlannedVisitsOutcomeReportsDetails',
        'directive' => 'afsearch-rural-planned-visits-outcome-reports-details',
        'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'outcome' => [
        'id' => 'outcome',
        'title' => ts('Outcome'),
        'active' => 1,
        'module' => 'afsearchRuralPlannedVisitOutcomeDetails',
        'directive' => 'afsearch-rural-planned-visit-outcome-details',
        'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'feedback' => [
        'id' => 'feedback',
        'title' => ts('Feedback'),
        'active' => 1,
        'module' => 'afsearchRuralPlannedVisitFeedbacksDetails',
        'directive' => 'afsearch-rural-planned-visit-feedbacks-details',
        'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
        'entity' => ['id' => $eventID],
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
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
  public static function sendRuralPlannedVisitOutcomeEmail($event) {
    try {
      $eventId = $event['id'];
      $eventCode = $event['title'];
      $addresses = Address::get(FALSE)
        ->addWhere('id', '=', $event['loc_block_id.address_id'])
        ->setLimit(1)
        ->execute()->first();
      $eventAddress = \CRM_Utils_Address::format($addresses);

      $eventAttendedById = $event['Rural_Planned_Visit.Goonj_Coordinator'];
      $outcomeEmailSent = $event['Rural_Planned_Visit_Outcome.Outcome_Email_Sent'];

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
          'subject' => 'Rural Planned Visit Events Notification: ' . $eventCode . ' at ' . $eventAddress,
          'from' => $from,
          'toEmail' => $attendeeEmail,
          'replyTo' => $from,
          'html' => self::getOutcomeEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress),
        ];

        $emailSendResult = \CRM_Utils_Mail::send($mailParams);

        if ($emailSendResult) {
          Event::update(FALSE)
            ->addValue('Rural_Planned_Visit_Outcome.Outcome_Email_Sent', TRUE)
            ->addValue('Rural_Planned_Visit_Outcome.Filled_By', $eventAttendedById)
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
    $eventOutcomeFormUrl = $homeUrl . 'rural-planned-visit-outcome/#?Event1=' . $eventId;

    $html = "
	<p>Dear $attendeeName,</p>
	<p>Thank you for attending the Rural Planned Visit <strong>$eventCode</strong> at <strong>$eventAddress</strong>. Their is one forms that require your attention during and after the Rural Planned Visit:</p>
	<ol>
		Please complete this form from the Rural Planned Visit location once the event ends.</li>
		<li><a href=\"$eventOutcomeFormUrl\">Rural Planned Visit Outcome Form</a><br>
		This feedback form should be filled out after the event ends, once you have an overview of the event's outcomes.</li>
	</ol>
	<p>We appreciate your cooperation.</p>
	<p>Warm Regards,<br>Urban Relations Team</p>";

    return $html;
  }

  public function goonjParticipantRegistration($formName, &$form) {
    \Civi::log()->info('formName', ['formName'=>$formName, 'form'=>$form]);
    if ($formName === 'CRM_Event_Form_Registration_Register') {
      $eventId = \CRM_Utils_Request::retrieve('id', 'Integer');
      if ($eventId === 113) { // Replace with your event ID
          // Pre-fill the custom field value
          $form->setDefaults([
              'custom_859' => '200', // Replace with your desired value
          ]);
      }
  }
}

}
