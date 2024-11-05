<?php

use Civi\Api4\Activity;
use Civi\Api4\MessageTemplate;

/**
 * Goonjcustom.InductionSlotBookingFollowUp API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_induction_slot_booking_follow_up_cron_spec(&$spec) {
  // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.induction_slot_booking_follow_up_cron API implementation.
 *
 * This function checks for unscheduled induction activities older than 7 days 
 * and sends follow-up emails to the respective contacts if they haven't already 
 * received one.
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

    $batchSize = 25;
    $offset = 0;

    try {
        // Retrieve the email template for follow-up.
        $template = MessageTemplate::get(FALSE)
            ->addSelect('id', 'msg_subject')
            ->addWhere('msg_title', 'LIKE', 'Induction_slot_booking_follow_up_email%')
            ->setLimit(1)
            ->execute()
            ->single();

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
                // Check if an followup email has already been sent to avoid duplication.
                $emailActivities = Activity::get(FALSE)
                    ->addWhere('activity_type_id:name', '=', 'Email')
                    ->addWhere('subject', '=', $template['msg_subject'])
                    ->addWhere('source_contact_id', '=', $activity['source_contact_id'])
                    ->setLimit(1)
                    ->execute()->first();

                if (!$emailActivities) {
                    $emailParams = [
                        'contact_id' => $activity['source_contact_id'],
                        'template_id' => $template['id'],
                    ];
                    $emailResult = civicrm_api3('Email', 'send', $emailParams);
                    \Civi::log()->info('Follow-up email sent', ['result' => $emailResult]);
                }

            }

            // Move to the next batch by increasing the offset
            $offset += $batchSize;

        } while (count($unscheduledInductionActivities) === $batchSize);

    } catch (Exception $e) {
        // Log any errors encountered during the process.
        \Civi::log()->error('Error in follow-up cron: ' . $e->getMessage());
        return civicrm_api3_create_error($e->getMessage());
    }

    return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'induction_slot_booking_follow_up_cron');
}
