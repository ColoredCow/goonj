<?php
/**
 * Form controller class to manage CiviRule/RuleAction
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_Civirules_ExtensionUtil as E;
use Civi\Api4\CiviRulesAction;
use Civi\Api4\CiviRulesRuleAction;

class CRM_Civirules_Form_RuleAction extends CRM_Core_Form {

  protected $ruleId = NULL;

  protected $ruleActionId;

  protected CRM_Civirules_BAO_CiviRulesRuleAction $ruleAction;

  protected CRM_Civirules_BAO_CiviRulesAction $action;

  protected CRM_Civirules_BAO_CiviRulesRule $rule;

  /**
   * If TRUE, only edit the delay params
   * @var bool
   */
  protected bool $editDelay = FALSE;

  /**
   * Function to buildQuickForm (extends parent function)
   *
   * @access public
   */
  function buildQuickForm() {
    $this->setFormTitle();
    $this->createFormElements();
    parent::buildQuickForm();
  }

  /**
   * Function to perform processing before displaying form (overrides parent function)
   *
   * @access public
   */
  function preProcess() {
    $this->ruleId = CRM_Utils_Request::retrieve('rule_id', 'Integer');
    $this->ruleActionId = CRM_Utils_Request::retrieve('id', 'Integer');
    $this->editDelay = CRM_Utils_Request::retrieveValue('editdelay', 'Boolean') ?? FALSE;

    if (!$this->ruleId && $this->getAction()) {
      CRM_Core_Error::statusBounce('Missing rule ID');
    }
    if (!$this->ruleActionId && $this->getAction() !== CRM_Core_Action::ADD) {
      CRM_Core_Error::statusBounce('Missing RuleAction ID');
    }

    $this->rule = new CRM_Civirules_BAO_CiviRulesRule();
    $this->rule->id = $this->ruleId;
    $this->rule->find(true);

    if ($this->ruleActionId) {
      $this->ruleAction = new CRM_Civirules_BAO_CiviRulesRuleAction();
      $this->ruleAction->id = $this->ruleActionId;
      if (!$this->ruleAction->find(true)) {
        throw new Exception('Civirules could not find ruleAction (RuleAction)');
      }

      $this->action = new CRM_Civirules_BAO_CiviRulesAction();
      $this->action->id = $this->ruleAction->action_id;
      if (!$this->action->find(true)) {
        throw new Exception('Civirules could not find action');
      }

      $this->assign('action_label', $this->action->label);
    }
  }

  /**
   * Function to perform post save processing (extends parent function)
   *
   * @access public
   */
  function postProcess() {
    $saveParams = [];
    $saveParams['rule_id'] = $this->getSubmittedValue('rule_id');
    $saveParams['delay'] = NULL;
    $saveParams['ignore_condition_with_delay'] = '0';
    if (!empty($this->getSubmittedValue('rule_action_select'))) {
      if (!isset($this->ruleAction)) {
        $this->ruleAction = new CRM_Civirules_BAO_CiviRulesRuleAction();
      }
      $this->ruleAction->action_id = $this->getSubmittedValue('rule_action_select');
      $saveParams['action_id'] = $this->getSubmittedValue('rule_action_select');
    }
    if ($this->ruleActionId) {
      $saveParams['id'] = $this->ruleActionId;
    }

    if (!empty($this->_submitValues['delay_select'])) {
      $delayClass = CRM_Civirules_Delay_Factory::getDelayClassByName($this->_submitValues['delay_select']);
      $delayClass->setValues($this->_submitValues, '', $this->rule);
      $saveParams['delay'] = serialize($delayClass);
      if (!empty($this->_submitValues['ignore_condition_with_delay'])) {
        $saveParams['ignore_condition_with_delay'] = '1';
      }
    }

    $ruleAction = CiviRulesRuleAction::save(FALSE)
      ->setRecords([$saveParams])
      ->execute()
      ->first();

    $session = CRM_Core_Session::singleton();
    $action = CRM_Civirules_BAO_CiviRulesAction::getActionObjectById($this->ruleAction->action_id, true);
    $redirectUrl = $action->getExtraDataInputUrl($ruleAction['id']);
    if (empty($redirectUrl) || $this->editDelay) {
      $redirectUrl = CRM_Utils_System::url('civicrm/civirule/form/rule', 'action=update&id=' . $this->getSubmittedValue('rule_id'), TRUE);
      if (empty($this->ruleActionId)) {
        $session->setStatus('Action added to CiviRule ' . CRM_Civirules_BAO_CiviRulesRule::getRuleLabelWithId($this->getSubmittedValue('rule_id')),
          'Action added', 'success');
      }
    }
    else {
      // Redirect to action configuration (required to redirect popup without closing
      CRM_Utils_System::redirect($redirectUrl);
    }

    // This will allow popup to close
    $session->pushUserContext($redirectUrl);
  }

  /**
   * Function to add the form elements
   *
   * @access protected
   */
  protected function createFormElements() {
    $this->add('hidden', 'rule_id');
    if ($this->ruleActionId) {
      $this->add('hidden', 'id');
    }

    $actionList = CiviRulesAction::get(FALSE)
      ->addSelect('id', 'label', 'name', 'class_name')
      ->addOrderBy('label', 'ASC')
      ->addWhere('is_active', '=',TRUE)
      ->execute()
      ->indexBy('id');
    foreach ($actionList as $id => $detail) {
      $description = '';
      if (!empty($detail['class_name'])) {
        try {
          $description = (new $detail['class_name']($detail))->getHelpText('actionDescription');
        }
        catch (Throwable $e) {
          // Do nothing, we'll continue without description
        }
      }
      $actions[] = [
        'id' => $id,
        'text' => $detail['label'],
        'description' => $description,
      ];
    }

    if ($this->getAction() === CRM_Core_Action::ADD) {
      $this->add('select2', 'rule_action_select', E::ts('Select Action'), $actions, TRUE, ['class' => 'huge']);
    }

    $delayList = [' - No Delay - '] + CRM_Civirules_Delay_Factory::getOptionList();
    $this->add('select', 'delay_select', E::ts('Delay action to'), $delayList);
    foreach(CRM_Civirules_Delay_Factory::getAllDelayClasses() as $delay_class) {
      $delay_class->addElements($this, '', $this->rule);
    }
    $this->assign('delayClasses', CRM_Civirules_Delay_Factory::getAllDelayClasses());
    $this->assign('delayPrefix', '');
    $this->add('checkbox', 'ignore_condition_with_delay', E::ts("Don't recheck condition upon processing of delayed action"));

    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Next'), 'isDefault' => TRUE,],
      ['type' => 'cancel', 'name' => E::ts('Cancel')]
    ]);
  }

  public function setDefaultValues() {
    $defaults['rule_id'] = $this->ruleId;

    foreach(CRM_Civirules_Delay_Factory::getAllDelayClasses() as $delay_class) {
      $delay_class->setDefaultValues($defaults, '', $this->rule);
    }

    if (!empty($this->ruleActionId)) {
      $defaults['rule_action_select'] = $this->ruleAction->action_id;
      $defaults['id'] = $this->ruleActionId;
      if (isset($this->ruleAction->ignore_condition_with_delay)) {
        $defaults['ignore_condition_with_delay'] = $this->ruleAction->ignore_condition_with_delay;
      }

      if (!empty($this->ruleAction->delay)) {
        $delayClass = unserialize($this->ruleAction->delay);
      }
      if (isset($delayClass)) {
        $defaults['delay_select'] = get_class($delayClass);
        foreach($delayClass->getValues('', $this->rule) as $key => $val) {
          $defaults[$key] = $val;
        }
      }

    }

    return $defaults;
  }

  /**
   * Function to set the form title based on action and data coming in
   *
   * @access protected
   */
  protected function setFormTitle() {
    $title = 'CiviRules Add Action';
    $this->assign('ruleActionHeader', 'Add Action to CiviRule '.CRM_Civirules_BAO_CiviRulesRule::getRuleLabelWithId($this->ruleId));
    CRM_Utils_System::setTitle($title);
  }

  /**
   * Function to add validation action rules (overrides parent function)
   *
   * @access public
   */
  public function addRules() {
    if (empty($this->ruleActionId)) {
      $this->addFormRule([
        'CRM_Civirules_Form_RuleAction',
        'validateRuleAction'
      ]);
    }
    $this->addFormRule([
      'CRM_Civirules_Form_RuleAction',
      'validateDelay'
    ]);
  }

  /**
   * Function to validate value of rule action form
   *
   * @param array $fields
   * @return array|bool
   * @access public
   * @static
   */
  static function validateRuleAction($fields) {
    $errors = [];
    if (isset($fields['rule_action_select']) && empty($fields['rule_action_select'])) {
      $errors['rule_action_select'] = E::ts('Action has to be selected, press CANCEL if you do not want to add an action');
    } else {
      $actionClass = CRM_Civirules_BAO_CiviRulesAction::getActionObjectById($fields['rule_action_select'], false);
      if (!$actionClass) {
        $errors['rule_action_select'] = E::ts('Not a valid action, action class is missing');
      } else {
        $rule = new CRM_Civirules_BAO_CiviRulesRule();
        $rule->id = $fields['rule_id'];
        $rule->find(TRUE);
        $trigger = new CRM_Civirules_BAO_CiviRulesTrigger();
        $trigger->id = $rule->trigger_id;
        $trigger->find(TRUE);

        $triggerObject = CRM_Civirules_BAO_CiviRulesTrigger::getPostTriggerObjectByClassName($trigger->class_name, TRUE);
        $triggerObject->setTriggerId($trigger->id);
        if (!$actionClass->doesWorkWithTrigger($triggerObject, $rule)) {
          $errors['rule_action_select'] = E::ts('This action is not available with trigger %1', [1 => $trigger->label]);
        }
      }
    }

    if (count($errors)) {
      return $errors;
    }

    return TRUE;
  }

  /**
   * Function to validate value of the delay
   *
   * @param array $fields
   * @return array|bool
   * @access public
   * @static
   */
  static function validateDelay($fields) {
    $errors = [];
    if (!empty($fields['delay_select'])) {
      $ruleActionId = CRM_Utils_Request::retrieve('rule_action_id', 'Integer');
      $ruleAction = new CRM_Civirules_BAO_CiviRulesRuleAction();
      $ruleAction->id = $ruleActionId;
      $ruleAction->find(true);
      $rule = new CRM_Civirules_BAO_CiviRulesRule();
      $rule->id = $ruleAction->rule_id;
      $rule->find(true);

      $delayClass = CRM_Civirules_Delay_Factory::getDelayClassByName($fields['delay_select']);
      $delayClass->validate($fields, $errors, '', $rule);
    }

    if (count($errors)) {
      return $errors;
    }

    return TRUE;
  }
}
