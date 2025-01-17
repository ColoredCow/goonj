<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\Group;
use Civi\Api4\GroupContact;
use Civi\Core\Service\AutoSubscriber;
use Civi\Api4\EckEntity;
use Civi\Traits\CollectionSource;
use Civi\Api4\OptionValue;

/**
 *
 */
class UrbanPlannedVisitService extends AutoSubscriber {
  use CollectionSource;
  const ENTITY_NAME = 'Institution_Visit';
  const ENTITY_SUBTYPE_NAME = 'Urban_Visit';

  /**
   *
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_pre' => [
        ['assignChapterGroupToIndividualForUrbanPlannedVisit'],
        ['sendAuthorizationEmailToExtCoordPoc'],
        ['sendAuthorizationEmailToExtCoordPocAndVisitGuide'],
        ['sendVisitOutcomeForm'],
      ],
      '&hook_civicrm_tabset' => 'urbanVisitTabset',
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
  public static function sendAuthorizationEmailToExtCoordPocAndVisitGuide(string $op, string $objectName, $objectId, &$objectRef) {
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

      $visitData = EckEntity::get('Institution_Visit', FALSE)
        ->addSelect('Urban_Planned_Visit.Number_of_people_accompanying_you', 'Urban_Planned_Visit.When_do_you_wish_to_visit_Goonj', 'Urban_Planned_Visit.What_time_do_you_wish_to_visit_', 'Urban_Planned_Visit.Institution_Name')
        ->addWhere('id', '=', $visitId)
        ->execute()->single();

      $visitDate = $visitData['Urban_Planned_Visit.When_do_you_wish_to_visit_Goonj'];
      $visitTime = $visitData['Urban_Planned_Visit.What_time_do_you_wish_to_visit_'];
      $visitParticipation = $visitData['Urban_Planned_Visit.Number_of_people_accompanying_you'];
      $institutionName = $visitData['Urban_Planned_Visit.Institution_Name'];

      $goonjCoordinatingPocId = $objectRef['Urban_Planned_Visit.Coordinating_Goonj_POC'] ?? '';

      $coordinatingGoonjPoc = Contact::get(FALSE)
        ->addSelect('email.email', 'display_name', 'phone.phone_numeric')
        ->addJoin('Email AS email', 'LEFT')
        ->addWhere('id', '=', $goonjCoordinatingPocId)
        ->execute()->single();

      $coordinatingGoonjPocEmail = $coordinatingGoonjPoc['email.email'];
      $coordinatingGoonjPocName = $coordinatingGoonjPoc['display_name'];

      $from = HelperService::getDefaultFromEmail();

      $optionValue = OptionValue::get(FALSE)
        ->addWhere('option_group_id:name', '=', 'activity_type')
        ->addWhere('label', '=', 'Planned Visit User')
        ->execute()->single();

      $activityTypeId = $optionValue['value'];

      $activity = Activity::get(FALSE)
        ->addSelect('contact.display_name')
        ->addJoin('ActivityContact AS activity_contact', 'LEFT')
        ->addJoin('Contact AS contact', 'LEFT')
        ->addWhere('activity_type_id', '=', $activityTypeId)
        ->addWhere('Volunteering_Activity.Urban_Planned_Visit', '=', $visitId)
        ->execute()->first();

      $individualName = $activity['contact.display_name'];

      $goonjVisitGuideIds = $objectRef['Urban_Planned_Visit.Visit_Guide'] ?? '';

      if (!$goonjVisitGuideIds) {
        return;
      }

      $goonjVisitGuideIds = is_array($goonjVisitGuideIds) ? $goonjVisitGuideIds : [$goonjVisitGuideIds];

      self::sendEmailToVisitGuide(
        $visitId,
        $visitDate,
        $visitTime,
        $visitParticipation,
        $coordinatingGoonjPocName,
        $from,
        $coordinatingGoonjPocEmail,
        $individualName,
        $institutionName,
        $goonjVisitGuideIds
        );
    }
  }

  /**
   *
   */
  private static function sendEmailToVisitGuide($visitId, $visitDate, $visitTime, $visitParticipation, $coordinatingGoonjPocName, $from, $coordinatingGoonjPocEmail, $individualName, $institutionName, $goonjVisitGuideIds) {
    $emailToGoonjVisitGuide = EckEntity::get('Institution_Visit', FALSE)
      ->addSelect('Urban_Planned_Visit.Email_To_Goonj_Visit_Guide',)
      ->addWhere('id', '=', $visitId)
      ->execute()->single();

    $isEmailSendToGoonjVisitGuide = $emailToGoonjVisitGuide['Urban_Planned_Visit.Email_To_Goonj_Visit_Guide'];

    if ($isEmailSendToGoonjVisitGuide !== NULL) {
      return;
    }

    $allEmailsSent = TRUE;

    foreach ($goonjVisitGuideIds as $goonjVisitGuideId) {
      $goonjVisitGuideData = Contact::get(FALSE)
        ->addSelect('email.email', 'display_name', 'phone.phone_numeric')
        ->addJoin('Email AS email', 'LEFT')
        ->addWhere('id', '=', $goonjVisitGuideId)
        ->execute()
        ->single();

      $goonjVisitGuideEmail = $goonjVisitGuideData['email.email'];
      $goonjVisitGuideName = $goonjVisitGuideData['display_name'];

      $mailParamsVisitGuide = [
        'subject' => 'You have been assigned for a Learning Journey at GCoC',
        'from' => $from,
        'toEmail' => $goonjVisitGuideEmail,
        'replyTo' => $from,
        'html' => self::getGoonjCoordPocAndVisitEmailHtml($coordinatingGoonjPocName, $visitDate, $visitTime, $visitParticipation, $goonjVisitGuideName, $individualName, $institutionName),
        'cc' => $coordinatingGoonjPocEmail,
      ];

      $emailSendResultToVisitGuide = \CRM_Utils_Mail::send($mailParamsVisitGuide);

      if (!$emailSendResultToVisitGuide) {
        $allEmailsSent = FALSE;
      }
    }

    if ($allEmailsSent) {
      EckEntity::update('Institution_Visit', FALSE)
        ->addValue('Urban_Planned_Visit.Email_To_Goonj_Visit_Guide', 1)
        ->addWhere('id', '=', $visitId)
        ->execute();
    }
  }

  /**
   * Generate the email HTML content for Goonj Coordinating POC.
   */
  private static function getGoonjCoordPocAndVisitEmailHtml($coordinatingGoonjPocName, $visitDate, $visitTime, $visitParticipation, $goonjVisitGuideName, $individualName, $institutionName) {
    $date = new \DateTime($visitDate);
    $dayOfWeek = $date->format('l');

    // Conditionally construct the Individual/Institute Name string.
    $individualOrInstitute = $individualName;

    if (!empty($institutionName)) {
      $individualOrInstitute .= " / $institutionName";
    }

    $html = "
<p>Dear $goonjVisitGuideName,</p>

<p>A Learning Journey at our Goonj Center of Circularity (GCoC) has been confirmed, and you have been assigned for this visit as per the roster/availability. Details below:</p>

<ul>
    <li><strong>Date:</strong> $visitDate, $dayOfWeek</li>
    <li><strong>Time:</strong> $visitTime</li>
    <li><strong>Individual/Institute Name:</strong> $individualOrInstitute</li>
    <li><strong>Number of Participants:</strong> $visitParticipation</li>
</ul>

<p>Kindly ensure your presence during the above time slot. If you need to make any changes, please write back to me asap so we can adjust the schedule accordingly.</p>

<p>Let’s work together to create a meaningful and enriching experience for our visitors!</p>

<p>
  Warm regards,<br>
  $coordinatingGoonjPocName<br>
  Team Goonj
</p>
";

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
  public static function sendAuthorizationEmailToExtCoordPoc(string $op, string $objectName, $objectId, &$objectRef) {
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

      $emailToExtCoordPoc = EckEntity::get('Institution_Visit', FALSE)
        ->addSelect('Urban_Planned_Visit.Email_To_Ext_Coord_Poc')
        ->addWhere('id', '=', $visitId)
        ->execute()->single();

      $isEmailSendToExtCoordPoc = $emailToExtCoordPoc['Urban_Planned_Visit.Email_To_Ext_Coord_Poc'];

      if ($isEmailSendToExtCoordPoc !== NULL) {
        return;
      }

      $visitData = EckEntity::get('Institution_Visit', FALSE)
        ->addSelect('Urban_Planned_Visit.Which_Goonj_Processing_Center_do_you_wish_to_visit_', 'Urban_Planned_Visit.When_do_you_wish_to_visit_Goonj', 'Urban_Planned_Visit.What_time_do_you_wish_to_visit_')
        ->addWhere('id', '=', $visitId)
        ->execute()->single();

      $visitAtId = $visitData['Urban_Planned_Visit.Which_Goonj_Processing_Center_do_you_wish_to_visit_'];
      $visitDate = $visitData['Urban_Planned_Visit.When_do_you_wish_to_visit_Goonj'];
      $visitTime = $visitData['Urban_Planned_Visit.What_time_do_you_wish_to_visit_'];

      $contact = Contact::get(FALSE)
        ->addSelect('address.street_address', 'address.city')
        ->addJoin('Address AS address', 'LEFT')
        ->addWhere('id', '=', $visitAtId)
        ->execute()->single();

      $visitAddress = $contact['address.street_address'];
      $visitAtName = $contact['address.city'];

      $externalCoordinatingPocId = $objectRef['Urban_Planned_Visit.External_Coordinating_PoC'] ?? '';
      $coordinatingPocId = $objectRef['Urban_Planned_Visit.Coordinating_Person'] ?? '';
      $coordinatingGoonjPocId = $objectRef['Urban_Planned_Visit.Coordinating_Goonj_POC'] ?? '';

      $coordinatingGoonjPocPerson = Contact::get(FALSE)
        ->addSelect('display_name', 'phone.phone_numeric')
        ->addJoin('Phone AS phone', 'LEFT')
        ->addWhere('id', '=', $coordinatingGoonjPocId)
        ->execute()->single();

      $coordinatingGoonjPersonName = $coordinatingGoonjPocPerson['display_name'];
      $coordinatingGoonjPersonPhone = $coordinatingGoonjPocPerson['phone.phone_numeric'];

      $coordinatingPerson = Contact::get(FALSE)
        ->addSelect('display_name')
        ->addWhere('id', '=', $coordinatingPocId)
        ->execute()->single();

      $coordinatingPersonName = $coordinatingPerson['display_name'];

      $externalCoordinatingGoonjPoc = Contact::get(FALSE)
        ->addSelect('email.email', 'display_name', 'phone.phone_numeric')
        ->addJoin('Email AS email', 'LEFT')
        ->addJoin('Phone AS phone', 'LEFT')
        ->addWhere('id', '=', $externalCoordinatingPocId)
        ->execute()->single();

      $externalCoordinatingGoonjPocEmail = $externalCoordinatingGoonjPoc['email.email'];
      $externalCoordinatingGoonjPocName = $externalCoordinatingGoonjPoc['display_name'];
      $externalCoordinatingGoonjPocPhone = $externalCoordinatingGoonjPoc['phone.phone_numeric'];

      $from = HelperService::getDefaultFromEmail();

      $mailParamsExternalPoc = [
        'subject' => $externalCoordinatingGoonjPocName . ', your Learning Journey is Scheduled!',
        'from' => $from,
        'toEmail' => $externalCoordinatingGoonjPocEmail,
        'replyTo' => $from,
        'html' => self::getExtCoordPocEmailHtml($externalCoordinatingGoonjPocName, $visitAtName, $visitAddress, $visitDate, $visitTime, $coordinatingPersonName, $coordinatingGoonjPersonName, $coordinatingGoonjPersonPhone),
      ];

      $emailSendResultToExternalPoc = \CRM_Utils_Mail::send($mailParamsExternalPoc);

      if ($emailSendResultToExternalPoc) {
        EckEntity::update('Institution_Visit', FALSE)
          ->addValue('Urban_Planned_Visit.Email_To_Ext_Coord_Poc', 1)
          ->addWhere('id', '=', $visitId)
          ->execute();
      }
    }
  }

  /**
   *
   */
  private static function getExtCoordPocEmailHtml($externalCoordinatingGoonjPocName, $visitAtName, $visitAddress, $visitDate, $visitTime, $coordinatingPersonName, $coordinatingGoonjPersonName, $coordinatingGoonjPersonPhone) {
    $date = new \DateTime($visitDate);
    $dayOfWeek = $date->format('l');

    $html = "
    <p>Dear $externalCoordinatingGoonjPocName,</p>

    <p>Thank you for coordinating the learning journey at Goonj. Below are the details:</p>

    <p>
        Thank you for choosing to explore Goonj through a learning journey at Goonj Center of Circularity (GCOC) ! Your visit is scheduled as per the below details :
    </p>
    <ul>
        <li><strong>At:</strong> $visitAtName </li>
        <li><strong>Address:</strong> $visitAddress </li>
        <li><strong>Directions:</strong> <a href='https://www.google.com/maps?q=$visitAddress' target='_blank'>View Directions on Google Maps</a></li>
        <li><strong>On:</strong>  $visitDate , $dayOfWeek</li>
        <li><strong>From:</strong> $visitTime </li>
        <li><strong>Contact Point:</strong> $coordinatingPersonName</li>
    </ul>

    
    <p>We’re excited to give you a first-hand glimpse into our work and its impact.  For assistance, feel free to write back or call $coordinatingGoonjPersonName on $coordinatingGoonjPersonPhone</p>
    <p>We look forward to hosting you!</p>
    <p>Best wishes,</p>
    <p>Team Goonj..</p>

    <p>Meanwhile, sharing a few reading materials and links. We encourage you to explore them before your visit to get a sneak peek into our work:</p>
    <ul>
    <li>1. <a href='https://goonj.org/'>Goonj Website</a></li>
    <li>2. <a href='https://goonj.org/dignitydiaries/'>Ground Reports</a></li>
    <li>3. <a href='https://www.youtube.com/watch?v=qOowwnlPcAE'>Video: National Geographic on Goonj - Redefining Philanthropy</a></li>
    <li>4. Articles by our founder, Mr. Anshu Gupta:
        <ul>
            <li>- <a href='https://yourstory.com/2018/01/money-currency'>Why Should Money Be the Only Currency?</a></li>
            <li>- <a href='https://www.livemint.com/news/business-of-life/anshu-gupta-give-with-dignity-1540994585257.html'>Give with Dignity</a></li>
        </ul>
    </li>
</ul>";

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

  /**
   *
   */
  public static function urbanVisitTabset($tabsetName, &$tabs, $context) {
    if (!self::isViewingVisit($tabsetName, $context)) {
      return;
    }

    $tabConfigs = [
      'visitOutcome' => [
        'title' => ts('Visit Outcome'),
        'module' => 'afsearchVisitUrbanOutcomeDetails',
        'directive' => 'afsearch-visit-urban-outcome-details',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'visitContact' => [
        'title' => ts('Visit Contact'),
        'module' => 'afsearchVisitContactPerson',
        'directive' => 'afsearch-visit-contact-person',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
      'visitFeedback' => [
        'title' => ts('Visit Feedback'),
        'module' => 'afsearchVisitFeedbackDetails',
        'directive' => 'afsearch-visit-feedback-details',
        'template' => 'CRM/Goonjcustom/Tabs/CollectionCamp.tpl',
        'permissions' => ['goonj_chapter_admin', 'urbanops'],
      ],
    ];

    foreach ($tabConfigs as $key => $config) {
      if (!\CRM_Core_Permission::checkAnyPerm($config['permissions'])) {
        // Does not permission; just continue.
        continue;
      }

      $tabs[$key] = [
        'id' => $key,
        'title' => $config['title'],
        'is_active' => 1,
        'template' => $config['template'],
        'module' => $config['module'],
        'directive' => $config['directive'],
      ];

      \Civi::service('angularjs.loader')->addModules($config['module']);
    }
  }

  /**
   *
   */
  private static function isViewingVisit($tabsetName, $context) {
    if ($tabsetName !== 'civicrm/eck/entity' || empty($context) || $context['entity_type']['name'] !== self::ENTITY_NAME) {
      return FALSE;
    }

    $entityId = $context['entity_id'];

    $entity = EckEntity::get(self::ENTITY_NAME, TRUE)
      ->addWhere('id', '=', $entityId)
      ->execute()->single();

    $entitySubtypeValue = $entity['subtype'];
    $subtypeId = self::getSubtypeId();

    return (int) $entitySubtypeValue === $subtypeId;
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

  /**
   *
   */
  public static function assignChapterGroupToIndividualForUrbanPlannedVisit(string $op, string $objectName, $objectId, &$objectRef) {
    if ($op !== 'edit' || $objectName !== 'AfformSubmission') {
      return FALSE;
    }

    if (empty($objectRef['data']['Eck_Institution_Visit1'])) {
      return FALSE;
    }

    $individualData = $objectRef['data']['Individual1'];
    $visitData = $objectRef['data']['Eck_Institution_Visit1'];

    foreach ($individualData as $individual) {
      $contactId = $individual['id'] ?? NULL;
    }

    foreach ($visitData as $visit) {
      $fields = $visit['fields'] ?? [];
      $stateProvinceId = $fields['Urban_Planned_Visit.State'] ?? NULL;
    }

    $groupId = self::getChapterGroupForState($stateProvinceId);

    if ($groupId & $contactId) {
      GroupContact::create(FALSE)
        ->addValue('contact_id', $contactId)
        ->addValue('group_id', $groupId)
        ->addValue('status', 'Added')
        ->execute();
    }
  }

  /**
   *
   */
  private static function getChapterGroupForState($stateId) {
    $stateContactGroups = Group::get(FALSE)
      ->addSelect('id')
      ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
      ->addWhere('Chapter_Contact_Group.Contact_Catchment', 'CONTAINS', $stateId)
      ->execute();

    $stateContactGroup = $stateContactGroups->first();

    if (!$stateContactGroup) {
      \CRM_Core_Error::debug_log_message('No chapter contact group found for state ID: ' . $stateId);

      $fallbackGroups = Group::get(FALSE)
        ->addWhere('Chapter_Contact_Group.Use_Case', '=', 'chapter-contacts')
        ->addWhere('Chapter_Contact_Group.Fallback_Chapter', '=', 1)
        ->execute();

      $stateContactGroup = $fallbackGroups->first();

      \Civi::log()->info('Assigning fallback chapter contact group: ' . $stateContactGroup['title']);
    }

    return $stateContactGroup ? $stateContactGroup['id'] : NULL;
  }

  /**
   *
   */
  public static function sendReminderEmailToExtCoordPoc($visit) {
    $externalCoordinatingPocId = $visit['Urban_Planned_Visit.External_Coordinating_PoC'] ?? '';

    $externalCoordinatingGoonjPoc = Contact::get(FALSE)
      ->addSelect('email.email', 'display_name', 'phone.phone_numeric')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('id', '=', $externalCoordinatingPocId)
      ->execute()->single();

    $externalCoordinatingGoonjPocEmail = $externalCoordinatingGoonjPoc['email.email'];
    $externalCoordinatingGoonjPocName = $externalCoordinatingGoonjPoc['display_name'];

    $coordinatingGoonjPocId = $visit['Urban_Planned_Visit.Coordinating_Goonj_POC'] ?? '';

    $coordinatingGoonjPocPerson = Contact::get(FALSE)
      ->addSelect('display_name', 'phone.phone_numeric')
      ->addJoin('Phone AS phone', 'LEFT')
      ->addWhere('id', '=', $coordinatingGoonjPocId)
      ->execute()->single();

    $coordinatingGoonjPersonName = $coordinatingGoonjPocPerson['display_name'];
    $coordinatingGoonjPersonPhone = $coordinatingGoonjPocPerson['phone.phone_numeric'];

    $visitTime = $visit['Urban_Planned_Visit.What_time_do_you_wish_to_visit_'];
    $visitAtId = $visit['Urban_Planned_Visit.Which_Goonj_Processing_Center_do_you_wish_to_visit_'];

    $contact = Contact::get(FALSE)
      ->addSelect('address.street_address', 'address.city')
      ->addJoin('Address AS address', 'LEFT')
      ->addWhere('id', '=', $visitAtId)
      ->execute()->single();

    $visitAddress = $contact['address.street_address'];
    $visitAtName = $contact['address.city'];
    $from = HelperService::getDefaultFromEmail();

    $reminderMailParamsExternalPoc = [
      'subject' => $externalCoordinatingGoonjPocName . ', your Learning Journey is scheduled for today !',
      'from' => $from,
      'toEmail' => $externalCoordinatingGoonjPocEmail,
      'replyTo' => $from,
      'html' => self::getReminderExtCoordPocEmailHtml($externalCoordinatingGoonjPocName, $coordinatingGoonjPersonName, $coordinatingGoonjPersonPhone, $visitTime, $visitAddress, $visitAtName),
    ];
    $emailSendResultToExternalPoc = \CRM_Utils_Mail::send($reminderMailParamsExternalPoc);

    if ($emailSendResultToExternalPoc) {
      EckEntity::update('Institution_Visit', FALSE)
        ->addValue('Urban_Planned_Visit.Reminder_Email_To_Ext_Coord_Poc', 1)
        ->addWhere('id', '=', $visit['id'])
        ->execute();
    }
  }

  /**
   *
   */
  private static function getReminderExtCoordPocEmailHtml($externalCoordinatingGoonjPocName, $coordinatingGoonjPersonName, $coordinatingGoonjPersonPhone, $visitTime, $visitAddress, $visitAtName) {
    $html = "
    <p>Dear $externalCoordinatingGoonjPocName,</p>

    <p>We look forward to meeting you today at <strong>$visitTime</strong> and taking you through a <strong><u>learning journey at Goonj Center of Circularity (GCOC) !</u></strong> Below directions to reach our center:</p>

    <ul>
        <li><strong>At:</strong> $visitAtName</li>
        <li><strong>Address:</strong> $visitAddress center</li>
        <li><strong>Directions:</strong> <a href='https://www.google.com/maps?q=$visitAddress' target='_blank'>View Directions on Google Maps</a></li>
    </ul>

    <p>For assistance, feel free to write back or call <strong>$coordinatingGoonjPersonName</strong> on <strong>$coordinatingGoonjPersonPhone</strong>.</p>
    
    <p>We look forward to hosting you!</p>
    <p>Best wishes,</p>
    <p>Team Goonj..</p>
    ";

    return $html;
  }

  /**
   *
   */
  public static function sendReminderEmailToCoordPerson($visit) {
    $visitParticipation = $visit['Urban_Planned_Visit.Number_of_people_accompanying_you'];
    $institutionName = $visit['Urban_Planned_Visit.Institution_Name'];
    $visitId = $visit['id'];
    $visitTime = $visit['Urban_Planned_Visit.What_time_do_you_wish_to_visit_'];

    $goonjVisitGuideId = $visit['Urban_Planned_Visit.Visit_Guide'] ?? '';
    $goonjVisitGuideIds = is_array($goonjVisitGuideId) ? $goonjVisitGuideId : [$goonjVisitGuideId];

    if (!$goonjVisitGuideIds) {
      return;
    }

    $goonjCoordinatingPocId = $visit['Urban_Planned_Visit.Coordinating_Goonj_POC'] ?? '';

    $coordinatingGoonjPoc = Contact::get(FALSE)
      ->addSelect('email.email', 'display_name', 'phone.phone_numeric')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('id', '=', $goonjCoordinatingPocId)
      ->execute()->single();

    $coordinatingGoonjPocEmail = $coordinatingGoonjPoc['email.email'];
    $coordinatingGoonjPocName = $coordinatingGoonjPoc['display_name'];

    $from = HelperService::getDefaultFromEmail();

    $optionValue = OptionValue::get(FALSE)
      ->addWhere('option_group_id:name', '=', 'activity_type')
      ->addWhere('label', '=', 'Planned Visit User')
      ->execute()->single();

    $activityTypeId = $optionValue['value'];

    $activity = Activity::get(FALSE)
      ->addSelect('contact.display_name')
      ->addJoin('ActivityContact AS activity_contact', 'LEFT')
      ->addJoin('Contact AS contact', 'LEFT')
      ->addWhere('activity_type_id', '=', $activityTypeId)
      ->addWhere('Volunteering_Activity.Urban_Planned_Visit', '=', $visitId)
      ->execute()->first();

    $individualName = $activity['contact.display_name'];

    $allEmailsSent = TRUE;

    foreach ($goonjVisitGuideIds as $goonjVisitGuideId) {
      $goonjVisitGuideData = Contact::get(FALSE)
        ->addSelect('email.email', 'display_name', 'phone.phone_numeric')
        ->addJoin('Email AS email', 'LEFT')
        ->addWhere('id', '=', $goonjVisitGuideId)
        ->execute()->single();

      $goonjVisitGuideEmail = $goonjVisitGuideData['email.email'];
      $goonjVisitGuideName = $goonjVisitGuideData['display_name'];

      $reminderMailParamsVisitGuide = [
        'subject' => 'Reminder to take the Learning Journey at GCoC today',
        'from' => $from,
        'toEmail' => $goonjVisitGuideEmail,
        'replyTo' => $from,
        'html' => self::getGoonjReminderCoordPocAndVisitEmailHtml($coordinatingGoonjPocName, $visitTime, $visitParticipation, $goonjVisitGuideName, $individualName, $institutionName),
        'cc' => $coordinatingGoonjPocEmail,
      ];

      $emailSendResultToVisitGuide = \CRM_Utils_Mail::send($reminderMailParamsVisitGuide);

      if (!$emailSendResultToVisitGuide) {
        $allEmailsSent = FALSE;
      }
    }

    if ($emailSendResultToVisitGuide) {
      EckEntity::update('Institution_Visit', FALSE)
        ->addValue('Urban_Planned_Visit.Reminder_Email_To_Coord_Person', 1)
        ->addWhere('id', '=', $visitId)
        ->execute();
    }
  }

  /**
   *
   */
  private static function getGoonjReminderCoordPocAndVisitEmailHtml($coordinatingGoonjPocName, $visitTime, $visitParticipation, $goonjVisitGuideName, $individualName, $institutionName) {
    // Conditionally construct the Individual/Institute Name string.
    $individualOrInstitute = $individualName;

    if (!empty($institutionName)) {
      $individualOrInstitute .= " / $institutionName";
    }

    $html = "
<p>Dear $goonjVisitGuideName,</p>

<p>Just a little nudge to remind you about today’s Learning Journey at $visitTime!  Here are the details:</p>

<ul
<li><strong>Individual/Institute Name:</strong> $individualOrInstitute</li>
    <li><strong>Number of Participants:</strong> $visitParticipation</li>
</ul>

<p>We are counting on you to bring your energy and enthusiasm to make this visit truly special and meaningful experience for our visitors!  All the best 😊 </p>

<p>
  Warm regards,<br>
  $coordinatingGoonjPocName<br>
  Team Goonj
</p>
";

    return $html;
  }

  /**
   *
   */
  public static function sendFeedbackEmailToExtCoordPoc($visit) {
    $emailToExtCoordPoc = EckEntity::get('Institution_Visit', FALSE)
      ->addSelect('Visit_Feedback.Feedback_Email_Sent')
      ->addWhere('id', '=', $visit['id'])
      ->execute()->single();

    $isEmailSendToExtCoordPoc = $emailToExtCoordPoc['Visit_Feedback.Feedback_Email_Sent'];

    if ($isEmailSendToExtCoordPoc !== NULL) {
      return;
    }

    $externalCoordinatingPocId = $visit['Urban_Planned_Visit.External_Coordinating_PoC'] ?? '';
    $externalCoordinatingGoonjPoc = Contact::get(FALSE)
      ->addSelect('email.email', 'display_name', 'phone.phone_numeric')
      ->addJoin('Email AS email', 'LEFT')
      ->addJoin('Phone AS phone', 'LEFT')
      ->addWhere('id', '=', $externalCoordinatingPocId)
      ->execute()->single();

    $externalCoordinatingGoonjPocEmail = $externalCoordinatingGoonjPoc['email.email'];
    $externalCoordinatingGoonjPocName = $externalCoordinatingGoonjPoc['display_name'];

    $coordinatingGoonjPocId = $visit['Urban_Planned_Visit.Coordinating_Goonj_POC'] ?? '';
    $coordinatingGoonjPocPerson = Contact::get(FALSE)
      ->addSelect('display_name', 'phone.phone_numeric')
      ->addJoin('Phone AS phone', 'LEFT')
      ->addWhere('id', '=', $coordinatingGoonjPocId)
      ->execute()->single();

    $coordinatingGoonjPersonName = $coordinatingGoonjPocPerson['display_name'];
    $coordinatingGoonjPersonPhone = $coordinatingGoonjPocPerson['phone.phone_numeric'];

    $from = HelperService::getDefaultFromEmail();

    $mailParamsExternalPoc = [
      'subject' => 'Thank You for Visiting Goonj Center of Circularity!',
      'from' => $from,
      'toEmail' => $externalCoordinatingGoonjPocEmail,
      'replyTo' => $from,
      'html' => self::getExtCoordPocFeedbackEmailHtml($externalCoordinatingGoonjPocName, $coordinatingGoonjPersonName, $coordinatingGoonjPersonPhone, $visit['id']),
    ];

    $emailSendResultToExternalPoc = \CRM_Utils_Mail::send($mailParamsExternalPoc);

    if ($emailSendResultToExternalPoc) {
      EckEntity::update('Institution_Visit', FALSE)
        ->addValue('Visit_Feedback.Feedback_Email_Sent', 1)
        ->addWhere('id', '=', $visit['id'])
        ->execute();
    }

  }

  /**
   *
   */
  private static function getExtCoordPocFeedbackEmailHtml($externalCoordinatingGoonjPocName, $coordinatingGoonjPersonName, $coordinatingGoonjPersonPhone, $visitId) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $visitFeedbackFormUrl = $homeUrl . '/visit-feedback/#?Eck_Institution_Visit1=' . $visitId;

    $html = "
    <p>Dear $externalCoordinatingGoonjPocName,</p>

    <p>Thank you for visiting the <strong>Goonj Center of Circularity!</strong> We hope you gained valuable insights into how Goonj’s work is building an ecosystem of circularity, with dignity at its core. To help us improve this experience for future visitors, we’d appreciate it if you could fill out this short <strong>feedback form:</strong> <a href=\"$visitFeedbackFormUrl\">Feedback Form</a>.</p>

    <p>Meanwhile, you too can make a difference by engaging with Goonj in the following ways:</p>

    <ul>
        <li>Bring more people for a <strong>Learning Journey at GCoC</strong></li>
        <li>Volunteer for Goonj - <a href='https://goonj.org/volunteer/'>Goonj Volunteer Page</a></li>
        <li>Goonj it your underutilized materials at the nearest dropping center</li>
        <li>Organize a collection drive at your society/office/institute</li>
        <li>Fundraise by engaging in Goonj ki <strong>Gullak or Team 5000</strong> - <a href='https://goonj.org/donate'>Donate to Goonj</a></li>
    </ul>

    <p>We are here to support you in finding the best way to engage with Goonj’s mission and create a meaningful change.</p>

    <p>For any further assistance, feel free to reach out to $coordinatingGoonjPersonName on <strong>$coordinatingGoonjPersonPhone</strong>.</p>

    <p>Warm regards,</p>
    <p>Team Goonj..</p>
    ";

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
  public static function sendVisitOutcomeForm(string $op, string $objectName, $objectId, &$objectRef) {
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

      $visitOutcomeSent = EckEntity::get('Institution_Visit', TRUE)
        ->addSelect('Urban_Planned_Visit.Outcome_Email_Sent')
        ->addWhere('id', '=', $visitId)
        ->execute()->single();

      $isVisitOutcomeSent = $visitOutcomeSent['Urban_Planned_Visit.Outcome_Email_Sent'];

      if ($isVisitOutcomeSent !== NULL) {
        return;
      }

      $visitData = EckEntity::get('Institution_Visit', FALSE)
        ->addSelect('Urban_Planned_Visit.When_do_you_wish_to_visit_Goonj', 'Urban_Planned_Visit.What_time_do_you_wish_to_visit_', 'Urban_Planned_Visit.Number_of_people_accompanying_you')
        ->addWhere('id', '=', $visitId)
        ->execute()->single();

      $numberOfAttendees = $visitData['Urban_Planned_Visit.Number_of_people_accompanying_you'];

      $visitDate = $visitData['Urban_Planned_Visit.When_do_you_wish_to_visit_Goonj'];
      $visitTime = $visitData['Urban_Planned_Visit.What_time_do_you_wish_to_visit_'];

      $goonjCoordinatingPocId = $objectRef['Urban_Planned_Visit.Coordinating_Goonj_POC'] ?? '';

      $coordinatingGoonjPoc = Contact::get(FALSE)
        ->addSelect('email.email', 'display_name', 'phone.phone_numeric')
        ->addJoin('Email AS email', 'LEFT')
        ->addWhere('id', '=', $goonjCoordinatingPocId)
        ->execute()->single();

      $coordinatingGoonjPocEmail = $coordinatingGoonjPoc['email.email'];
      $coordinatingGoonjPocName = $coordinatingGoonjPoc['display_name'];

      $from = HelperService::getDefaultFromEmail();

      $goonjVisitGuideIds = $objectRef['Urban_Planned_Visit.Visit_Guide'] ?? '';

      if (!$goonjVisitGuideIds) {
        return;
      }

      $goonjVisitGuideIds = is_array($goonjVisitGuideIds) ? $goonjVisitGuideIds : [$goonjVisitGuideIds];

      $allEmailsSent = TRUE;

      foreach ($goonjVisitGuideIds as $goonjVisitGuideId) {
        $goonjVisitGuideData = Contact::get(FALSE)
          ->addSelect('email.email', 'display_name', 'phone.phone_numeric')
          ->addJoin('Email AS email', 'LEFT')
          ->addWhere('id', '=', $goonjVisitGuideId)
          ->execute()
          ->single();

        $goonjVisitGuideEmail = $goonjVisitGuideData['email.email'];
        $goonjVisitGuideName = $goonjVisitGuideData['display_name'];

        $mailParamsVisitGuide = [
          'subject' => 'Outcome Form for the Learning Journey on' . $visitDate,
          'from' => $from,
          'toEmail' => $goonjVisitGuideEmail,
          'replyTo' => $from,
          'html' => self::getOutcomeFormEmailHtml($coordinatingGoonjPocName, $visitDate, $visitTime, $goonjVisitGuideName, $goonjVisitGuideId, $visitId, $numberOfAttendees),
          'cc' => $coordinatingGoonjPocEmail,
        ];

        $emailSendResultToVisitGuide = \CRM_Utils_Mail::send($mailParamsVisitGuide);

        if (!$emailSendResultToVisitGuide) {
          $allEmailsSent = FALSE;
        }
      }

      if ($allEmailsSent) {
        EckEntity::update('Institution_Visit', FALSE)
          ->addValue('Urban_Planned_Visit.Outcome_Email_Sent', 1)
          ->addWhere('id', '=', $visitId)
          ->execute();
      }
    }

  }

  /**
   *
   */
  private static function getOutcomeFormEmailHtml($coordinatingGoonjPocName, $visitDate, $visitTime, $goonjVisitGuideName, $goonjVisitGuideId, $visitId, $numberOfAttendees) {
    $homeUrl = \CRM_Utils_System::baseCMSURL();
    $visitOutcomeFormUrl = $homeUrl . '/visit-outcome/#?Eck_Urban_Source_Outcome_Details1=' . $visitId . '&Urban_Visit_Outcome.Visit_Id=' . $visitId . '&Urban_Visit_Outcome.Filled_By=' . $goonjVisitGuideId . '&Urban_Visit_Outcome.Number_of_Attendees=' . $numberOfAttendees;

    $html = "
    <p>Dear $goonjVisitGuideName,</p>

    <p>Thank you for guiding our visitors on the Learning Journey on <strong>$visitDate</strong> at <strong>$visitTime</strong>. We hope it was a rewarding experience!</p>

    <p>To complete the process, kindly fill out this short <a href='$visitOutcomeFormUrl' target='_blank'>Outcome Form</a> for our records and documentation purposes.</p>

    <p>Warm regards,</p>
    <p>$coordinatingGoonjPocName<br>Team Goonj</p>
    ";

    return $html;
  }

}
