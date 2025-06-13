<?php
/**
 * Class for CiviRules AgeComparison (extending generic ValueComparison)
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_CivirulesConditions_Contact_InDomain extends CRM_Civirules_Condition {

  private $conditionParams = [];

  /**
   * Method to set the Rule Condition data
   *
   * @param array $ruleCondition
   */
  public function setRuleConditionData($ruleCondition) {
    parent::setRuleConditionData($ruleCondition);
    $this->conditionParams = [];
    if (!empty($this->ruleCondition['condition_params'])) {
      $this->conditionParams = unserialize($this->ruleCondition['condition_params']);
    }
  }

  /**
   * This method returns true or false when an condition is valid or not
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return bool
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $isConditionValid = FALSE;
    $contact_id = $triggerData->getContactId();
    switch($this->conditionParams['operator']) {
      case 'in':
        $isConditionValid = $this->contactIsMemberOfDomain($contact_id, $this->conditionParams['domain_id']);
        break;
      case 'not in':
        $isConditionValid = $this->contactIsNotMemberOfDomain($contact_id, $this->conditionParams['domain_id']);
        break;
    }
    return $isConditionValid;
  }

  /**
   * @param int $contact_id
   * @param int $domain_id
   *
   * @return bool
   */
  protected function contactIsNotMemberOfDomain($contact_id, $domain_id) {
    $isValid = TRUE;
    if (self::isContactInDomain($contact_id, $domain_id)) {
      $isValid = FALSE;
    }
    return $isValid;
  }

  /**
   * @param int $contact_id
   * @param int $domain_id
   *
   * @return bool
   */
  protected function contactIsMemberOfDomain($contact_id, $domain_id) {
    $isValid = FALSE;
    if (self::isContactIndomain($contact_id, $domain_id)) {
      $isValid = TRUE;
    }
    return $isValid;
  }

  /**
   * Returns a redirect url to extra data input from the user after adding a condition
   *
   * Return false if you do not need extra data input
   *
   * @param int $ruleConditionId
   *
   * @return bool|string
   */
  public function getExtraDataInputUrl($ruleConditionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/contact_indomain', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   */
  public function userFriendlyConditionParams() {
    $operators = CRM_CivirulesConditions_Contact_InDomain::getOperatorOptions();
    $operator = $this->conditionParams['operator'];
    $operatorLabel = ts('unknown');
    if (isset($operators[$operator])) {
      $operatorLabel = $operators[$operator];
    }

    $domainTitle = self::getDomainName($this->conditionParams['domain_id']);

    return $operatorLabel.' groups ('.$domainTitle.')';
  }

  /**
   * Method to get operators
   *
   * @return array
   */
  public static function getOperatorOptions() {
    return [
      'in' => ts('In selected domain'),
      'not in' => ts('Not in selected domain'),
    ];
  }

  /**
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function domains() {
    $domains = [];
    $domainsFound = \Civi\Api4\Domain::get(FALSE)
      ->execute()
      ->indexBy('id');
    foreach ($domainsFound as $domainId => $values) {
      $domains[$domainId] = $values['name'];
    }
    return $domains;
  }

  /**
   * @param int $domain_id
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  public static function getDomainName($domain_id) {
    return self::domains()[$domain_id];
  }

  /**
   * @param int $contact_id
   * @param int $domain_id
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public static function isContactIndomain($contact_id, $domain_id) {
    $group_id = \Civi::settings($domain_id)->get('domain_group_id');
    if (empty($group_id)) {
      return TRUE;
    }
    else {
      return CRM_CivirulesConditions_Utils_GroupContact::isContactInGroup($contact_id, $group_id);
    }
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $params = parent::exportConditionParameters();
    if (!empty($params['domain_id'])) {
      try {
        $params['domain_id'] = civicrm_api3('Domain', 'getvalue', [
          'return' => 'name',
          'id' => $params['domain_id']
        ]);
      } catch (\CiviCRM_Api3_Exception $e) {
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
    if (!empty($condition_params['domain_id'])) {
      try {
        $condition_params['domain_id'] = civicrm_api3('Domain', 'getvalue', [
          'return' => 'id',
          'name' => $condition_params['domain_id']
        ]);
      } catch (\CiviCRM_Api3_Exception $e) {
        // Do nothing.
      }
    }
    return parent::importConditionParameters($condition_params);
  }

}
