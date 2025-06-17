<?php
use CRM_Civirules_ExtensionUtil as E;
/**
 * Class for CiviRules Case HasTag condition
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 17 May 2021
 * @license AGPL-3.0
 */

class CRM_CivirulesConditions_Case_HasTag extends CRM_Civirules_Condition {

  protected $conditionParams = [];

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
    }
  }

  /**
   * This method returns TRUE or FALSE when an condition is valid or not
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return bool
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData): bool {
    $isConditionValid = FALSE;
    $entityID = $triggerData->getEntityId();
    if (empty($entityID)) {
      return FALSE;
    }
    $generic = new CRM_CivirulesConditions_Generic_HasTag();
    $generic->setEntityTable('civicrm_case');
    switch($this->conditionParams['operator']) {
      case 'in one of':
        $isConditionValid = $generic->entityHasOneOfTags($entityID, $this->conditionParams['tag_ids']);
        break;
      case 'in all of':
        $isConditionValid = $generic->entityHasAllTags($entityID, $this->conditionParams['tag_ids']);
        break;
      case 'not in':
        $isConditionValid = $generic->entityHasNotTag($entityID, $this->conditionParams['tag_ids']);
        break;
    }
    return $isConditionValid;
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $generic = new CRM_CivirulesConditions_Generic_HasTag();
    $generic->setEntityTable('civicrm_case');
    $tags = $generic->getEntityTags();
    $params = parent::exportConditionParameters();
    if (!empty($params['tag_ids']) && is_array($params['tag_ids'])) {
      foreach($params['tag_ids'] as $i => $j) {
        $params['tag_ids'][$i] = $tags[$j];
      }
    } elseif (!empty($params['tag_ids'])) {
      $params['tag_ids'] = $tags[$params['tag_ids']];
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
    $generic = new CRM_CivirulesConditions_Generic_HasTag();
    $generic->setEntityTable('civicrm_case');
    $tags = array_flip($generic->getEntityTags());
    if (!empty($condition_params['tag_ids']) && is_array($condition_params['tag_ids'])) {
      foreach($condition_params['tag_ids'] as $i => $j) {
        $condition_params['tag_ids'][$i] = $tags[$j];
      }
    } elseif (!empty($condition_params['tag_ids'])) {
      $condition_params['tag_ids'] = $tags[$condition_params['tag_ids']];
    }
    return parent::importConditionParameters($condition_params);
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a condition
   *
   * Return FALSE if you do not need extra data input
   *
   * @param int $ruleConditionId
   *
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleConditionId) {
    return CRM_Utils_System::url('civicrm/civirule/form/condition/entity_hastag/',
      'rule_condition_id=' . $ruleConditionId . '&tn=civicrm_case',
      FALSE, NULL, FALSE, FALSE, TRUE
    );
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams(): string {
    $generic = new CRM_CivirulesConditions_Generic_HasTag();
    $operators = $generic->getOperatorOptions();
    if (isset($this->conditionParams['operator'])) {
      $operator = $this->conditionParams['operator'];
      $operatorLabel = E::ts('unknown');
      if (isset($operators[$operator])) {
        $operatorLabel = $operators[$operator];
      }
    }
    $tags = '';
    foreach($this->conditionParams['tag_ids'] as $tid) {
      if (strlen($tags)) {
        $tags .= ', ';
      }
      $tags .= civicrm_api3('Tag', 'getvalue', ['return' => 'name', 'id' => $tid]);
    }
    return $operatorLabel .' tags (' . $tags . ')';
  }

  /**
   * This function validates whether this condition works with the selected trigger.
   *
   * @param CRM_Civirules_Trigger $trigger
   * @param CRM_Civirules_BAO_Rule $rule
   *
   * @return bool
   */
  public function doesWorkWithTrigger(CRM_Civirules_Trigger $trigger, CRM_Civirules_BAO_Rule $rule) {
    return $trigger->doesProvideEntity('Case') || $trigger->doesProvideEntity('Case_Activity');
  }

}
