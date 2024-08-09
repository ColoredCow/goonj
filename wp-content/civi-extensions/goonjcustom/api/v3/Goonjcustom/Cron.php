<?php

/**
 * Civirules.Cron API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @return void
 */
function _civicrm_api3_goonjcustom_cron_spec(&$spec) {
  //there are no parameters for the civirules cron
}

/**
 * Civirules.Cron API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_goonjcustom_cron($params) {
  $returnValues = [];

  try {
    if (function_exists('civicrm_initialize')) {
        civicrm_initialize();
    }

    // $currentDate = date('Y-m-d');
    $currentDate = '2024-08-30';

    $contacts = \Civi\Api4\Contact::get(TRUE)
        ->addSelect('id', 'display_name', 'Volunteer_Induction_Summary.Induction_status', 'Volunteer_Induction_Summary.Induction_assignee', 'Volunteer_Induction_Summary.Induction_date')
        ->addWhere('Volunteer_Induction_Summary.Induction_status', '=', 'Scheduled')
        ->addWhere('Volunteer_Induction_Summary.Induction_date', 'LIKE', '%' . $currentDate . '%')
        ->setLimit(25)
        ->execute();
    $assignees_data = [];

    // Collect display names of the assignees_data
    foreach ($contacts as $contact) {
        if (isset($contact['Volunteer_Induction_Summary.Induction_assignee']) && !empty($contact['Volunteer_Induction_Summary.Induction_assignee'])) {
            $assignees_data[] = [
                'volunteer_id' => $contact['id'],
                'volunteer_display_name' => $contact['display_name'],
                'volunteer_induction_status' => $contact['Volunteer_Induction_Summary.Induction_status'],
                'assignee_display_name' => $contact['Volunteer_Induction_Summary.Induction_assignee'],
                'volunteer_induction_date' => $contact['Volunteer_Induction_Summary.Induction_date'],
            ];
        }
    }

    // Fetch the IDs for each assignee and update the assignees_data array
    $assigneeIds = \Civi\Api4\Contact::get(TRUE)
        ->addSelect('id', 'display_name')
        ->addWhere('display_name', 'IN', array_column($assignees_data, 'assignee_display_name'))
        ->execute();

    foreach ($assignees_data as &$assignee) {
        foreach ($assigneeIds as $idInfo) {
            if ($assignee['assignee_display_name'] == $idInfo['display_name']) {
                $assignee['assignee_id'] = $idInfo['id'];
                break;
            }
        }
    }

    // Extract the IDs from the assignees_data to fetch emails
    $ids = array_column($assignees_data, 'assignee_id');

    // Fetch the emails associated with the assignee IDs
    $assigneeEmails = \Civi\Api4\Email::get(TRUE)
        ->addSelect('contact_id', 'email')
        ->addWhere('contact_id', 'IN', $ids)
        ->execute();

    // Append emails to the corresponding assignee in the assignees_data array
    foreach ($assignees_data as &$assignee) {
        foreach ($assigneeEmails as $emailInfo) {
            if ($assignee['assignee_id'] == $emailInfo['contact_id']) {
                $assignee['assignee_email'] = $emailInfo['email'];
                break;
            }
        }
    }

    // foreach ($assignees_data as $assignee) {
    //     if (isset($assignee['assignee_email'])) {
    //         $params = [
    //             'contact_id' => $assignee['assignee_id'],
    //             'template_id' => 76, // Ensure this template has the placeholders
    //             'from_name' => "Goonj",
    //             'from_email' => "nishant.kumar@coloredcow.in",
    //             'merge_vars' => [
    //                 [
    //                     'name' => 'volunteer_display_name',
    //                     'value' => $assignee['volunteer_display_name'],
    //                 ],
    //                 [
    //                     'name' => 'volunteer_induction_date',
    //                     'value' => $assignee['volunteer_induction_date'],
    //                 ],
    //             ],
    //             'create_activity' => TRUE,
    //             'activity_details' => 'text', // Or 'html' depending on your needs
    //         ];
    
    //         try {
    //             civicrm_api3('Email', 'send', $params);
    //         } catch (CiviCRM_API3_Exception $e) {
    //             error_log('Goonj Cron Job: Error sending email to ' . $assignee['assignee_display_name'] . ' - ' . $e->getMessage());
    //         }
    //     }
    // }
    
    echo "Assignees_dataEmails:\n";
    error_log($assignees_data);

    error_log('Goonj Cron Job: Job completed successfully');
} catch (CiviCRM_API3_Exception $e) {
    error_log('Goonj Cron Job: API error - ' . $e->getMessage());
}
    error_log('check');
  return civicrm_api3_create_success($returnValues, $params, 'Goonjcustom', 'cron');

}

