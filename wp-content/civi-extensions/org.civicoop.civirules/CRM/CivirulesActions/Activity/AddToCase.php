<?php
/**
 * Class for CiviRules adding an activity to the system
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use Civi\Api4\ActivityContact;
use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Activity_AddToCase extends CRM_CivirulesActions_Activity_Add {

  /**
   * Returns an array with parameters used for processing an action
   *
   * @param array $params
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return array
   */
  protected function alterApiParameters($params, CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $params = parent::alterApiParameters($params, $triggerData);

    if (!empty($triggerData->getEntityData('Case')['id'])) {
      // If we have an existing case, don't look it up by other fields.
      $params['case_id'] = $triggerData->getEntityData('Case')['id'];
      unset($params['case_type_id'], $params['case_status_id']);
    }
    if (!empty($triggerData->getEntityData('Activity')['id'])) {
      // If we have an existing activity ID and assignee_contact_id is NOT set
      //   use the assignee, source and target from the existing activity.
      if (empty($params['assignee_contact_id']) || empty(reset($params['assignee_contact_id']))) {
        $activityContacts = ActivityContact::get(FALSE)
          ->addSelect('record_type_id:name', 'contact_id')
          ->addWhere('activity_id', '=', $triggerData->getEntityData('Activity')['id'])
          ->execute();
        unset($params['assignee_contact_id'], $params['source_contact_id'], $params['target_contact_id']);
        foreach ($activityContacts as $activityContact) {
          switch ($activityContact['record_type_id:name']) {
            case 'Activity Source':
              // Can be only 1.
              $params['source_contact_id'] = $activityContact['contact_id'];
              break;

            case 'Activity Targets':
              $params['target_contact_id'][] = $activityContact['contact_id'];
              break;

            case 'Activity Assignees':
              $params['assignee_contact_id'][] = $activityContact['contact_id'];
              break;
          }
        }
      }

    }
    return $params;
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
    $action_params = $this->getActionParameters();
    if (!empty($parameters['case_id'])) {
      $caseParams = ['id' =>$parameters['case_id']];
    }
    else {
      $caseParams['contact_id'] = $parameters['target_contact_id'];
      $caseParams['case_type_id'] = $action_params['case_type_id'];
      // ensure deleted cases are not selected
      $caseParams['is_deleted'] = FALSE;
      if (!empty($action_params['case_status_id'])) {
        $caseParams['status_id'] = $action_params['case_status_id'];
      }
    }

    try {
      $case = civicrm_api3('Case', 'getsingle', $caseParams);
    } catch (\CRM_Core_Exception $ex) {
      $formattedCaseParams = '';
      foreach($caseParams as $key => $param) {
        if (strlen($formattedCaseParams)) {
          $formattedCaseParams .= ', ';
        }
        $formattedCaseParams .= "{$key}=\"$param\"";
      }
      $message = "Civirules could not find case: {$ex->getMessage()}. API call: Case.getsingle with params: {$formattedCaseParams}";
      \Civi::log()->error($message);
      throw new Exception($message);
    }
    $parameters['case_id'] = $case['id'];

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
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportActionParameters() {
    $action_params = parent::exportActionParameters();
    try {
      $action_params['case_status_id'] = civicrm_api3('OptionValue', 'getvalue', [
        'return' => 'name',
        'value' => $action_params['case_status_id'],
        'option_group_id' => 'case_status',
      ]);
    } catch (CRM_Core_Exception $e) {
    }
    try {
      $action_params['case_type_id'] = civicrm_api3('OptionValue', 'getvalue', [
        'return' => 'name',
        'value' => $action_params['case_type_id'],
        'option_group_id' => 'case_type',
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
      $action_params['case_status_id'] = civicrm_api3('OptionValue', 'getvalue', [
        'return' => 'value',
        'name' => $action_params['case_status_id'],
        'option_group_id' => 'case_status',
      ]);
    } catch (CRM_Core_Exception $e) {
    }
    try {
      $action_params['case_type_id'] = civicrm_api3('OptionValue', 'getvalue', [
        'return' => 'value',
        'name' => $action_params['case_type_id'],
        'option_group_id' => 'case_type',
      ]);
    } catch (CRM_Core_Exception $e) {
    }
    return parent::importActionParameters($action_params);
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/activity/add_to_case', $ruleActionId);
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
      $return .= E::ts("Type: %1", array(1 => $type));
    }
    if (!empty($params['status_id'])) {
      $status = civicrm_api3('OptionValue', 'getvalue', array(
        'return' => 'label',
        'option_group_id' => 'activity_status',
        'value' => $params['status_id']));
      $return .= "<br>";
      $return .= E::ts("Status: %1", array(1 => $status));
    }
    $subject = $params['subject'];
    if (!empty($subject)) {
      $return .= "<br>";
      $return .= E::ts("Subject: %1", array(1 => $subject));
    }
    if (!empty($params['assignee_contact_id'])) {
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
      $return .= E::ts("Assignee(s): %1", array(1 => $assignees));

    }

    if (!empty($params['activity_date_time'])) {
      if ($params['activity_date_time'] != 'null') {
        $delayClass = unserialize(($params['activity_date_time']));
        if ($delayClass instanceof CRM_Civirules_Delay_Delay) {
          $return .= '<br>'.E::ts('Activity date time').': '.$delayClass->getDelayExplanation();
        }
      }
    }
    if (!empty($params['case_type_id'])) {
      $case_type = civicrm_api3('CaseType', 'getvalue', [
        'id' => $params['case_type_id'],
        'return' => 'title',
        'options' => ['limit' => 1]
      ]);
      $case_statuses = CRM_Core_OptionGroup::values('case_status');
      if (!empty($params['case_status_id'])) {
        $case_status = $case_statuses[$params['case_status_id']];
        $return .= '<br>' . E::ts('Add to case with type %1 and status %2', [
            1 => $case_type,
            2 => $case_status
          ]);
      }
      else {
        $return .= '<br>' . E::ts('Add to case with type %1', [1 => $case_type]);
      }
    }
    else {
      $return .= '<br>' . E::ts('Add to existing case');
    }

    if (!empty($params['send_email'])) {
      $return .= '<br>'.E::ts('Send notification');
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

  /**
   * Get various types of help text for the action:
   *   - actionDescription: When choosing from a list of actions, explains what the action does.
   *   - actionDescriptionWithParams: When a action has been configured for a rule provides a
   *       user friendly description of the action and params (see $this->userFriendlyConditionParams())
   *   - actionParamsHelp (default): If the action has configurable params, show this help text when configuring
   * @param string $context
   *
   * @return string
   */
  public function getHelpText(string $context): string {
    // Child classes should override this function

    switch ($context) {
      case 'actionDescriptionWithParams':
        return $this->userFriendlyConditionParams();

      case 'actionDescription':
        return E::ts('Create an activity on a case');

      case 'actionParamsHelp':
        return E::ts('Create an activity on a case') . '<br>'
          . E::ts('If the rule trigger includes a case ID that will be used. Otherwise the case will be identified based on contact ID, case type and case status.')
          . '<br>' . E::ts('If you don\'t set assignee contact ID the contacts from the existing activity will be used on the new activity');
    }

    return $helpText ?? '';
  }

}
