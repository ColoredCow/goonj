<?php
/**
 * Class for CiviRule Condition Generic Date is, Participation register date is, Participation event date is .....
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 3 May 2018
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_CivirulesConditions_Generic_Date extends CRM_Civirules_Condition {

  private $_conditionParams = array();

  public function getExtraDataInputUrl($ruleConditionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/generic/date', $ruleConditionId);
  }

  /**
   * Method to set the Rule Condition data
   *
   * @param array $ruleCondition
   * @access public
   */
  public function setRuleConditionData($ruleCondition) {
    parent::setRuleConditionData($ruleCondition);
    $this->_conditionParams = array();
    if (!empty($this->ruleCondition['condition_params'])) {
      $conditionParams = unserialize($this->ruleCondition['condition_params']);
      // convert from old format
      if (empty($data['compare_type'])) {
        if (!empty($data['use_trigger_date'])) { // old verion of way trigger date was saved
          $conditionParams['compare_type'] = 'trigger';
        } elseif (!empty($data['use_action_date'])) { // old verion of way action date was saved
          $conditionParams['compare_type'] = 'action';
        }
      }
      $this->ruleCondition['empty_field'] = empty($this->ruleCondition['empty_field']) ? 'false' : $this->ruleCondition['empty_field'];
      $this->_conditionParams = $conditionParams;
    }
  }

  /**
   * Method to check if the condition is valid, will check if the contact
   * has an activity of the selected type
   *
   * @param object CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   * @access public
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    
    if (!empty($this->_conditionParams['date_select'])) {
      list($source_entity, $source_field_id) = $this->parseRawFieldId($this->_conditionParams['date_select']);
    } else {
      $source_entity = 'Activity';
      $source_field_id = 'activity_date_time';
    }
    
    $entityData = $triggerData->getEntityData($source_entity);

    if (!empty($entityData[$source_field_id])) {
      
      try {
        $activityDate = new DateTime($entityData[$source_field_id]);
      } catch (Exception $ex) {
        Civi::log()->error(ts('Could not parse activity_date_time ') . $entityData[$source_field_id]
          . ts(' into a DateTime object in ') . __METHOD__ . ts(', condition returned as false'));
        return false;
      }

      if ($activityDate) {

        if ($this->_conditionParams['operator'] == 6) {

          try {
            $fromDate = new DateTime($this->_conditionParams['activity_from_date']);
            $toDate = new DateTime($this->_conditionParams['activity_to_date']);
          } catch (Exception $ex) {
            Civi::log()->error(ts('Could not parse either from date or to date from the condition params into a DateTime object in ') . __METHOD__ . ts(', condition returned as false'));
            return FALSE;
          }

          if ($fromDate >= $activityDate && $toDate <= $activityDate)
            return true;

        } else {

          $compareDate = $this->getCompareDate($triggerData);

          if ($compareDate===true)
            return true;
          if ($compareDate===false)
            return false;

          return $this->compareDate($compareDate, $activityDate);
        }
      }
    }
    
    return false;
  }

  /**
   * Method to compare activity date and compare date
   *
   * @param DateTime $compareDate
   * @param DateTime $activityDate
   * @return bool
   */
  private function compareDate($compareDate, $activityDate) {
    switch ($this->_conditionParams['operator']) {
      case 0:
        if ($activityDate == $compareDate) {
          return true;
        }
        break;
      case 1:
        if ($activityDate > $compareDate) {
          return true;
        }
        break;
      case 2:
        if ($activityDate >= $compareDate) {
          return true;
        }
        break;
      case 3:
        if ($activityDate < $compareDate) {
          return true;
        }
        break;
      case 4:
        if ($activityDate <= $compareDate) {
          return true;
        }
        break;
      case 5:
        if ($activityDate != $compareDate) {
          return true;
        }
        break;
    }
    return false;
  }

  /**
   * Method to get the compare date
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool|DateTime
   */
  private function getCompareDate($triggerData) {

    $triggerDate = date('YmdHis');
    if ($triggerData->isDelayedExecution) {
      if (isset($triggerData->delayedSubmitDateTime)) {
        $triggerDate = $triggerData->delayedSubmitDateTime;
      }
    }

    $actionDate = date('YmdHis');


    if ($this->_conditionParams['compare_type']==='trigger') { // if use_trigger_date, compare with trigger date
      $dateToUse = $triggerDate;
    } elseif ($this->_conditionParams['use_action_date']==='action') { // if use_action_date, use date (only makes sense for actions with delays)
      $dateToUse = $actionDate;
    } else { // field or fixed dated
      
      if ($this->_conditionParams['compare_type']==='field') { // some field value
        try {
          
          if (empty($this->_conditionParams['activity_compare_field']))
            throw new Exception('No field set');
          
          list($source_entity, $source_field_id) = $this->parseRawFieldId($this->_conditionParams['activity_compare_field']);

          if (in_array(strtolower($source_entity), ['contact', 'individual', 'organization', 'household'])) {
            // source is a contact field
            $source_entity_id = $triggerData->getContactId();
          } else {
            // source is the triggering entity
            $source_entity_data = $triggerData->getEntityData($source_entity);
            if (empty($source_entity_data))
              throw new Exception("Source Entity Data or ID is empty for '{$source_entity}'");
            if (empty($source_entity_data['id']))
              throw new Exception("Source Entity ID is empty '{$source_entity_data['id']}'");
            $source_entity_id = $source_entity_data['id'];
          }

          $dateToUse = $this->getValue($source_entity, $source_field_id, $source_entity_id);

        } catch (Exception $ex) {
          Civi::log()->error(ts('Unable to field or contact to use ') . ' in ' . __METHOD__);
          return false;
        }
      } elseif ($this->_conditionParams['compare_type']==='fixed') { // some fixed falue

        if (!empty($this->_conditionParams['activity_compare_date']))
          $dateToUse = $this->_conditionParams['activity_compare_date'];

      } else {
        Civi::log()->error(ts('Invalid comparison type ') . ' in ' . __METHOD__);
        return false;
      }

      if (empty($dateToUse)) {
        switch ($this->_conditionParams['empty_field']) {
          case 'trigger':
            $dateToUse = $triggerDate;
            break;
          case 'action':
            $dateToUse = $actionDate;
            break;
          case 'true':
            return true;
          case 'false':
          default:
            return false;
        }
      }
    }

    if ($dateToUse) {
      try {
        // weird fix for the fact that activity_date_time does not hold seconds.....
        $dateToUse = substr($dateToUse, 0, -2) . '00';
        $compareDate = new DateTime($dateToUse);
        return $compareDate;
      }
      catch (Exception $ex) {
        Civi::log()->error(ts('Could not parse date ') . $this->_conditionParams['activity_compare_date'] . ' in ' . __METHOD__);
      }
    }

    return false;
  }

  /**
   * Get the value of the given field for the given contact
   *
   * @param string $entity_type entity type of primary object trigger
   * @param int $entity_id      entity ID of primary object trigger
   * @param string $field_id    field ID or special fields like 'contact_id'
   * @param string $mode        string can be 'value', 'min' or 'max'
   * @return mixed              current value
   * @throws Exception when unable to retrieve value
   */
  protected function getValue($entity_type, $field_id, $entity_id) {

    if (is_numeric($field_id)) {
      return civicrm_api3($entity_type, 'getvalue', [
        'id' => $entity_id,
        'return' => "custom_{$field_id}",
      ]);
    } elseif (!empty($field_id)) {
      return civicrm_api3($entity_type, 'getvalue', [
        'id' => $entity_id,
        'return' => $field_id,
      ]);
    } else {
      throw new Exception("Unknown field id '{$field_id}'.");
    }
  }

  /**
   * Parse saved field id into entity type and field id (eg Contact:10 to Contact and 10)
   * 
   * @param string $raw_field_id
   * @return array [entity_type, field_id]
   * @throws Exception when field id is invalid
   */
  protected function parseRawFieldId($raw_field_id) {

    $field_parts = explode('::', $raw_field_id);
    
    if (count($field_parts)!==2)
      throw new Exception("Invalid field format '{$raw_field_id}'.");

    list($entity_type, $field_id) = $field_parts;

    $entity_search = civicrm_api3('Entity', 'get', [
      'sequential' => 1,
    ]);

    $entities = array_map('strtolower', $entity_search['values']);
    if (!in_array(strtolower($entity_type), $entities))
      throw new Exception("Invalid entity for field '{$raw_field_id}'.");

    return $field_parts;
  }

  /**
   * Returns a user friendly text explaining the condition params
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    
    $operatorOptions = CRM_Civirules_Utils::getActivityDateOperatorOptions();

    if (!empty($this->_conditionParams['date_select'])) {
      switch($this->_conditionParams['date_select']) {
        case 'Event::start_date':
          $friendlyText = ts('Event Start Date');
          break;
        case 'Event::end_date':
          $friendlyText = ts('Event End Date');
          break;
        case 'Participant::participant_register_date':
          $friendlyText = ts('Participant Register Date');
          break;
        default;
          return 'Invalid date type!';
      }
    } else {
      $friendlyText = ts("Activity Date");
    }

    $friendlyText .= ' ' . ts($operatorOptions[$this->_conditionParams['operator']]);
    
    if ($this->_conditionParams['operator'] == 6) {
      try {
        $fromDate = new DateTime($this->_conditionParams['activity_from_date']);
        $toDate = new DateTime($this->_conditionParams['activity_to_date']);
        $friendlyText .= ' ' . $fromDate->format('j F Y') . ts(' and ') . $toDate->format('j F Y');
      }
      catch (Exception $ex) {
      }
    } elseif ($this->_conditionParams['compare_type'] === 'trigger') { // if use_trigger_date
      $friendlyText .= ' the date the rule is triggered.';
    } elseif ($this->_conditionParams['compare_type'] === 'action') { // if use_action_date
      $friendlyText .= ' the date the action is executed.';
    } elseif ($this->_conditionParams['compare_type'] === 'fixed') {
      try {
        if (empty($this->_conditionParams['activity_compare_date'])) {
          if ($this->_conditionParams['empty_field'] === 'trigger') {
            $friendlyText .= ' the date the rule is triggered.';
          } elseif ($this->_conditionParams['empty_field'] === 'action') {
            $friendlyText .= ' the date the action is executed.';
          } elseif ($this->_conditionParams['empty_field'] === 'true') {
            $friendlyText = 'Will always evaluate true.';
          } elseif ($this->_conditionParams['empty_field'] === 'false') {
            $friendlyText = 'Will always evaluate false.';
          } else {
            $friendlyText = 'Invalid empty date value.';
          }
        } else {
          $compareDate = new DateTime($this->_conditionParams['activity_compare_date']);
          $friendlyText .= ' ' . $compareDate->format('j F Y');
        }
      } catch (Exception $ex) {
        $friendlyText = 'Could not parse dates!';
      }
    } elseif ($this->_conditionParams['compare_type'] === 'field') {
      if (!empty($this->_conditionParams['activity_compare_field'])) {
        try {
          $parts = explode('::', $this->_conditionParams['activity_compare_field']);
          if (count($parts)!=2)
            throw new Exception;
          $field = civicrm_api3('CustomField', 'getsingle', ['id' => $parts[1]]);
          $friendlyText .= ' ' . ucwords($parts[0]) . ' field "' . $field['label'] . '"';
        } catch (Exception $ex) {
          $friendlyText = 'Invalid field set';
        }
      } else {
        $friendlyText = 'No field set';
      }
    } else {
      $friendlyText = 'Invalid rule!';
    }
    return $friendlyText;
  }

  /**
   * This function validates whether this condition works with the selected trigger.
   *
   * This function could be overriden in child classes to provide additional validation
   * whether a condition is possible in the current setup. E.g. we could have a condition
   * which works on contribution or on contributionRecur then this function could do
   * this kind of validation and return false/true
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    return $trigger->doesProvideEntity('Activity') || $trigger->doesProvideEntity('Participant');
  }
}
