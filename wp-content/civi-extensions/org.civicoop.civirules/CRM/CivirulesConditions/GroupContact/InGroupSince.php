<?php

use CRM_Civirules_ExtensionUtil as E;

/**
 * Class for CiviRules Condition Contact In Group Since
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 28 Sep 2021
 * @link https://lab.civicrm.org/extensions/civirules/-/issues/158
 * @license AGPL-3.0
 */

class CRM_CivirulesConditions_GroupContact_InGroupSince extends CRM_Civirules_Condition {

  private $_conditionParams = [];

  /**
   * Method to set the Rule Condition data
   *
   * @param array $ruleCondition
   * @access public
   */
  public function setRuleConditionData(array $ruleCondition) {
    parent::setRuleConditionData($ruleCondition);
    $this->_conditionParams = [];
    if (!empty($this->ruleCondition['condition_params'])) {
      $this->_conditionParams = unserialize($this->ruleCondition['condition_params']);
    }
  }

  /**
   * Method to determine if the condition is valid
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @return bool
   * @throws
   */
  public function isConditionValid(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $contactId = (int) $triggerData->getContactId();
    // only if contact in group in the first place
    if ($this->isContactInGroup($contactId)) {
      $sinceDate = $this->determineSinceDate();
      $groupDate = $this->getLatestGroupAddDate($contactId);
      if ($sinceDate && $groupDate) {
        if ($this->_conditionParams['operator'] == 'longer') {
          if ($groupDate < $sinceDate) {
            return TRUE;
          }
        }
        else {
          if ($groupDate >= $sinceDate) {
            return TRUE;
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Method to get the date of the latest date the contact was added to the group
   *
   * @param int $contactId
   * @return DateTime|false
   * @throws Exception
   */
  private function getLatestGroupAddDate(int $contactId) {
    $query = "SELECT date, status
        FROM civicrm_subscription_history
        WHERE group_id = %1 AND contact_id = %2 ORDER BY date DESC LIMIT 1";
    $dao = CRM_Core_DAO::executeQuery($query, [
      1 => [(int) $this->_conditionParams['group_id'], "Integer"],
      2 => [$contactId, "Integer"],
    ]);
    if ($dao->fetch()) {
      if ($dao->status == "Added") {
        $groupDate = new DateTime($dao->date);
        if ($groupDate) {
          return $groupDate;
        }
      }
    }
    return FALSE;
  }

  /**
   * Method to check if contact is in the relevant group at all
   *
   * @param int $contactId
   * @return bool
   */
  private function isContactInGroup(int $contactId) {
    if (function_exists('civicrm_api4')) {
      try {
        $groupContacts = \Civi\Api4\GroupContact::get()
          ->addSelect('COUNT(*) AS count')
          ->addWhere('contact_id', '=', $contactId)
          ->addWhere('group_id', '=', (int) $this->_conditionParams['group_id'])
          ->addWhere('status:name', '=', 'Added')
          ->execute();
        $groupContact = $groupContacts->first();
        if ($groupContact['count'] && $groupContact['count'] > 0) {
          return TRUE;
        }
      }
      catch (API_Exception $ex) {
      }

    }
    else {
      try {
        $count = civicrm_api3('GroupContact', 'getcount', [
          'contact_id' => $contactId,
          'group_id' => (int) $this->_conditionParams['group_id'],
          'status' => "Added",
        ]);
        if ($count > 0) {
          return TRUE;
        }
      }
      catch (CiviCRM_API3_Exception $ex) {
      }
    }
    return FALSE;
  }

  /**
   * Method to determine the since date
   *
   * @return DateTime|false
   */
  private function determineSinceDate() {
    $periodLabels = CRM_Civirules_Utils::getPeriods();
    $sinceDate = new DateTime();
    $label = $periodLabels[$this->_conditionParams['period']];
    if ($label) {
      $modifier = "-" . $this->_conditionParams['number'] . $label;
      return $sinceDate->modify($modifier);
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
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/condition/groupcontact/ingroupsince', $ruleConditionId);
  }

  /**
   * Returns a user friendly text explaining the condition params
   * e.g. 'Older than 65'
   *
   * @return string
   * @access public
   */
  public function userFriendlyConditionParams() {
    $periodLabels = CRM_Civirules_Utils::getPeriods();
    $text = "Contact has been in group ";
    if (isset($this->_conditionParams['group_id'])) {
      $groupTitle = CRM_Civirules_Utils::getGroupTitleWithId($this->_conditionParams['group_id']);
      if ($groupTitle) {
        $text .= $groupTitle;
      }
      else {
        $text .= $this->_conditionParams['group_id'];
      }
    }
    if (isset($this->_conditionParams['operator']) && !empty($this->_conditionParams['operator'])) {
      $text .= " " . $this->_conditionParams['operator'] . " than ";
    }
    if (isset($this->_conditionParams['number']) && !empty($this->_conditionParams['number'])) {
      $text .= $this->_conditionParams['number'] . " ";
    }
    if (isset($this->_conditionParams['period']) && !empty($this->_conditionParams['period'])) {
      $text .= $periodLabels[$this->_conditionParams['period']];
    }
    return $text;
  }

  /**
   * Returns condition data as an array and ready for export.
   * E.g. replace ids for names.
   *
   * @return array
   */
  public function exportConditionParameters() {
    $params = parent::exportConditionParameters();
    if (!empty($params['group_id'])) {
      try {
        $params['group_id'] = civicrm_api3('Group', 'getvalue', [
          'return' => 'name',
          'id' => $params['group_id'],
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
    if (!empty($condition_params['group_id'])) {
      try {
        $condition_params['group_id'] = civicrm_api3('Group', 'getvalue', [
          'return' => 'id',
          'name' => $condition_params['group_id'],
        ]);
      } catch (\CiviCRM_Api3_Exception $e) {
        // Do nothing.
      }
    }
    return parent::importConditionParameters($condition_params);
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
    return $trigger->doesProvideEntity('Contact');
  }

}
