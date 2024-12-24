<?php

namespace Civi;

use Civi\Api4\Contact;
use Civi\Core\Service\AutoSubscriber;
use Civi\Api4\Event;
use Civi\Traits\QrCodeable;

/**
 *
 */
class GoonjInitiatedEventsService extends AutoSubscriber {
  use QrCodeable;
  /**
   *
   */
  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_pre' => 'generateGoonjEventsQr',
      '&hook_civicrm_tabset' => 'goonjActivitiesTabset',
    ];
  }

  /**
   *
   */
  public static function sendEventOutcomeEmail($event) {
    try {
      $eventId = $event['id'];
      $eventCode = $event['title'];
      $eventAddress = $event['Goonj_Events.Venue'];
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
          ->execute()->single();

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
          'html' => self::getLogisticsEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress),
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
      \Civi::log()->error("Error in sendLogisticsEmail for $campId " . $e->getMessage());
    }

  }

  /**
   *
   */
  private static function getLogisticsEmailHtml($attendeeName, $eventId, $eventAttendedById, $eventCode, $eventAddress) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    // Construct the full URLs for the forms.
    $campOutcomeFormUrl = $homeUrl . 'goonj-initiated-events-outcome/#?Event1=' . $eventId . '&Goonj_Events_Outcome.Filled_By=' . $campAttendedById;

    $html = "
    <p>Dear $attendeeName,</p>
    <p>Thank you for attending the goonj activity <strong>$eventId</strong> at <strong>$eventAddress</strong>. Their is one forms that require your attention during and after the goonj activity:</p>
    <ol>
        Please complete this form from the goonj activity location once the goonj activity ends.</li>
        <li><a href=\"$campOutcomeFormUrl\">Goonj Activity Outcome Form</a><br>
        This feedback form should be filled out after the goonj activity/session ends, once you have an overview of the event's outcomes.</li>
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
      $eventId = $objectRef['id'] ?? null;
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

      $eventQrCode = $event['Event_QR.QR_Code'] ?? null;
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
  } catch (\Exception $e) {
      \Civi::log()->error('Error generating QR Code for event.', [
          'errorMessage' => $e->getMessage(),
          'objectName' => $objectName,
          'objectId' => $objectId,
      ]);
  }
}


// function goonjActivitiesTabset($tabsetName, &$tabs, $context) {
//   // Check if the tabset is for Event Management
//   if ($tabsetName == 'civicrm/event/manage') {
//     // Ensure the event ID is available in the context
//     if (!empty($context['event_id'])) {
//       $eventID = $context['event_id'];
//       \Civi::log()->info('eventID', ['eventID'=>$eventID]);
      
//       // Construct the URL to the Search Kit view with the event ID filter in hash fragment
//       $url = \CRM_Utils_System::url(
//         'civicrm/events-material-contributions'
//       ) . "#?Material_Contribution.Event=$eventID";
      
//       // Add the "Material Contributions" tab
//       $newTab = [
//         'id' => 'material_contributions', // Unique identifier for the tab
//         'title' => ts('Material Contributions'),
//         'link' => $url, // URL to the Search Kit view
//         'valid' => true, // Ensures the tab is valid
//         'active' => true, // Activates the tab
//         'current' => false, // Indicates this tab is not the currently active tab
//       ];

//       // Insert the new tab into the tabs array (e.g., at position 4)
//       $tabs = array_merge(
//         array_slice($tabs, 0, 4), // Tabs before the new tab
//         [$newTab], // The new tab
//         array_slice($tabs, 4) // Tabs after the new tab
//       );
//     }
//   }
// }


  // /**
  //  *
  //  */
  // public static function goonjActivitiesTabset($tabsetName, &$tabs, $context) {
  //   \Civi::log()->info('tabsetName', ['tabsetName'=>$tabsetName, 'tabs'=>$tabs, 'context'=>$context ]);

  //   $tabConfigs = [
  //     'activities' => [
  //       'title' => ts('Material Contributions'),
  //       'module' => 'afsearchEventsMaterialContributions',
  //       'directive' => 'afsearch-events-material-contributions',
  //       'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
  //     ],
  //   ];

  //   foreach ($tabConfigs as $key => $config) {
  //     $tabs[$key] = [
  //       'id' => $key,
  //       'title' => $config['title'],
  //       'active' => 1,
  //       // 'template' => $config['template'],
  //       // 'module' => $config['module'],
  //       // 'directive' => $config['directive'],
  //     ];

  //     \Civi::service('angularjs.loader')->addModules($config['module']);
  //   }
  // }

  // public static function goonjActivitiesTabset($tabsetName, &$tabs, $context) {
  //   if ($tabsetName !== 'civicrm/event/manage') {
  //     return;
  //   }

  //   // Ensure the context has an event ID
  //   if (empty($context['event_id'])) {
  //     return;
  //   }

  //   $eventID = $context['event_id'];

  //   $tabs['materialContributions'] = [
  //     'id' => 'material_contributions', // Unique identifier for the tab
  //     'title' => ts('Material Contributions'),
  //     'active' => 1,
  //     'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl', // Template for rendering the tab
  //     'module' => 'afsearchEventsMaterialContributions', // AngularJS module
  //     'directive' => 'afsearch-events-material-contributions', // AngularJS directive
  //   ];

  //   \Civi::service('angularjs.loader')->addModules('afsearchEventsMaterialContributions');
  // }

  public static function goonjActivitiesTabset($tabsetName, &$tabs, $context) {
      if ($tabsetName !== 'civicrm/event/manage') {
        return;
      }
  
      // Log the context for debugging
      \Civi::log()->debug('Tabset Context', ['context' => $context]);
  
      if (empty($context['event_id'])) {
        \Civi::log()->debug('No Event ID Found in Context');
        return;
      }
  
      $eventID = $context['event_id'];
  
      // $tabs['materialContributions'] = [
      //   'id' => 'material_contributions',
      //   'title' => ts('Material Contributions'),
      //   'active' => 1,
      //   'template' => 'CRM/Goonjcustom/Tabs/Events.tpl',
      //   'module' => 'afsearchEventsMaterialContributions',
      //   'directive' => 'afsearch-events-material-contributions',
      //   'entity' => ['id' => $eventID],
      // ];
  
      // \Civi::service('angularjs.loader')->addModules('afsearchEventsMaterialContributions');
      $tabConfigs = [
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
        'monetaryContributionForUrbanOps' => [
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
        // if ($key == 'monetaryContributionForUrbanOps' && $isAdmin) {
        //   continue;
        // }
  
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
          'entity' => $config['entity']
        ];
  
        \Civi::service('angularjs.loader')->addModules($config['module']);
      }
    }
}
