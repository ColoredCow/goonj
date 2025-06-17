<?php

use CRM_Civirules_ExtensionUtil as E;
/**
 * Class CRM_CivirulesConditions_Contact_HasRecur
 */
class CRM_CivirulesConditions_Contact_HasRecur extends CRM_CivirulesConditions_Generic_Status {

  /**
   * The entity name (eg. Membership)
   * @return string
   */
  protected function getEntity() {
    return 'ContributionRecur';
  }

  /**
   * The entity status field (eg. membership_status_id)
   * @return string
   */
  public function getEntityStatusFieldName() {
    return 'contribution_status_id';
  }

  /**
   * The (internal) name of the condition
   * @return string
   */
  protected function getConditionName() {
    return 'contact_has_recurring';
  }

  /**
   * Returns an array of statuses as [ id => label ]
   * @param bool $active
   * @param bool $inactive
   *
   * @return array
   */
  public static function getEntityStatusList($active = TRUE, $inactive = FALSE) {
    return CRM_CivirulesConditions_ContributionRecur_Status::getEntityStatusList($active, $inactive);
  }

  /**
   * Returns a help text for this condition.
   * The help text is shown to the administrator who is configuring the condition.
   *
   * @return string
   */
  public function getHelpText() {
    return E::ts('This condition checks if the contact has any recurring contributions in a specific status. If you select "is not in" it is also valid if the contact has no recurring contributions.');
  }

  /**
   * This method returns true or false when an condition is valid or not
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $params['contact_id'] = $triggerData->getContactId();
    $params['contribution_status_id'] = ['IN' => $this->conditionParams['status_id']];

    $entities = civicrm_api3('ContributionRecur', 'get', $params)['values'];
    switch ($this->conditionParams['operator']) {
      case 0:
        if (!empty($entities)) {
          return TRUE;
        }
        break;

      case 1:
        if (empty($entities)) {
          return TRUE;
        }
        break;
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
   */
  public function getExtraDataInputUrl($ruleConditionId) {
    return CRM_Utils_System::url('civicrm/civirule/form/condition/status',
      [
        'rule_condition_id' => $ruleConditionId,
        'entity' => $this->getEntity(),
        'condition_name' => $this->getConditionName(),
      ],
      FALSE, NULL, FALSE, FALSE, TRUE
    );
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {

    $params = parent::exportConditionParameters();
    if (!empty($params['status_id']) && is_array($params['status_id'])) {
      foreach($params['status_id'] as $i => $j) {
        try {
          $params['status_id'][$i] = civicrm_api3('OptionValue', 'getvalue', [
            'return' => 'name',
            'value' => $j,
            'option_group_id' => 'contribution_status',
          ]);
        } catch (CiviCRM_API3_Exception $e) {
        }
      }
    } elseif (!empty($params['status_id'])) {
      try {
        $params['status_id'] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'name',
          'value' => $params['status_id'],
          'option_group_id' => 'contribution_status',
        ]);
      } catch (CiviCRM_API3_Exception $e) {
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
    if (!empty($condition_params['status_id']) && is_array($condition_params['status_id'])) {
      foreach($condition_params['status_id'] as $i => $j) {
        try {
          $condition_params['status_id'][$i] = civicrm_api3('OptionValue', 'getvalue', [
            'return' => 'value',
            'name' => $j,
            'option_group_id' => 'contribution_status',
          ]);
        } catch (CiviCRM_API3_Exception $e) {
        }
      }
    } elseif (!empty($condition_params['status_id'])) {
      try {
        $condition_params['status_id'] = civicrm_api3('OptionValue', 'getvalue', [
          'return' => 'value',
          'name' => $condition_params['status_id'],
          'option_group_id' => 'contribution_status',
        ]);
      } catch (CiviCRM_API3_Exception $e) {
      }
    }
    return parent::importConditionParameters($condition_params);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $statusList = self::getEntityStatusList(TRUE, TRUE);
    $operator = NULL;
    if ($this->conditionParams['operator'] == 0) {
      $operator = self::getOperatorOptions()[0];
      $extraText = '';
    }
    if ($this->conditionParams['operator'] == 1) {
      $operator = self::getOperatorOptions()[1];
      $extraText = E::ts('or has no recurring contributions.');
    }

    if (is_array($this->conditionParams['status_id'])) {
      $statusLabels = [];
      foreach ($this->conditionParams['status_id'] as $statusID) {
        $statusLabels[] = $statusList[$statusID];
      }
      $statusLabel = implode(',', $statusLabels);
    }
    else {
      $statusLabel = $statusList[$this->conditionParams['status_id']];
    }

    return E::ts("Contact has %1(s) with the status '%2' '%3' %4",
      [
        1 => $this->getEntity(),
        2 => $operator,
        3 => $statusLabel,
        4 => $extraText,
      ]
    );
  }

  /**
   * This function validates whether this condition works with the selected trigger.
   *
   * This function could be overridden in child classes to provide additional validation
   * whether a condition is possible in the current setup. E.g. we could have a condition
   * which works on contribution or on contributionRecur then this function could do
   * this kind of validation and return false/true
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   *
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    return $trigger->doesProvideEntity('Contact');
  }

}
