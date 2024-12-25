<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Core\Service\AutoSubscriber;
use Civi\Api4\Event;
use Civi\Api4\Address;
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
	  '&hook_civicrm_pre' => 'generateGoonjEventsQr',
	  '&hook_civicrm_tabset' => 'goonjEventsTabset',
	];
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
		  'subject' => 'Goonj Events Notification: ' . $eventCode . ' at ' . $eventAddress,
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
	<p>Thank you for attending the goonj events <strong>$eventCode</strong> at <strong>$eventAddress</strong>. Their is one forms that require your attention during and after the goonj event:</p>
	<ol>
		Please complete this form from the goonj event location once the goonj event ends.</li>
		<li><a href=\"$eventOutcomeFormUrl\">Goonj Event Outcome Form</a><br>
		This feedback form should be filled out after the goonj event ends, once you have an overview of the event's outcomes.</li>
	</ol>
	<p>We appreciate your cooperation.</p>
	<p>Warm Regards,<br>Urban Relations Team</p>";

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
	  'outcome' => [
		'id' => 'outcome',
		'title' => ts('Outcome'),
		'active' => 1,
		'module' => 'afsearchEventsOutcomeDetails',
		'directive' => 'afsearch-events-outcome-details',
		'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
		'entity' => ['id' => $eventID],
		'permissions' => ['goonj_chapter_admin', 'urbanops'],
	  ],
	  'feedback' => [
		'id' => 'feedback',
		'title' => ts('Feedback'),
		'active' => 1,
		'module' => 'afsearchEventFeedback',
		'directive' => 'afsearch-event-feedback',
		'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
		'entity' => ['id' => $eventID],
		'permissions' => ['goonj_chapter_admin', 'urbanops'],
	  ],
	  'materialContributions' => [
		'id' => 'material_contributions',
		'title' => ts('Material Contributions'),
		'active' => 1,
		'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
		'module' => 'afsearchEventsMaterialContributions',
		'directive' => 'afsearch-events-material-contributions',
		'entity' => ['id' => $eventID],
		'permissions' => ['goonj_chapter_admin', 'urbanops'],
	  ],
	//   'monetaryContributionForUrbanOps' => [
	// 	'id' => 'monetary_contributions',
	// 	'title' => ts('Monetary Contribution'),
	// 	'active' => 1,
	// 	'module' => 'afsearchEventsMonetaryContribution',
	// 	'directive' => 'afsearch-events-monetary-contribution',
	// 	'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
	// 	'entity' => ['id' => $eventID],
	// 	'permissions' => ['goonj_chapter_admin', 'urbanops'],
	//   ],
	  'monetaryContribution' => [
		'id' => 'monetary_contributions',
		'title' => ts('Monetary Contribution'),
		'active' => 1,
		'module' => 'afsearchEventsMonetaryContribution',
		'directive' => 'afsearch-events-monetary-contribution',
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
	  ];

	  \Civi::service('angularjs.loader')->addModules($config['module']);
	}
  }

  /**
   *
   */
  public static function sendGoonjInitiatedFeedbackEmail($event) {

	try {
	  $eventId = $event['id'];
	  $eventCode = $event['title'];
	  $addresses = Address::get(FALSE)
		->addWhere('id', '=', $event['loc_block_id.address_id'])
		->setLimit(1)
		->execute()->first();
	  $eventAddress = \CRM_Utils_Address::format($addresses);
	  $eventAttendedById = $event['participant.created_id'];
	  $feedbackEmailSent = $event['Goonj_Events_Feedback.Last_Reminder_Sent'];

	  $endDate = new \DateTime($event['end_date']);

	  $today = new \DateTimeImmutable();
	  $endOfToday = $today->setTime(23, 59, 59);

      if (!$feedbackEmailSent && $endOfToday <= $endDate) {

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
		  'subject' => 'Goonj Events Notification: ' . $eventCode . ' at ' . $eventAddress,
		  'from' => $from,
		  'toEmail' => $attendeeEmail,
		  'replyTo' => $from,
		  'html' => self::getParticipantsFeedbackEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress),
		];

		$emailSendResult = \CRM_Utils_Mail::send($mailParams);

		if ($emailSendResult) {
		  Event::update(FALSE)
			->addValue('Goonj_Events_Feedback.Last_Reminder_Sent', TRUE)
			->addWhere('id', '=', $eventId)
			->execute();
		}
	  }
	}
	catch (\Exception $e) {
	  \Civi::log()->error("Error in sendEventsFeedbackEmail for $eventId " . $e->getMessage());
	}

  }

  /**
   *
   */
  private static function getParticipantsFeedbackEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress) {
	$homeUrl = \CRM_Utils_System::baseCMSURL();
	// Construct the full URLs for the forms.
	$eventFeedBackFormUrl = $homeUrl . 'events-feedback/#?Event1=' . $eventId . '&Goonj_Events_Outcome.Filled_By=' . $eventAttendedById;

	$html = "
	<p>Dear $attendeeName,</p>
	<p>Thank you for attending the goonj events <strong>$eventCode</strong> at <strong>$eventAddress</strong>. Their is one forms that require your attention during and after the goonj event:</p>
	<ol>
		Please complete this form from the goonj event location once the goonj event ends.</li>
		<li><a href=\"$eventFeedBackFormUrl\">Goonj Events Feedback Form</a><br>
		This feedback form should be filled out after the goonj event ends, once you have an overview of the event's event.</li>
	</ol>
	<p>We appreciate your cooperation.</p>
	<p>Warm Regards,<br>Urban Relations Team</p>";

	return $html;
  }

}
