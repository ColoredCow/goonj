<?php
/**
 * Class for CiviRules adding an activity to the system
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_CivirulesActions_Activity_Add extends CRM_CivirulesActions_Generic_Api {

  // Store a list of api params passed to action
  protected $apiParams = [];

  // Store the triggering activity id
  protected $activityId;

  // Store a list of new assigned contacts
  protected $asignedContacts = [];

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportActionParameters() {
    $action_params = parent::exportActionParameters();
    try {
      $action_params['status_id'] = civicrm_api3('OptionValue', 'getvalue', [
        'return' => 'name',
        'value' => $action_params['status_id'],
        'option_group_id' => 'activity_status',
      ]);
    } catch (CRM_Core_Exception $e) {
    }
    try {
      $action_params['activity_type_id'] = civicrm_api3('OptionValue', 'getvalue', [
        'return' => 'name',
        'value' => $action_params['activity_type_id'],
        'option_group_id' => 'activity_type',
      ]);
    } catch (CRM_Core_Exception $e) {
    }
    return $action_params;
  }

  /**
   * Returns condition data as an array and ready for import.
   * E.g. replace name for ids.
   *
   * @return string
   */
  public function importActionParameters($action_params = NULL) {
    try {
      $action_params['status_id'] = civicrm_api3('OptionValue', 'getvalue', [
        'return' => 'value',
        'name' => $action_params['status_id'],
        'option_group_id' => 'activity_status',
      ]);
    } catch (CRM_Core_Exception $e) {
    }
    try {
      $action_params['activity_type_id'] = civicrm_api3('OptionValue', 'getvalue', [
        'return' => 'value',
        'name' => $action_params['activity_type_id'],
        'option_group_id' => 'activity_type',
      ]);
    } catch (CRM_Core_Exception $e) {
    }
    return parent::importActionParameters($action_params);
  }


  /**
   * Returns an array with parameters used for processing an action
   *
   * @param array $params
   * @param object CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return array $params
   * @access protected
   */
  protected function alterApiParameters($params, CRM_Civirules_TriggerData_TriggerData $triggerData) {

    // Store params
    $this->apiParams = $params;

    $action_params = $this->getActionParameters();

    //this function could be overridden in subclasses to alter parameters to meet certain criteraia
    $params['target_contact_id'] = $triggerData->getContactId();
    $params['activity_type_id'] = $action_params['activity_type_id'];
    $params['status_id'] = $action_params['status_id'];
    $params['subject'] = $action_params['subject'];
    $params['duration'] = $action_params['duration'];
    $params['details'] = $action_params['details'];

    if (!empty($action_params['assignee_contact_id'])) {
      $assignee = array();
      if (is_array($action_params['assignee_contact_id'])) {
        foreach($action_params['assignee_contact_id'] as $contact_id) {
          if($contact_id) {
            $assignee[] = $contact_id;
          }
        }
      } else {
        $assignee[] = $action_params['assignee_contact_id'];
      }
      if (count($assignee)) {
        $params['assignee_contact_id'] = $action_params['assignee_contact_id'];
      } else {
        $params['assignee_contact_id'] = [];
      }

      // Store the assigned contacts to send a notification email
      if (!empty($params['assignee_contact_id'])) {
        $this->asignedContacts = (array)$params['assignee_contact_id'];
      }
    }

    // #188 If selecting a relationship contact assignee, append to assignee array
    if (!empty($params['relationship_contact_assignee'])) {
      $entityData = $triggerData->getEntityData('Relationship');
      $params['assignee_contact_id'][] = $this->asignedContacts[] = $entityData[$params['relationship_contact_assignee']];
    }

    // issue #127: no activity date time if set to null
    if ($action_params['activity_date_time'] == 'null') {
      unset($params['activity_date_time']);
    } else {
      if (!empty($action_params['activity_date_time'])) {
        $delayClass = unserialize($action_params['activity_date_time']);
        if ($delayClass instanceof CRM_Civirules_Delay_Delay) {
          $activityDate = $delayClass->delayTo(new DateTime(), $triggerData);
          if ($activityDate instanceof DateTime) {
            $params['activity_date_time'] = $activityDate->format('Ymd His');
          }
        }
      }
    }

    // #188 If selecting a specific contact via relationship, pass necessary details to API params
    if (!empty($params['relationship_contact']) && $params['relationship_contact'] != 'both') {
      $entityData = $triggerData->getEntityData('Relationship');
      $params['relationship_contact_id'] = $entityData[$params['relationship_contact']];
    }

    // Issue #152: when a rule is trigger from a public page then source contact id
    // is empty and that in turn creates a fatal error.
    // So the solution is to check whether we have a logged in user and if not use
    // the contact from the trigger as the source contact.
    if (CRM_Core_Session::getLoggedInContactID()) {
      $params['source_contact_id'] = CRM_Core_Session::getLoggedInContactID();
    } else {
      $params['source_contact_id'] = $triggerData->getContactId();
    }
    return $params;
  }

  /**
   * Process the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    // Process the action, may throw Exceptions
    parent::processAction($triggerData);

    // Check if we need to send any emails
    if (!empty($this->apiParams['send_email']) && !empty($this->activityId) && !empty($this->asignedContacts)) {
      foreach (array_unique($this->asignedContacts) as $contactId) {

        $contact = civicrm_api3('Contact', 'getsingle', ['id' => $contactId]);

        // check contact has an email
        if (empty($contact['email']))
          continue;

        CRM_Case_BAO_Case::sendActivityCopy(NULL, $this->activityId, [$contact['email'] => $contact], NULL, NULL);
      }
    }
  }

  /**
   * Executes the action
   * Overrided to save new activity id
   *
   * This method could be overridden if needed
   *
   * @param $entity
   * @param $action
   * @param $parameters
   * @access protected
   * @throws Exception on api error
   */
  protected function executeApiAction($entity, $action, $parameters) {
    // #188 relationship contact filter; return if target does not match
    if (!empty($parameters['relationship_contact']) &&
      $parameters['relationship_contact'] != 'both' &&
      $parameters['target_contact_id'] != $parameters['relationship_contact_id']
    ) {
      return;
    }

    try {
      $activity = civicrm_api3($entity, $action, $parameters);
      $this->activityId = $activity['id'];
    } catch (Exception $e) {
      $formattedParams = '';
      foreach($parameters as $key => $param) {
        if (strlen($formattedParams)) {
          $formattedParams .= ', ';
        }
        $formattedParams .= "{$key}=\"$param\"";
      }
      $message = "Civirules api action exception: {$e->getMessage()}. API call: {$entity}.{$action} with params: {$formattedParams}";
      \Civi::log()->error($message);
      throw new Exception($message);
    }
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a action
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleActionId
   * @return bool|string
   * @access public
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/activity', $ruleActionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   * @throws \CRM_Core_Exception
   */
  public function userFriendlyConditionParams() {
    $return = '';
    $params = $this->getActionParameters();
    if (!empty($params['activity_type_id'])) {
      $type = civicrm_api3('OptionValue', 'getvalue', array(
        'return' => 'label',
        'option_group_id' => 'activity_type',
        'value' => $params['activity_type_id']));
      $return .= ts("Type: %1", array(1 => $type));
    }
    if (!empty($params['status_id'])) {
      $status = civicrm_api3('OptionValue', 'getvalue', array(
        'return' => 'label',
        'option_group_id' => 'activity_status',
        'value' => $params['status_id']));
      $return .= "<br>";
      $return .= ts("Status: %1", array(1 => $status));
    }
    $subject = $params['subject'];
    if (!empty($subject)) {
      $return .= "<br>";
      $return .= ts("Subject: %1", array(1 => $subject));
    }
    // #188 relationship based target selection
    $relContactMap = [
      'both' => ts('Both Contacts'),
      'contact_id_a' => ts('Contact A'),
      'contact_id_b' => ts('Contact B'),
    ];
    if (!empty($params['relationship_contact'])) {
      $return .= "<br>";
      $return .= ts("Relationship Contact: %1", [1 => $relContactMap[$params['relationship_contact']]]);
    }
    if (!empty($params['assignee_contact_id']) && !empty($params['assignee_contact_id'][0])) {
      if (!is_array($params['assignee_contact_id'])) {
        $params['assignee_contact_id'] = array($params['assignee_contact_id']);
      }
      $assignees = '';
      foreach($params['assignee_contact_id'] as $cid) {
        try {
          $assignee = civicrm_api3('Contact', 'getvalue', array('return' => 'display_name', 'id' => $cid));
          if ($assignee) {
            if (strlen($assignees)) {
              $assignees .= ', ';
            }
            $assignees .= $assignee;
          }
        } catch (Exception $e) {
          //do nothing
        }
      }

      $return .= '<br>';
      $return .= ts("Assignee(s): %1", array(1 => $assignees));
    }

    // #188 assignee by relationship
    if (!empty($params['relationship_contact_assignee'])) {
      $return .= "<br>";
      $return .= ts("Assignee (by relationship): %1", [1 => $relContactMap[$params['relationship_contact_assignee']]]);
    }

    if (!empty($params['activity_date_time'])) {
      if ($params['activity_date_time'] != 'null') {
        $delayClass = unserialize(($params['activity_date_time']));
        if ($delayClass instanceof CRM_Civirules_Delay_Delay) {
          $return .= '<br>'.ts('Activity date time').': '.$delayClass->getDelayExplanation();
        }
      }
    }

    if (!empty($params['send_email'])) {
      $return .= '<br>'.ts('Send notification');
    }

    return $return;
  }

  /**
   * Method to set the api entity
   *
   * @return string
   * @access protected
   */
  protected function getApiEntity() {
    return 'Activity';
  }

  /**
   * Method to set the api action
   *
   * @return string
   * @access protected
   */
  protected function getApiAction() {
    return 'create';
  }

}
