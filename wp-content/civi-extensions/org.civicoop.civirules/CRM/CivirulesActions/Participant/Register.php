<?php
/**
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_CivirulesActions_Participant_Register extends CRM_CivirulesActions_Generic_Api {

  /**
   * Method to get the api entity to process in this CiviRule action
   *
   * @access protected
   * @abstract
   */
  protected function getApiEntity() {
    return 'Participant';
  }

  /**
   * Method to get the api action to process in this CiviRule action
   *
   * @access protected
   * @abstract
   */
  protected function getApiAction() {
    return 'Create';
  }

  /**
   * Returns an array with parameters used for processing an action
   *
   * @param array $parameters
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return array
   * @access protected
   */
  protected function alterApiParameters($parameters, CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $contactId = $triggerData->getContactId();
    if ($contactId) {
      $parameters['contact_id'] = $contactId;
    }
    return $parameters;
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/register', $ruleActionId);
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportActionParameters() {
    $action_params = parent::exportActionParameters();
    if (!empty($action_params['participant_role_id'])) {
      try {
        $action_params['participant_role_id'] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'name',
          'value' => $action_params['participant_role_id'],
          'option_group_id' => "participant_role",
        ]);
      } catch (CRM_Core_Exception $e) {
      }
    }
    if (!empty($action_params['participant_status_id'])) {
      try {
        $action_params['participant_status_id'] = civicrm_api3('ParticipantStatusType', 'getvalue', [
          'return' => 'name',
          'id' => $action_params['participant_status_id'],
        ]);
      } catch (CRM_Core_Exception $e) {
      }
    }
    if (!empty($action_params['campaign_id'])) {
      try {
        $action_params['campaign_id'] = civicrm_api3('Campaign', 'getvalue', [
          'return' => 'name',
          'id' => $action_params['campaign_id'],
        ]);
      } catch (CRM_Core_Exception $e) {
      }
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
    if (!empty($action_params['participant_role_id'])) {
      try {
        $action_params['participant_role_id'] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'value',
          'name' => $action_params['participant_role_id'],
          'option_group_id' => "participant_role",
        ]);
      } catch (CRM_Core_Exception $e) {
      }
    }
    if (!empty($action_params['participant_status_id'])) {
      try {
        $action_params['participant_status_id'] = civicrm_api3('ParticipantStatusType', 'getvalue', [
          'return' => 'id',
          'name' => $action_params['participant_status_id'],
        ]);
      } catch (CRM_Core_Exception $e) {
      }
    }
    if (!empty($action_params['campaign_id'])) {
      try {
        $action_params['campaign_id'] = civicrm_api3('Campaign', 'getvalue', [
          'return' => 'id',
          'name' => $action_params['campaign_id'],
        ]);
      } catch (CRM_Core_Exception $e) {
      }
    }
    return parent::importActionParameters($action_params);
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
    $friendlyTxt = "";
    $actionParameters = $this->getActionParameters();
    if (!empty($actionParameters['event_id'])) {
      $friendlyTxt = "Register Participant for Event: " . CRM_Civirules_Utils::getEventTitle((int) $actionParameters['event_id']);
    }
    $roleOptionGroupId = CRM_Civirules_Utils::getOptionGroupIdWithName('participant_role');
    if ($roleOptionGroupId && !empty($actionParameters['participant_role_id'])) {
      $friendlyTxt .= " with Role: " . CRM_Civirules_Utils::getOptionLabelWithValue($roleOptionGroupId, $actionParameters['participant_role_id']) . ", ";
    }
    if (!empty($actionParameters['participant_status_id'])) {
      $friendlyTxt .= " with Status: " . CRM_Civirules_Utils::getParticipantStatusLabel($actionParameters['participant_status_id']) . " on ";
    }
    if (!empty($actionParameters['registration_date'])) {
      $registrationDate = new DateTime($actionParameters['registration_date']);
      $dateFormat = CRM_Utils_Date::getDateFormat();
      switch ($dateFormat) {
        case "mm-dd-yy":
          $friendlyTxt .= $registrationDate->format('m-d-y');
          break;
        case "yy-mm-dd":
          $friendlyTxt .= $registrationDate->format('y-m-d');
          break;
        case "mm-yy-dd":
          $friendlyTxt .= $registrationDate->format('m-y-d');
          break;
        default:
          $friendlyTxt .= $registrationDate->format('d-m-y');
          break;
      }
    }
    return $friendlyTxt;
  }

  /**
   * This function validates whether this action works with the selected trigger.
   *
   * This function could be overriden in child classes to provide additional validation
   * whether an action is possible in the current setup.
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    $entities = $trigger->getProvidedEntities();
    $validEntities = ["Participant", "Contact", "Individual", "Household", "Organization"];
    foreach ($validEntities as $validEntity) {
      if (isset($entities[$validEntity])) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
