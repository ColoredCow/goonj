<?php

/**
 * @file
 */

use Civi\Api4\Activity;
use Civi\Api4\MessageTemplate;


/**
 * Goonjcustom.induction_slot_booking_follow_up_cron API specification.
 * 
 * This function defines the API specification for the induction slot booking 
 * follow-up cron job. It currently has no parameters.
 *
 * @param array $spec
 *   The specification for API fields supported by this cron job.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_induction_slot_booking_follow_up_cron_spec(&$spec) {
    // No parameters are needed for the Goonjcustom cron.
}

/**
 * Goonjcustom.induction_slot_booking_follow_up_cron API implementation.
 *
 * This function checks for unscheduled induction activities and sends follow-up
 * emails to the respective contacts if they haven't already received one.
 *
 * @param array $params
 *   Parameters passed to the API call.
 *
 * @return array API result descriptor
 *
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 *
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_goonjcustom_induction_slot_booking_follow_up_cron($params) {
	$returnValues = [];
	\Civi::log()->info('Check for unscheduled induction activities');

	// Configurable number of days to check for scheduling
	$followUpDays = 7;
	$followUpTimestamp = strtotime("-$followUpDays days");

	try {
			// Retrieve unscheduled induction activities
			$unscheduledInductionActivities = Activity::get(FALSE)
					->addSelect('source_contact_id', 'created_date')
					->addWhere('activity_type_id:name', '=', 'Induction')
					->addWhere('status_id:name', '=', 'To be scheduled')
					->setLimit(25)
					->execute();

			foreach ($unscheduledInductionActivities as $activity) {
					// Check if the created date is older than 7 days
					if (strtotime($activity['created_date']) < $followUpTimestamp) {
							// Fetch the email template for follow-up.
							$template = MessageTemplate::get(FALSE)
									->addSelect('id', 'msg_subject')
									->addWhere('msg_title', 'LIKE', 'Induction_slot_booking_follow_up_email%')
									->setLimit(1)
									->execute()
									->single();

							// Check if an email has already been sent to avoid duplication.
							$emailActivities = Activity::get(FALSE)
									->addWhere('activity_type_id:name', '=', 'Email')
									->addWhere('subject', '=', $template['msg_subject'])
									->addWhere('source_contact_id', '=', $activity['source_contact_id'])
									->setLimit(1)
									->execute()->first();

							if (!$emailActivities) {
									// Prepare email parameters and send the email.
									$emailParams = [
											'contact_id' => $activity['source_contact_id'],
											'template_id' => $template['id'],
									];
									$emailResult = civicrm_api3('Email', 'send', $emailParams);
									\Civi::log()->info('Follow-up email sent', ['result' => $emailResult]);
							} else {
									\Civi::log()->info('Email already sent to contact', ['contact_id' => $activity['source_contact_id']]);
							}
					} else {
							\Civi::log()->info('No follow-up needed, activity is recent', ['contact_id' => $activity['source_contact_id'], 'created_date'=>$activity['created_date']]);
					}
			}

	} catch (Exception $e) {
			// Log any errors encountered during the process.
			\Civi::log()->error('Error in follow-up cron: ' . $e->getMessage());
			return civicrm_api3_create_error($e->getMessage());
	}

	return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'induction_slot_booking_follow_up_cron');
}
