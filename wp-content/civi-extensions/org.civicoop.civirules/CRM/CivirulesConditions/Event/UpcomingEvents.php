<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesConditions_Event_UpcomingEvents extends CRM_Civirules_Condition {

  private $conditionParams = array();

  /**
   * Method to set the Rule Condition data
   *
   * @param array $ruleCondition
   * @access public
   */
  public function setRuleConditionData($ruleCondition) {
    parent::setRuleConditionData($ruleCondition);
    $this->conditionParams = array();
    if (!empty($this->ruleCondition['condition_params'])) {
      $this->conditionParams = unserialize($this->ruleCondition['condition_params']);
    }
  }

  /**
   * Method to determine if the condition is valid
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   */

  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $eventApi = \Civi\Api4\Event::get(FALSE)
      ->selectRowCount()
      ->addWhere('is_active', '=', TRUE)
      ->addWhere('start_date', '>=', date("Y-m-d H:i:s"));
    if ($this->conditionParams['event_type_id'] !== NULL && count($this->conditionParams['event_type_id'])) {
      $eventApi->addWhere('event_type_id', 'IN', $this->conditionParams['event_type_id']);
    }
    if ($this->conditionParams['additional_wheres'] !== NULL) {
      $additionalWheres = json_decode($this->conditionParams['additional_wheres'], TRUE);
      if ($additionalWheres) {
        foreach($additionalWheres as $where) {
          list($field, $op, $value) = $where;
          $eventApi->addWhere($field, $op, $value);
        }
      }
    }  
    $events = $eventApi->execute();
    if ($events->countMatched() > 0) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a condition
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleConditionId
   * @return bool|string
   * @access public
   * @abstract
   */
  public function getExtraDataInputUrl($ruleConditionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/upcoming_events', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   * @throws Exception
   */
  public function userFriendlyConditionParams() {
    $friendlyText = "";
    $typeText = array();
      if (!empty($this->conditionParams['event_type_id'])) {
      $eventTypes = civicrm_api3('OptionValue', 'get', array(
        'value' => array('IN' => $this->conditionParams['event_type_id']),
        'option_group_id' => 'event_type',
        'options' => array('limit' => 0)
      ));
      foreach($eventTypes['values'] as $eventType) {
        $typeText[] = $eventType['label'];
      }
    }

    if (!empty($typeText)) {
      $friendlyText .= E::ts('Event type is one of: %1', [1=>implode(", ", $typeText)]);
    }
    return $friendlyText;
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $params = parent::exportConditionParameters();
    if (!empty($params['event_type_id']) && is_array($params['event_type_id'])) {
      foreach($params['event_type_id'] as $i => $j) {
        $params['event_type_id'][$i] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'name',
          'value' => $j,
          'option_group_id' => 'event_type',
        ]);
      }
    } elseif (!empty($params['event_type_id'])) {
      try {
        $params['event_type_id'] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'name',
          'value' => $params['event_type_id'],
          'option_group_id' => 'event_type',
        ]);
      } catch (\CRM_Core_Exception $e) {
        // Do nothing.
      }
    }
    return $params;
  }

  /**
   * Returns condition data as an array and ready for import.
   * E.g. replace name for ids.
   *
   * @return string
   */
  public function importConditionParameters($condition_params = NULL) {
    if (!empty($condition_params['event_type_id']) && is_array($condition_params['event_type_id'])) {
      foreach($condition_params['event_type_id'] as $i => $j) {
        $condition_params['event_type_id'][$i] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'name',
          'value' => $j,
          'option_group_id' => 'event_type',
        ]);
      }
    } elseif (!empty($condition_params['event_type_id'])) {
      try {
        $condition_params['event_type_id'] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'value',
          'name' => $condition_params['event_type_id'],
          'option_group_id' => 'event_type',
        ]);
      } catch (\CRM_Core_Exception $e) {
        // Do nothing.
      }
    }
    return parent::importConditionParameters($condition_params);
  }

}
