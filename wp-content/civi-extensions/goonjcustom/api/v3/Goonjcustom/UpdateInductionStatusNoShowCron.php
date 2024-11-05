<?php

use Civi\Api4\Activity;
use Civi\Api4\MessageTemplate;

/**
 * Goonjcustom.Induction Status Update to No Show API specification (optional)
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
 * Goonjcustom.update_induction_status_no_show_cron API implementation.
 *
 * This function checks for unscheduled induction activities older than 30 days 
 * and update induction status to No Show
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

    $followUpDays = 30;

    // Calculate the timestamp for 30 days ago from the current date
    $followUpTimestamp = strtotime("-$followUpDays days");

    $batchSize = 25;
    $offset = 0;

    try {
        // Fetch the follow up message template for the follow-up email
        $template = MessageTemplate::get(FALSE)
            ->addSelect('id', 'msg_subject')
            ->addWhere('msg_title', 'LIKE', 'Induction_slot_booking_follow_up_email%')
            ->execute()->single();

        if (!$template) {
            throw new \Exception('Follow-up email template not found.');
        }

        do {
            // Fetch email activities older than 30 days
            $followUpEmailActivities = Activity::get(TRUE)
              ->addSelect('source_contact_id', 'activity_date_time')
              ->addWhere('subject', '=', $template['msg_subject'])
              ->addWhere('activity_type_id:label', '=', 'Email')
              ->addWhere('created_date', '<', date('Y-m-d H:i:s', $followUpTimestamp))
              ->setLimit($batchSize)
              ->setOffset($offset)->execute();

            foreach ($followUpEmailActivities as $activity) {

                // Fetch the associated induction activity
                $inductionActivity = Activity::get(FALSE)
                    ->addSelect('id', 'source_contact_id', 'status_id:name')
                    ->addWhere('activity_type_id:name', '=', 'Induction')
                    ->addWhere('source_contact_id', '=', $activity['source_contact_id'])
                    ->execute()
                    ->single();

                if (!$inductionActivity) {
                    \Civi::log()->info('No induction activity found for source contact', [
                        'source_contact_id' => $activity['source_contact_id'],
                    ]);
                    continue;
                }

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
        } while (count($followUpEmailActivities) === $batchSize);

    } catch (\Exception $e) {
        // Log any errors encountered during the process
        \Civi::log()->error('Error in follow-up cron: ' . $e->getMessage());
        return civicrm_api3_create_error($e->getMessage());
    }

    \Civi::log()->info('Induction slot booking follow-up cron job completed successfully.');

    return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'update_induction_status_no_show_cron');
}
