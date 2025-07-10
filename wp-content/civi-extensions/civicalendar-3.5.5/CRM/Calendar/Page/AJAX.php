<?php

class CRM_Calendar_Page_AJAX {

  /**
   * Ajax called, gets all contact events, as json
   *
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public static function getContactEvents() {
    $contactId = CRM_Utils_Request::retrieve('cid', 'Positive');

    if (empty($contactId)) {
      $session = CRM_Core_Session::singleton();
      $userID = $session->get('userID');
      $contactId = [$userID];
    }

    $params = [
      'hide_past_events' => CRM_Calendar_Settings::getValue('hide_past_events'),
      'startDate' => gmdate('Y-m-d H:i:s', CRM_Utils_Request::retrieve('start', 'String')),
      'endDate' => gmdate('Y-m-d H:i:s', CRM_Utils_Request::retrieve('end', 'String')),
    ];

    $eventsHandler = new CRM_Calendar_Common_Handler($contactId, $params);
    $events = $eventsHandler->getAllEventsWeb();

    CRM_Utils_JSON::output($events);
  }

  /**
   * Ajax called, gets all contacts events, as json, for Contact calendar
   * sharing
   *
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  public static function getContactEventOverlaying() {
    $contactIds = CRM_Utils_Request::retrieve('cid', 'Memo');
    $groupIds = CRM_Utils_Request::retrieve('gid', 'Memo');

    $groupContactIds = [];

    if (!empty($groupIds)) {
      $groupContacts = civicrm_api4('GroupContact', 'get', [
        'select' => [
          'contact_id',
        ],
        'where' => [
          ['group_id.id', 'IN', $groupIds],
          ['group_id.is_active', '=', TRUE],
        ],
        'checkPermissions' => FALSE,
      ]);

      foreach ($groupContacts as $groupContact) {
        $groupContactIds[] = $groupContact['contact_id'];
      }
    }

    if (empty($contactIds)) {
      $finalContactIds = array_unique($groupContactIds, SORT_NUMERIC);
    } else {
      $finalContactIds = array_unique(array_merge($contactIds, $groupContactIds), SORT_NUMERIC);
    }

    if (empty($finalContactIds)) {
      $finalContactIds = 0;
    }

    $params = [
      'hide_past_events' => CRM_Calendar_Settings::getValue('hide_past_events'),
      'startDate' => gmdate('Y-m-d H:i:s', CRM_Utils_Request::retrieve('start', 'String')),
      'endDate' => gmdate('Y-m-d H:i:s', CRM_Utils_Request::retrieve('end', 'String')),
    ];

    $eventsHandler = new CRM_Calendar_Common_Handler($finalContactIds, $params);
    $events = $eventsHandler->getAllEventsWeb();

    CRM_Utils_JSON::output($events);
  }

}
