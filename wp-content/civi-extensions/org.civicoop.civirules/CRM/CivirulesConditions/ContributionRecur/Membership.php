<?php
class CRM_CivirulesConditions_ContributionRecur_Membership extends CRM_Civirules_Condition {

  private $conditionParams = [];

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
   * This method returns true or false when an condition is valid or not
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   * @access public
   * @abstract
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {

    $recurringID = $triggerData->getEntityData('ContributionRecur')['id'] ?? NULL;
    if (!$recurringID) {
      // Just in case
      return FALSE;
    }

    $sqlParams = [];
    $whereClauses = [ 'contribution_recur_id = %1' ];
    $sqlParams[1] = [$recurringID, 'Integer'];
    // Most of the following is copied from  CRM_CivirulesConditions_Membership_ContactHasMembership class.

    if (count($this->conditionParams['membership_type_id'])) {
      switch ($this->conditionParams['type_operator']) {
        case 'in':
          $whereClauses[] = 'membership_type_id IN (' . implode(',', $this->conditionParams['membership_type_id']) . ')';
          break;

        case 'not in':
          $whereClauses[] = 'membership_type_id NOT IN (' . implode(',', $this->conditionParams['membership_type_id']) . ')';
          break;
      }
    }
    if (count($this->conditionParams['membership_status_id'])) {
      switch ($this->conditionParams['status_operator']) {
        case 'in':
          $whereClauses[] = 'status_id IN (' . implode(',', $this->conditionParams['membership_status_id']) . ')';
          break;

        case 'not in':
          $whereClauses[] = 'status_id NOT IN (' . implode(',', $this->conditionParams['membership_status_id']) . ')';
          break;
      }
    }

    $dateFields = ['start_date', 'join_date', 'end_date'];
    foreach ($dateFields as $dateField) {
      $date_relative = $this->conditionParams[$dateField . '_relative'] ?? NULL;
      $date_to = $this->conditionParams[$dateField . '_to'] ?? NULL;
      $date_from = $this->conditionParams[$dateField . '_from'] ?? NULL;

      if (!empty($date_relative) || !empty($date_from) || !empty($date_to)) {
        [$from, $to] = CRM_Utils_Date::getFromTo($date_relative, $date_from, $date_to);
        $dateOperator = NULL;
        if (!empty($from) && !empty($to)) {
          $dateOperator = "BETWEEN '{$from}' AND '{$to}'";
        }
        elseif (!empty($from) && empty($to)) {
          $dateOperator = ">= '{$from}'";
        }
        elseif (empty($from) && !empty($to)) {
          $dateOperator = "<= '{$to}'";
        }
        if (!empty($dateOperator)) {
          $whereClauses[] = "($dateField $dateOperator)";
        }
      }
    }

    $sql = "SELECT COUNT(*) as total FROM civicrm_membership WHERE " . implode(' AND ', $whereClauses);
    $count = CRM_Core_DAO::singleValueQuery($sql, $sqlParams);

    return $count > 0;
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $params = parent::exportConditionParameters();
    if (!empty($params['membership_type_id']) && is_array($params['membership_type_id'])) {
      foreach($params['membership_type_id'] as $i => $gid) {
        try {
          $params['membership_type_id'][$i] = civicrm_api3('MembershipType', 'getvalue', [
            'return' => 'name',
            'id' => $gid,
          ]);
        } catch (CRM_Core_Exception $e) {
        }
      }
    }
    if (!empty($params['membership_status_id']) && is_array($params['membership_status_id'])) {
      foreach($params['membership_status_id'] as $i => $gid) {
        try {
          $params['membership_status_id'][$i] = civicrm_api3('MembershipStatus', 'getvalue', [
            'return' => 'name',
            'id' => $gid,
          ]);
        } catch (CRM_Core_Exception $e) {
        }
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
    if (!empty($condition_params['membership_type_id']) && is_array($condition_params['membership_type_id'])) {
      foreach($condition_params['membership_type_id'] as $i => $gid) {
        try {
          $condition_params['membership_type_id'][$i] = civicrm_api3('MembershipType', 'getvalue', [
            'return' => 'id',
            'name' => $gid,
          ]);
        } catch (CRM_Core_Exception $e) {
        }
      }
    }
    if (!empty($condition_params['membership_status_id']) && is_array($condition_params['membership_status_id'])) {
      foreach($condition_params['membership_status_id'] as $i => $gid) {
        try {
          $condition_params['membership_status_id'][$i] = civicrm_api3('MembershipStatus', 'getvalue', [
            'return' => 'id',
            'name' => $gid,
          ]);
        } catch (CRM_Core_Exception $e) {
        }
      }
    }
    return parent::importConditionParameters($condition_params);
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/recurhasmembership', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $label = '';
    $operator_options = self::getOperatorOptions();
    $inclusionOperators = $this->getInclusionOptions();
    $selectedInclusionOperator = CRM_Utils_Array::value('inclusion_operator', $this->conditionParams, 0);
    $label = $inclusionOperators[$selectedInclusionOperator] . "<ul>";

    try {
      $params = array(
        'is_active' => 1,
        'options' => array('limit' => 0, 'sort' => "name ASC"),
      );
      $membershipTypes = civicrm_api3('MembershipType', 'Get', $params);
      if (isset($this->conditionParams['membership_type_id']) && count($this->conditionParams['membership_type_id'])) {
        $operator = $operator_options[$this->conditionParams['type_operator']];
        $values = '';
        foreach ($this->conditionParams['membership_type_id'] as $membershipTypeId) {
          if (!isset($membershipTypes['values'][$membershipTypeId])) {
            continue;
          }
          if (strlen($values)) {
            $values .= ', ';
          }
          $values .= $membershipTypes['values'][$membershipTypeId]['name'];
        }
        $label .= "<li>" . ts('Membership Type') . " {$operator} <b>{$values}</b> <br>";
      }
    }
    catch (CRM_Core_Exception $ex) {
    }

    try {
      if (isset($this->conditionParams['membership_status_id']) && count($this->conditionParams['membership_status_id'])) {
        $params = [
          'options' => ['limit' => 0],
        ];
        $membershipStatus = civicrm_api3('MembershipStatus', 'Get', $params);
        $operator = $operator_options[$this->conditionParams['status_operator']];
        $values = '';
        foreach ($this->conditionParams['membership_status_id'] as $membershipStatusId) {
          if (!isset($membershipStatus['values'][$membershipStatusId])) {
            continue;
          }
          if (strlen($values)) {
            $values .= ', ';
          }
          $values .= $membershipStatus['values'][$membershipStatusId]['name'];
        }
        $label .= "<li>" . ts('Membership Status') . " {$operator} <b>{$values}</b> <br>";
      }
    }
    catch (CRM_Core_Exception $ex) {
    }

    $dateFields = [
      'start_date' => ts('Membership Start Date'),
      'join_date' => ts('Membership Join Date'),
      'end_date' => ts('Membership End Date'),
    ];
    $dateOperators = CRM_Core_OptionGroup::values('relative_date_filters');
    $msg = [];
    foreach ($dateFields as $dateField => $dateDesc) {
      $date_relative = $this->conditionParams[$dateField . '_relative'] ?? NULL;
      $date_to = $this->conditionParams[$dateField . '_to'] ?? NULL;
      $date_from = $this->conditionParams[$dateField . '_from'] ?? NULL;

      if (!empty($date_relative)) {
        $msg[] = $dateDesc . " <b>{$dateOperators[$date_relative]}</b>";
      }
      elseif (!empty($date_from) || !empty($date_to)) {
        $dateMsg = $dateDesc;
        if (!empty($date_from)) {
          $dateMsg .= ' ' . ts('From') . " <b>$date_from</b>";
        }
        if (!empty($date_to)) {
          $dateMsg .= ' ' . ts('To') . " <b>$date_to</b>";
        }
        $msg[] = $dateMsg;
      }
    };
    $label .= implode('<li>', $msg) . "</ul>";

    return trim($label);
  }

  /**
   * Method to get operators
   *
   * @return array
   * @access protected
   */
  public static function getOperatorOptions() {
    return array(
      'in' => ts('Is one of'),
      'not in' => ts('Is not one of'),
    );
  }

  /**
   * Method to get operators
   *
   * @return array
   * @access protected
   */
  public static function getInclusionOptions() {
    // Contact HAS Membership is value '0', for backwards-compatibility for existing rules where this condition will be empty
    return array(
      '0' => ts('Recurring Contribution pays for Membership'),
      '1' => ts('Recurring Contribution does not pay for Membership'),
    );
  }

}

