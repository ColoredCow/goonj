<?php

use Civi\Api4\Activity;
use Civi\Api4\MessageTemplate;

/**
 * Goonjcustom.InductionSlotBookingFollowUp API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec
 *   Description of fields supported by this API call.
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_update_induction_status_no_show_cron_spec(&$spec) {
    // There are no parameters for the Goonjcustom cron.
}

/**
 * Goonjcustom.induction_slot_booking_follow_up_cron API implementation.
 *
 * This function checks for unscheduled induction activities older than 7 days 
 * and sends follow-up emails to the respective contacts if they haven't already 
 * received one within a configurable number of days.
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
function civicrm_api3_goonjcustom_update_induction_status_no_show_cron($params) {
    $returnValues = [];
    \Civi::log()->info('Starting the induction slot booking follow-up cron job.');

    // Configurable number of days to check for follow-up emails
    $followUpDays = 30; // You can set this to any number of days as needed
    $followUpTimestamp = strtotime("-$followUpDays days");

    // Set batch size to process 25 records at a time
    $batchSize = 25;
    $offset = 0;

    try {
        // Fetch the message template for the follow-up email
        $template = MessageTemplate::get(FALSE)
            ->addSelect('id', 'msg_subject')
            ->addWhere('msg_title', 'LIKE', 'Induction_slot_booking_follow_up_email%')
            ->setLimit(1)
            ->execute()
            ->single();

        if (!$template) {
            throw new \Exception('Follow-up email template not found.');
        }

        \Civi::log()->info('Email template retrieved successfully', ['template' => $template]);

        // Loop through activities in batches
        do {
            // Fetch email activities in the current batch that were sent within the specified follow-up period
            $followUpEmailActivities = Activity::get(TRUE)
              ->addSelect('source_contact_id', 'activity_date_time')
              ->addWhere('subject', '=', $template['msg_subject'])
              ->addWhere('activity_type_id:label', '=', 'Email')
              ->addWhere('created_date', '<', date('Y-m-d H:i:s', $followUpTimestamp))
              ->setLimit($batchSize)
              ->setOffset($offset)
              ->execute();

            \Civi::log()->info('Batch of follow-up email activities retrieved', [
                'followUpEmailActivities' => $followUpEmailActivities,
            ]);

            // Process each activity in the batch
            foreach ($followUpEmailActivities as $activity) {
                \Civi::log()->info('Processing follow-up activity', ['activity' => $activity]);

                // Fetch the associated induction activity
                $inductionActivity = Activity::get(FALSE)
                    ->addSelect('id', 'source_contact_id', 'status_id:name')
                    ->addWhere('activity_type_id:name', '=', 'Induction')
                    ->addWhere('source_contact_id', '=', $activity['source_contact_id'])
                    ->execute()
                    ->single();

                if (!$inductionActivity) {
                    \Civi::log()->warning('No induction activity found for source contact', [
                        'source_contact_id' => $activity['source_contact_id'],
                    ]);
                    continue;
                }

                \Civi::log()->info('Induction activity retrieved', ['inductionActivity' => $inductionActivity]);

                // Check if the status is 'Completed' or 'Scheduled'
                if (in_array($inductionActivity['status_id:name'], ['Completed', 'Scheduled'])) {
                    \Civi::log()->info('Induction activity is already completed or scheduled, skipping', [
                        'inductionActivityId' => $inductionActivity['id'],
                        'status' => $inductionActivity['status_id:name']
                    ]);
                    continue;
                }

                // // Update the induction status to 'No_show'
                $updateResult = Activity::update(FALSE)
                    ->addValue('status_id:name', 'No_show')
                    ->addWhere('id', '=', $inductionActivity['id'])
                    ->execute();

                \Civi::log()->info('Induction activity status updated to No_show', [
                    'inductionActivityId' => $inductionActivity['id'],
                    'updateResult' => $updateResult
                ]);
            }

            // Increment the offset by the batch size
            $offset += $batchSize;
        } while (count($followUpEmailActivities) === $batchSize); // Continue until there are no more records

    } catch (\Exception $e) {
        // Log any errors encountered during the process
        \Civi::log()->error('Error in follow-up cron: ' . $e->getMessage());
        return civicrm_api3_create_error($e->getMessage());
    }

    \Civi::log()->info('Induction slot booking follow-up cron job completed successfully.');

    return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'update_induction_status_no_show_cron');
}
