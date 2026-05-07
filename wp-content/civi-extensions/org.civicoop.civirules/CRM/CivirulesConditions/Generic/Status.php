<?php

use Civi\Api4\CiviRulesRuleCondition;

abstract class CRM_CivirulesConditions_Generic_Status extends CRM_Civirules_Condition {

  protected $conditionParams = [];

  /**
   * The entity name (eg. Membership)
   * @return string
   */
  abstract protected function getEntity();

  /**
   * The entity status field (eg. membership_status_id)
   * @return string
   */
  abstract public function getEntityStatusFieldName();

  /**
   * Returns an array of statuses as [ id => label ]
   * @param bool $active
   * @param bool $inactive
   *
   * @return array
   */
  abstract public static function getEntityStatusList($active = TRUE, $inactive = FALSE);

  /**
   * Returns an array of statuses as [ id => label ]
   * @param bool $active
   * @param bool $inactive
   *
   * @return array
   */
  protected static function getEntityStatusListFromOptionGroup($groupName, $active = TRUE, $inactive = FALSE) {
    $return = [];
    $params = [
      'return' => ["label", "value"],
      'option_group_id' => $groupName,
      'options' => ['limit' => 0, 'sort' => "label ASC"],
    ];
    if ($active && !$inactive) {
      $params['is_active'] = 1;
    }
    elseif ($inactive && !$active) {
      $params['is_active'] = 0;
    }

    try {
      $options = civicrm_api3('OptionValue', 'get', $params)['values'];
      foreach ($options as $option) {
        $return[$option['value']] = $option['label'];
      }
    } catch (CRM_Core_Exception $ex) {}
    return $return;
  }

  /**
   * Method to set the Rule Condition data
   *
   * @param array $ruleCondition
   */
  public function setRuleConditionData(array $ruleCondition) {
    parent::setRuleConditionData($ruleCondition);
    $this->conditionParams = [];
    if (!empty($this->ruleCondition['condition_params'])) {
      $this->conditionParams = unserialize($this->ruleCondition['condition_params']);
      $oldFieldName = strtolower($this->getEntity()) . '_status_id';
      if (isset($this->conditionParams[$oldFieldName]) && !isset($this->conditionParams['status_id'])) {
        // Some old conditions saved using the entity specific name (eg. membership_status_id)
        $this->conditionParams['status_id'] = $this->conditionParams[$oldFieldName];
        unset($this->conditionParams[$oldFieldName]);
        $paramsUpdated = TRUE;
      }
      if (!is_array($this->conditionParams['status_id'])) {
        // Some old conditions did not support selecting multiple statuses so were saved as an integer.
        $this->conditionParams['status_id'] = [$this->conditionParams['status_id']];
        $paramsUpdated = TRUE;
      }
      if (isset($paramsUpdated)) {
        // Save the updated condition params
        CiviRulesRuleCondition::update(FALSE)
          ->addValue('condition_params', serialize($this->conditionParams))
          ->addWhere('id', '=', $this->ruleCondition['id'])
          ->execute();
      }

      if (!isset($this->conditionParams['operator'])) {
        // Contribution Status did not have "operator" so we set to 0 if not set.
        $this->conditionParams['operator'] = 0;
      }
    }
  }

  /**
   * Method to determine if the condition is valid
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $isConditionValid = FALSE;
    $entityData = $triggerData->getEntityData($this->getEntity());
    // issue 139, get campaign data if not complete (@link https://lab.civicrm.org/extensions/civirules/-/issues/139)
    $entity = $triggerData->getEntity();
    if ($entity == "Campaign") {
      $entityData = CRM_CivirulesConditions_Generic_Campaign::getCampaignData((int) $entityData['id']);
    }
    switch ($this->conditionParams['operator']) {
      case 0:
        if (in_array($entityData[$this->getEntityStatusFieldName()], $this->conditionParams['status_id'])) {
          $isConditionValid = TRUE;
        }
        break;

      case 1:
        if (!in_array($entityData[$this->getEntityStatusFieldName()], $this->conditionParams['status_id'])) {
          $isConditionValid = TRUE;
        }
        break;
    }

    return $isConditionValid;
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
      "rule_condition_id={$ruleConditionId}&entity={$this->getEntity()}",
      FALSE, NULL, FALSE, FALSE, TRUE
    );
  }

  /**
   * Method to get operators
   *
   * @return array
   */
  public static function getOperatorOptions() {
    return [
      0 => ts('Is one of'),
      1 => ts('Is not one of'),
    ];
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
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    return $trigger->doesProvideEntity($this->getEntity());
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    $statusList = $this->getEntityStatusList(TRUE, TRUE);
    $operator = null;
    if ($this->conditionParams['operator'] == 0) {
      $operator = self::getOperatorOptions()[0];
    }
    if ($this->conditionParams['operator'] == 1) {
      $operator = self::getOperatorOptions()[1];
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

    $entityName = $this->getEntity();
    if (method_exists($this, 'getEntityAlias')) {
      $entityName = $this->getEntityAlias();
    }

    return "{$entityName} Status {$operator} {$statusLabel}";
  }

}
