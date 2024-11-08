<?php

namespace Civi;

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\CustomField;
use Civi\Api4\MessageTemplate;
use Civi\Api4\Relationship;
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
    $targetContactId = ($sourceContactId === $contactId) ? $contactId : $contactId;

    $placeholderActivityDate = self::getPlaceholderActivityDate();

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
    self::createInduction(self::$volunteerId, $stateId);
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
  private static function findOfficeForState($stateId) {
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
  private static function findCoordinatorForOffice($officeId) {
    $coordinators = Relationship::get(FALSE)
      ->addWhere('contact_id_b', '=', $officeId)
      ->addWhere('relationship_type_id:name', '=', self::RELATIONSHIP_TYPE_NAME)
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

    if (self::isEmailAlreadySent($volunteerId)) {
      return FALSE;
    }

    // Retrieve the email template.
    $template = MessageTemplate::get(FALSE)
      ->addWhere('msg_title', 'LIKE', 'New_Volunteer_Registration%')
      ->setLimit(1)
      ->execute()->single();

    if (!$template) {
      return FALSE;
    }

    // Prepare email parameters.
    $emailParams = [
      'contact_id' => $volunteerId,
      'template_id' => $template['id'],
      'cc' => self::$volunteerInductionAssigneeEmail,
    ];

    self::queueInductionEmail($emailParams);
    return TRUE;
  }

  /**
   * Queue the induction email to be processed later.
   */
  private static function queueInductionEmail($params) {
    try {
      $queue = \Civi::queue(\CRM_Goonjcustom_Engine::QUEUE_NAME, [
        'type' => 'Sql',
        'error' => 'abort',
        'runner' => 'task',
      ]);

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

    self::sendInductionEmail(self::$volunteerId);
  }

  /**
   * Handles sending induction email to an individual.
   */
  public static function sendInductionEmailForTransitionedVolunteer(string $op, string $objectName, int $objectId, &$objectRef) {
    if ($op !== 'edit' || $objectName !== 'Individual' || (int) self::$transitionedVolunteerId !== (int) $objectRef->id) {
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

    $volunteerEmailActivity = Activity::get(FALSE)
      ->addWhere('activity_type_id:name', '=', 'Email')
      ->addWhere('subject', 'LIKE', '%Volunteering with Goonj%')
      ->addWhere('target_contact_id', '=', $contactId)
      ->setLimit(1)
      ->execute();

    return $volunteerEmailActivity->count() > 0;
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

    // Check if "Volunteer" is present in the contact_sub_type array.
    if (!in_array('Volunteer', $newSubtypes)) {
      \Civi::log()->info('Volunteer not found in subtypes, returning.');
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

    self::createInduction(self::$transitionedVolunteerId, $stateId);
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
      // Retrieve a batch of unscheduled induction activities older than 7 days
      $unscheduledInductionActivities = Activity::get(FALSE)
        ->addSelect('id', 'source_contact_id', 'created_date')
        ->addWhere('activity_type_id:name', '=', 'Induction')
        ->addWhere('status_id:name', '=', 'To be scheduled')
        ->addWhere('created_date', '<', date('Y-m-d H:i:s', $followUpTimestamp))
        ->setLimit($batchSize)
        ->setOffset($offset)
        ->execute();

      // Process each activity in the batch
      foreach ($unscheduledInductionActivities as $activity) {
        // Check if a follow-up email has already been sent to avoid duplication.
        $emailActivities = Activity::get(FALSE)
          ->addWhere('activity_type_id:name', '=', 'Email')
          ->addWhere('subject', '=', $template['msg_subject'])
          ->addWhere('source_contact_id', '=', $activity['source_contact_id'])
          ->execute();

        $emailActivity = $emailActivities->first();
  
        if (!$emailActivity) {
          $emailParams = [
            'contact_id' => $activity['source_contact_id'],
            'template_id' => $template['id'],
          ];
          civicrm_api3('Email', 'send', $emailParams);
        }
      }
  
      // Move to the next batch by increasing the offset
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
			// Fetch the follow-up message template
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
				->execute()->column('source_contact_id');

			do {
				// Fetch email activities older than 30 days
				$followUpEmailActivities = Activity::get(FALSE)
					->addSelect('source_contact_id', 'activity_date_time')
					->addWhere('subject', '=', $template['msg_subject'])
					->addWhere('activity_type_id:name', '=', 'Email')
					->addWhere('created_date', '<', date('Y-m-d H:i:s', $followUpTimestamp))
					->addWhere('source_contact_id', 'IN',$unscheduledInductionContactIds )
					->setLimit($batchSize)
					->setOffset($offset)->execute();

				foreach ($followUpEmailActivities as $activity) {
					// Fetch the associated induction activity
					$inductionActivities = Activity::get(FALSE)
						->addSelect('id', 'source_contact_id', 'status_id:name')
						->addWhere('activity_type_id:name', '=', 'Induction')
						->addWhere('source_contact_id', '=', $activity['source_contact_id'])
						->addWhere('status_id:name', '=', 'To be scheduled')
						->execute();

          $inductionActivity = $inductionActivities->first();

					if (!$inductionActivity) {
						\Civi::log()->info('No induction activity found for source contact', [
							'source_contact_id' => $activity['source_contact_id'],
						]);
						continue;
					}

					// Update the induction status to 'No_show'
					$updateResult = Activity::update(FALSE)
						->addValue('status_id:name', 'No_show')
						->addWhere('id', '=', $inductionActivity['id'])
						->execute();
				}

				// Increment the offset by the batch size
				$offset += $batchSize;
			} while (count($followUpEmailActivities) === $batchSize);

		} catch (\Exception $e) {
			\Civi::log()->error('Error in updating induction status: ' . $e->getMessage());
			throw $e;
		}
	}
}
