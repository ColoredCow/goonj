<?php

use Civi\Api4\CiviRulesRuleCondition;
use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesConditions_Form_Form extends CRM_Core_Form
{

  protected $ruleConditionId = false;

  /**
   * The (internal) name of the rule condition
   * @var string
   */
  protected $ruleConditionName = '';

  protected CRM_Civirules_BAO_CiviRulesRuleCondition $ruleCondition;

  protected CRM_Civirules_BAO_CiviRulesCondition $condition;

  protected CRM_Civirules_BAO_CiviRulesRule $rule;

  protected CRM_Civirules_BAO_CiviRulesTrigger $trigger;

  /**
   * @var CRM_Civirules_Trigger
   */
  protected $triggerClass;

  /**
   * @var CRM_Civirules_Condition
   */
  protected $conditionClass;

  /**
   * Overridden parent method to perform processing before form is build
   *
   * @access public
   */
  public function preProcess() {
    $this->ruleConditionId = CRM_Utils_Request::retrieve('rule_condition_id', 'Integer');
    $this->ruleConditionName = CRM_Utils_Request::retrieve('condition_name', 'String');

    $this->ruleCondition = new CRM_Civirules_BAO_CiviRulesRuleCondition();
    $this->ruleCondition->id = $this->ruleConditionId;
    if (!$this->ruleCondition->find(true)) {
      throw new Exception('Civirules could not find ruleCondition');
    }
    $ruleConditionData = array();
    CRM_Core_DAO::storeValues($this->ruleCondition, $ruleConditionData);

    $this->condition = new CRM_Civirules_BAO_CiviRulesCondition();
    $this->rule = new CRM_Civirules_BAO_CiviRulesRule();
    $this->trigger = new CRM_Civirules_BAO_CiviRulesTrigger();

    $this->condition->id = $this->ruleCondition->condition_id;
    if (!$this->condition->find(true)) {
      throw new Exception('Civirules could not find condition');
    }

    $this->rule->id = $this->ruleCondition->rule_id;
    if (!$this->rule->find(true)) {
      throw new Exception('Civirules could not find rule');
    }

    $this->trigger->id = $this->rule->trigger_id;
    if (!$this->trigger->find(true)) {
      throw new Exception('Civirules could not find trigger');
    }

    $this->conditionClass = CRM_Civirules_BAO_CiviRulesCondition::getConditionObjectById($this->condition->id, false);
    if ($this->conditionClass) {
      $this->conditionClass->setRuleConditionData($ruleConditionData);
    }

    $this->triggerClass = CRM_Civirules_BAO_CiviRulesTrigger::getTriggerObjectByTriggerId($this->trigger->id, true);
    $this->triggerClass->setTriggerId($this->trigger->id);
    $this->triggerClass->setTriggerParams($this->rule->trigger_params ?? '');

    parent::preProcess();

    $this->setFormTitle();
    $this->assign('ruleConditionHelp', $this->getHelpText());
    $this->assign('ruleName', $this->condition->name);

    //set user context
    $session = CRM_Core_Session::singleton();
    $editUrl = CRM_Utils_System::url('civicrm/civirule/form/rule', 'action=update&id='.$this->rule->id, TRUE);
    $session->pushUserContext($editUrl);
  }

  function cancelAction() {
    if (!empty($this->getSubmittedValue('rule_condition_id')) && $this->_action == CRM_Core_Action::ADD) {
      CiviRulesRuleCondition::delete(FALSE)
        ->addWhere('id', '=', $this->getSubmittedValue('rule_condition_id'))
        ->execute();
    }
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   * @access public
   */
  public function setDefaultValues() {
    $defaultValues = array();
    $defaultValues['rule_condition_id'] = $this->ruleConditionId;
    return $defaultValues;
  }

  public function postProcess() {
    $session = CRM_Core_Session::singleton();
    $session->setStatus(E::ts("Condition '%1' parameters updated for CiviRule '%2'", [1 => $this->condition->label, 2 => $this->rule->label]), 'Condition parameters updated', 'success');

    $redirectUrl = CRM_Utils_System::url('civicrm/civirule/form/rule', 'action=update&id='.$this->rule->id, TRUE);
    $session->pushUserContext($redirectUrl);
  }

  /**
   * Method to set the form title
   *
   * @access protected
   */
  protected function setFormTitle() {
    $title = 'CiviRules Edit Condition parameters';
    $this->assign('ruleConditionHeader', E::ts("Edit Condition '%1' for CiviRule '%2'", [ 1 => $this->condition->label, 2 => $this->rule->label]));
    CRM_Utils_System::setTitle($title);
  }

  /**
   * Returns a help text for this condition.
   * The help text is shown to the administrator who is configuring the condition.
   *
   * @return string
   */
  public function getHelpText() {
    return '';
  }

}
