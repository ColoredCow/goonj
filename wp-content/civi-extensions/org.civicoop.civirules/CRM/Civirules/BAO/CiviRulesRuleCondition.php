<?php

use CRM_Civirules_ExtensionUtil as E;
use Civi\Api4\CiviRulesRuleCondition;

/**
 * BAO RuleCondition for CiviRule Rule Condition
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */
class CRM_Civirules_BAO_CiviRulesRuleCondition extends CRM_Civirules_DAO_RuleCondition implements \Civi\Core\HookInterface {

  /**
   * Function to disable a rule condition
   *
   * @param int $ruleConditionId
   *
   * @throws Exception when ruleConditionId is empty
   */
  public static function disable($ruleConditionId) {
    if (!empty($ruleConditionId)) {
      CiviRulesRuleCondition::update(FALSE)
        ->addValue('is_active', 0)
        ->addWhere('id', '=', $ruleConditionId)
        ->execute();
    }
  }

  /**
   * Function to enable a rule condition
   *
   * @param int $ruleConditionId
   *
   * @throws Exception when ruleConditionId is empty
   */
  public static function enable($ruleConditionId) {
    if (!empty($ruleConditionId)) {
      CiviRulesRuleCondition::update(FALSE)
        ->addValue('is_active', 1)
        ->addWhere('id', '=', $ruleConditionId)
        ->execute();
    }
  }

  /**
   * Function to count the number of conditions for a rule
   *
   * @param int $ruleId
   *
   * @return int
   */
  public static function countConditionsForRule($ruleId) {
    return CiviRulesRuleCondition::get(FALSE)
      ->addWhere('rule_id', '=', $ruleId)
      ->execute()->count();
  }

  /**
   * Callback for hook_civicrm_post().
   * @param \Civi\Core\Event\PostEvent $event
   */
  public static function self_hook_civicrm_post(\Civi\Core\Event\PostEvent $event) {
    if (in_array($event->action, ['create' , 'edit'])) {
      CRM_Utils_Weight::correctDuplicateWeights('CRM_Civirules_DAO_CiviRulesRuleCondition');
    }
    if ($event->action === 'delete') {
      if (property_exists($event->object, 'rule_id'))
      self::emptyConditionLinkForFirstCondition($event->object->rule_id);
    }
  }

  private static function emptyConditionLinkForFirstCondition($ruleID) {
    $firstRuleCondition = CiviRulesRuleCondition::get(FALSE)
      ->addSelect('id', 'condition_link')
      ->addWhere('rule_id', '=', $ruleID)
      ->addOrderBy('weight', 'ASC')
      ->addOrderBy('id', 'ASC')
      ->execute()
      ->first();
    if (!empty($firstRuleCondition) && !empty($firstRuleCondition['condition_link'])) {
      CiviRulesRuleCondition::update(FALSE)
        ->addValue('condition_link', NULL)
        ->addWhere('id', '=', $firstRuleCondition['id'])
        ->execute();
    }
  }

  /**
   * Function to unserialize the CiviRulesRuleCondition condition_params
   *
   * @return array
   */
  public function unserializeParams(): array {
    if (!empty($this->condition_params) && !is_array($this->condition_params)) {
      return unserialize($this->condition_params);
    }
    return [];
  }

  public static function getConditionLinkOptions() {
    return [
      'AND' => E::ts('AND'),
      'OR' => E::ts('OR'),
    ];
  }

}
