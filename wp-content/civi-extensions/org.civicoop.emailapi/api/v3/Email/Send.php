<?php

/**
 * Email.Send API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_email_send_spec(&$spec) {
  $spec['contact_id'] = [
    'title' => 'Contact ID',
    'api.required' => 1,
  ];
  $spec['template_id'] = [
    'title' => 'Template ID',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 1,
  ];
  $spec['case_id'] = [
    'title' => 'Case ID',
    'type' => CRM_Utils_Type::T_INT,
  ];
  $spec['activity_id'] = [
    'title' => 'Activity ID',
    'type' => CRM_Utils_Type::T_INT,
  ];
  $spec['contribution_id'] = [
    'title' => 'Contribution ID',
    'type' => CRM_Utils_Type::T_INT,
  ];
  $spec['activity_id'] = [
    'title' => 'Activity ID',
    'type' => CRM_Utils_Type::T_INT,
  ];
  $spec['event_id'] = [
    'title' => 'Event ID',
    'type' => CRM_Utils_Type::T_INT,
  ];  
  $spec['location_type_id'] = [
    'title' => 'Location type id',
    'type' => CRM_Utils_Type::T_INT,
  ];
  $spec['alternative_receiver_address'] = [
    'title' => 'Alternative receiver address',
    'type' => CRM_Utils_Type::T_STRING,
  ];
  $spec['cc'] = [
    'title' => 'Cc',
    'type' => CRM_Utils_Type::T_STRING,
  ];
  $spec['bcc'] = [
    'title' => 'Bcc',
    'type' => CRM_Utils_Type::T_STRING,
  ];
  $spec['subject'] = [
    'title' => 'Subject',
    'type' => CRM_Utils_Type::T_STRING,
  ];
  $spec['extra_data'] = [
    'title' => 'Extra data',
    'type' => CRM_Utils_Type::T_TEXT,
  ];

  // Copy from MessageTemplate.send API
  $spec['disable_smarty'] = [
    'description' => 'Disable Smarty. Normal CiviMail tokens are still supported. By default Smarty is enabled if configured by CIVICRM_MAIL_SMARTY.',
    'title' => 'Disable Smarty',
    'type' => CRM_Utils_Type::T_BOOLEAN,
  ];

  $spec['create_activity'] = [
    'title' => 'Create Activity',
    'description' => 'Usually an Email activity is created when an email is sent.',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.default' => 1,
  ];

  $spec['activity_details'] = [
    'title' => 'Activity details',
    'description' => 'What to include in the details field of the created activity',
    'type' => CRM_Utils_Type::T_TEXT,
    'api.default' => 'html,text',
    'options' => [
      'html,text' => 'HTML and Text versions of the body',
      'tplName' => 'Just the name of the message template',
      'html' => 'Just the HTML version of the body',
      'text' => 'Just the text version of the body',
    ],
  ];

  $spec['from_email_option'] = [
    'title' => 'From Email Address Option value',
    'type' => CRM_Utils_Type::T_INT,
  ];

}

/**
 * Email.Send API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_email_send($params) {
  // @todo contact_id accepts multiple but other params do not. So eg. each contact gets the same activity.
  //   That may be what we want but it may not!
  //   We could add a "context" param that takes an array of params instead (note that we can only support one of each entity currently).
  //   [['contact_id' => 1, 'activity_id' => 123, ..], ['contact_id' => 2, 'activity_id' => 456]]
  // @todo Perhaps we could use TokenProcessor if passed the context param and the "old" method otherwise?
  if (!CRM_Utils_Type::validate($params['contact_id'], 'CommaSeparatedIntegers') && !isset($params['alternative_receiver_address'])) {
    throw new CRM_Core_Exception('Parameter contact_id must be a unique id or a list of ids separated by comma');
  }
  $params['contact_id'] = explode(',', $params['contact_id'] ?? '');
  $locationTypeId = !empty($params['location_type_id']) ? $params['location_type_id'] : FALSE;
  $alternativeEmailAddress = !empty($params['alternative_receiver_address']) ? $params['alternative_receiver_address'] : FALSE;

  $messageTemplates = new CRM_Core_DAO_MessageTemplate();
  $messageTemplates->id = $params['template_id'];

  list($defaultFromName, $defaultFromEmail) = CRM_Core_BAO_Domain::getNameAndEmail();
  $from = "\"$defaultFromName\" <$defaultFromEmail>";
  if (!empty($params['from_email']) && !empty($params['from_name'])) {
    // If both an email and a name are provided, use those as the from header.
    $from = '"' . $params['from_name'] . '" <' . $params['from_email'] . '>';
  }
  elseif (!empty($params['from_email_option'])) {
    $from = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('label')
      ->addWhere('option_group_id:name', '=', 'from_email_address')
      ->addWhere('value', '=', $params['from_email_option'])
      ->execute()
      ->first()['label'] ?? NULL;
    if (!$from) {
      throw new CRM_Core_Exception('Cannot find from_email_option');
    }
  }

  if (empty($from)) {
    throw new CRM_Core_Exception('Did not find valid from e-mail address. You have to provide both from_name and from_email or from_email_option');
  }

  if (!$messageTemplates->find(TRUE)) {
    throw new CRM_Core_Exception('Could not find template with ID: ' . $params['template_id']);
  }

  $returnValues = [];
  for ($i = 0; $i < count($params['contact_id']); $i++) {
    $contactId = $params['contact_id'][$i];
    $contact = \Civi\Api4\Contact::get(FALSE)
      ->addSelect('do_not_email', 'email_primary.email', 'is_deceased', 'is_deleted', 'email_primary.on_hold', 'display_name')
      ->addWhere('id', '=', $contactId)
      ->execute()
      ->first();
    if ($alternativeEmailAddress) {
      /*
       * If an alternative recipient address is given
       * then send email to that address rather than to
       * the email address of the contact
       */
      $toName = '';
      $toEmail = $alternativeEmailAddress;
    }
    elseif ($contact['do_not_email'] || empty($contact['email_primary.email']) || !empty($contact['is_deceased']) || $contact['email_primary.on_hold'] || !empty($contact['is_deleted'])) {
      /*
       * Contact is deceased or has opted out from mailings so do not send the email
       */
      \Civi::log()->debug("EmailAPI: Contact {$contactId} has no email address, is deceased or has opted out from mailings so do not send the email");
      continue;
    }
    else {
      $toName = $contact['display_name'];
      $toEmail = $contact['email_primary.email'];
    }
    if ($locationTypeId) {
      $locationAddress = \Civi\Api4\Email::get(FALSE)
        ->addSelect('email')
        ->addWhere('location_type_id', '=', $locationTypeId)
        ->addWhere('contact_id', '=', $contactId)
        ->addOrderBy('id', 'DESC')
        ->execute()
        ->first()['email'] ?? NULL;
      if (!$locationAddress) {
        \Civi::log()->debug("EmailAPI: Contact {$contactId} no email address for location {$locationTypeId}. Falling back to {$toEmail}");
      }
      $toEmail = $locationAddress;
    }

    // Change the user language (if multilingual)
    $preferred_language = NULL;
    if (CRM_Core_I18n::isMultilingual()) {
      $preferred_language = CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $contactId, 'preferred_language');
      $preferred_language = CRM_Core_BAO_ActionSchedule::pickLocale(CRM_Core_I18n::AUTO, $preferred_language);
    }

    $message['messageSubject'] = (empty($params['subject']) ? $messageTemplates->msg_subject : $params['subject']);
    $message['text'] = $messageTemplates->msg_text ?: CRM_Utils_String::htmlToText($messageTemplates->msg_html);
    $message['html'] = $messageTemplates->msg_html;
    $message_params = $params;
    $message_params['contact_id'] = $contactId;
    list('messageSubject' => $messageSubject, 'html' => $html, 'text' => $text) = CRM_Emailapi_Utils_Tokens::replaceTokens($contactId, $message, $message_params, $preferred_language);

    // set up the parameters for CRM_Utils_Mail::send
    $mailParams = [
      'groupName' => 'Email from API',
      'from' => $from,
      'toName' => $toName,
      'toEmail' => $toEmail,
      'subject' => $messageSubject,
      'messageTemplateID' => $messageTemplates->id,
      'contactId' => $contactId,
      'api_params' => $params,
    ];

    // render the &amp; entities in text mode, so that the links work
    $mailParams['text'] = str_replace('&amp;', '&', $text);
    $mailParams['html'] = $html;
    if (!empty($params['cc'])) {
      $mailParams['cc'] = $params['cc'];
    }
    if (!empty($params['bcc'])) {
      $mailParams['bcc'] = $params['bcc'];
    }

    // We are ready to send. Record that we are going to try to send the email.
    if ($params['create_activity']) {
      //create activity for sending email.
      $activityTypeID = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', 'Email');

      switch ($params['activity_details']) {
        case 'html,text':
          // Legacy default, unchanged. Falls back to just text if no HTML available.
          // CRM-6265: save both text and HTML parts in details (if present)
          if ($html and $text) {
            $details = "-ALTERNATIVE ITEM 0-\n$html\n-ALTERNATIVE ITEM 1-\n$text\n-ALTERNATIVE END-\n";
          }
          else {
            $details = $html ?: $text;
          }
          break;

        case 'html':
          $details = $html ?: $text;
          break;

        case 'text':
          $details = $text;
          break;

        case 'tplName':
          $details = "Message Template " . $messageTemplates->id . " <em>" . htmlspecialchars($messageTemplates->msg_title) . "</em>";
          break;
      }

      $activityParams = [
        'source_contact_id' => $contactId,
        'activity_type_id' => $activityTypeID,
        'activity_date_time' => date('YmdHis'),
        'subject' => $messageSubject,
        'details' => $details ?? '',
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_status_id', 'Cancelled'),
      ];

      try {
        $activity = civicrm_api3('Activity', 'create', $activityParams);

        $activityContacts = CRM_Core_OptionGroup::values('activity_contacts', FALSE, FALSE, FALSE, NULL, 'name');
        $targetID = CRM_Utils_Array::key('Activity Targets', $activityContacts);

        $activityTargetParams = [
          'activity_id' => $activity['id'],
          'contact_id' => $contactId,
          'record_type_id' => $targetID,
        ];
        CRM_Activity_BAO_ActivityContact::create($activityTargetParams);

        $caseId = NULL;
        if (!empty($case_id)) {
          $caseId = $case_id;
        }
        if (!empty($params['case_id'])) {
          $caseId = $params['case_id'];
        }
        if ($caseId) {
          $caseActivity = [
            'activity_id' => $activity['id'],
            'case_id' => $caseId,
          ];
          CRM_Case_BAO_Case::processCaseActivity($caseActivity);
        }
      }
      catch (Exception $e) {
        \Civi::log()->error("EmailAPI: Failed to create email activity for contactID: {$contactId}" . $e->getMessage());
      }
    }

    // Set the ID of the email activity (if we created one)
    $mailParams['emailActivityID'] = $activity['id'] ?? NULL;

    // It's possible, eg, that sendReminderEmail fires Hook::alterMailParams() and that some listener use ts().
    $swapLocale = empty($preferred_language) ? NULL : \CRM_Utils_AutoClean::swapLocale($preferred_language);

    // Try to send the email.
    $result = CRM_Utils_Mail::send($mailParams);
    if (!$result) {
      unset($swapLocale);
      throw new CRM_Core_Exception('Error sending email to ' . $contact['display_name'] . ' <' . $mailParams['toEmail'] . '> ');
    }

    // Switch back language
    unset($swapLocale);

    if ($params['create_activity']) {
      // Update the activity to Completed, since we know sending was successful.
      $activityParams = [
        'id' => $activity['id'],
        'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_status_id', 'Completed'),
        'return' => 'id',
      ];
      try {
        civicrm_api3('Activity', 'create', $activityParams);
      }
      catch (Exception $e) {
        \Civi::log()->error("EmailAPI: Failed to update email activity to Completed for contactID: {$contactId}" . $e->getMessage());
      }
    }

    $returnValues[$contactId] = [
      'contact_id' => $contactId,
      'send' => 1,
      'status_msg' => "Successfully sent email to {$mailParams['toEmail']}",
    ];
  }

  return civicrm_api3_create_success($returnValues, $params, 'Email', 'Send');
}
