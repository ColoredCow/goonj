<?php
/**
 * Form controller class to manage CiviRule/Rule
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use Civi\Api4\CiviRulesRuleAction;
use Civi\Api4\CiviRulesRule;
use Civi\Api4\CiviRulesRuleCondition;
use Civi\Api4\CiviRulesRuleTag;
use Civi\Api4\CiviRulesTrigger;
use CRM_Civirules_ExtensionUtil as E;

class CRM_Civirules_Form_Rule extends CRM_Core_Form {

  protected $ruleId = NULL;

  protected CRM_Civirules_BAO_CiviRulesRule $rule;

  protected CRM_Civirules_BAO_CiviRulesTrigger $trigger;

  protected $postRuleBlock = '';

  /**
   * @var CRM_Civirules_Trigger
   */
  protected $triggerClass;

  /**
   * Function to buildQuickForm (extends parent function)
   *
   * @access public
   */
  function buildQuickForm() {
    $this->setFormTitle();
    $this->createFormElements();
    $this->assign('postRuleBlock', $this->postRuleBlock);
    parent::buildQuickForm();
  }

  /**
   * Post rule details are shown on the form just between the rule name and the
   * linked trigger
   *
   * @return string
   */
  public function getPostRuleBlock() {
    return $this->postRuleBlock;
  }

  /**
   * Post rule details are shown on the form just between the rule name and the
   * linked trigger
   *
   * @param $postRuleBlock
   */
  public function setPostRuleBlock($postRuleBlock) {
    $this->postRuleBlock = $postRuleBlock;
    $this->assign('postRuleBlock', $this->postRuleBlock);
  }

  /**
   * Function to perform processing before displaying form (overrides parent function)
   *
   * @access public
   */
  function preProcess() {
    // make sure Cancel returns to the overview
    $session = CRM_Core_Session::singleton();
    $session->pushUserContext(CRM_Utils_System::url('civicrm/civirules/form/rulesview', 'reset=1'));

    $this->ruleId = CRM_Utils_Request::retrieve('id', 'Integer');

    if (!$this->ruleId && $this->getAction() !== CRM_Core_Action::ADD) {
      CRM_Core_Error::statusBounce('Missing rule ID');
    }

    $this->rule = new CRM_Civirules_BAO_CiviRulesRule();
    $this->trigger = new CRM_Civirules_BAO_CiviRulesTrigger();

    $this->assign('trigger_edit_params', false);
    $this->triggerClass = false;
    if (!empty($this->ruleId)) {
      $this->rule->id = $this->ruleId;
      if (!$this->rule->find(TRUE)) {
        CRM_Core_Error::statusBounce('Could not find rule with ID: ' . $this->ruleId);
      }

      $this->trigger->id = $this->rule->trigger_id;
      if (!$this->trigger->find(TRUE)) {
        CRM_Core_Error::statusBounce('Could not find trigger with ID: ' . $this->rule->trigger_id);
      }

      $this->triggerClass = CRM_Civirules_BAO_CiviRulesTrigger::getTriggerObjectByTriggerId($this->trigger->id, TRUE);
      $this->triggerClass->setTriggerId($this->trigger->id);
      $this->triggerClass->setRuleId($this->rule->id);
      $this->triggerClass->setTriggerParams($this->rule->trigger_params ?? '');

      $this->assign('trigger_edit_params', $this->triggerClass->getExtraDataInputUrl($this->ruleId));
    }
    $this->assign('triggerClass', $this->triggerClass);

    $ruleConditionAddUrl = CRM_Utils_System::url('civicrm/civirule/form/rule_condition', 'reset=1&action=add&rid='.$this->ruleId, TRUE);
    $ruleActionAddUrl = CRM_Utils_System::url('civicrm/civirule/form/rule_action', 'reset=1&action=add&rule_id='.$this->ruleId, TRUE);
    $this->assign('ruleConditionAddUrl', $ruleConditionAddUrl);
    $this->assign('ruleActionAddUrl', $ruleActionAddUrl);

    $this->assign('action', $this->_action);
    $this->assign('rule', $this->rule);

    if ($this->_action == CRM_Core_Action::UPDATE) {
      $clones = civicrm_api3('CiviRuleRule', 'getclones', [
        'id' => $this->ruleId,
      ]);
      if ($clones['count'] > 0) {
        $cloneLabels = [];
        foreach ($clones['values'] as $key => $clone) {
          $cloneLabels[$key] = $clone['label'];
        }
        $this->assign('clones', implode(',', $cloneLabels));
      }
    }
  }

  /**
   * Function to perform post save processing (extends parent function)
   *
   * @access public
   */
  function postProcess() {
    $session = CRM_Core_Session::singleton();
    $userId = $session->get('userID');

    if (isset($this->_submitValues['_qf_Rule_next_cancel'])) {
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/civirules/form/rulesview', 'reset=1'));
    }
    elseif (isset($this->_submitValues['_qf_Rule_next_clone'])) {
      $result = civicrm_api3('CiviRuleRule', 'clone', [
        'id' => $this->ruleId,
      ]);
      $this->ruleId = $result['values']['clone_id'];
      $session->setStatus('Rule cloned succesfully', 'CiviRule clone', 'success');
    } else {
      $this->saveRule($this->getSubmittedValues(), $userId);
      $this->saveRuleTrigger($this->getSubmittedValues());
      $session->setStatus("Rule: '{$this->getSubmittedValue('rule_label')}' saved succesfully", 'CiviRule saved', 'success');
      if (isset($this->_submitValues['_qf_Rule_upload_done'])) {
        CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/civirules/form/rulesview'));
      }
    }

    // if add mode, set user context to form in edit mode to add conditions and actions
    if ($this->_action == CRM_Core_Action::ADD || $this->_action == CRM_Core_Action::UPDATE) {
      $editUrl = CRM_Utils_System::url('civicrm/civirule/form/rule', 'action=update&id='.$this->ruleId, TRUE);
      $session->pushUserContext($editUrl);
    }

    if ($this->getSubmittedValue('rule_trigger_select')) {
      $redirectUrl = $this->getTriggerRedirect($this->getSubmittedValue('rule_trigger_select'));
      $session->pushUserContext($redirectUrl);
    }

    parent::postProcess();
  }

  /**
   * Function to set default values (overrides parent function)
   *
   * @return array $defaults
   * @access public
   */
  function setDefaultValues() {
    $defaults = array();
    $defaults['id'] = $this->ruleId;
    switch ($this->_action) {
      case CRM_Core_Action::ADD:
        $this->setAddDefaults($defaults);
        break;
      case CRM_Core_Action::UPDATE:
        $this->setUpdateDefaults($defaults);
        break;
    }
    return $defaults;
  }

  /**
   * Function to add validation rules (overrides parent function)
   *
   * @access public
   */
  function addRules() {
    if ($this->_action != CRM_Core_Action::DELETE) {
      $this->addFormRule(array(
        'CRM_Civirules_Form_Rule',
        'validateRuleLabelExists'
      ));
    }
    if ($this->_action == CRM_Core_Action::ADD) {
      $this->addFormRule(array('CRM_Civirules_Form_Rule', 'validateTriggerEmpty'));
    }
  }

  /**
   * Function to validate that trigger is not empty in add mode
   *
   * @param array $fields
   * @return array|bool
   * @access static
   */
  static function validateTriggerEmpty($fields) {
    if (empty($fields['rule_trigger_select'])) {
      $errors['rule_trigger_select'] = E::ts('You have to select a trigger for the rule');
      return $errors;
    }
    return TRUE;
  }

  /**
   * Function to validate if rule label already exists
   *
   * @param array $fields
   * @return array|bool
   * @access static
   */
  static function validateRuleLabelExists($fields) {
    /*
     * if id not empty, edit mode. Check if changed before check if exists
     */
    if (!empty($fields['id']) && $fields['id'] != 'RuleId') {

      /*
       * check if values have changed against database label
       */
      $currentLabel = CRM_Civirules_BAO_CiviRulesRule::getRuleLabelWithId($fields['id']);
      if ($fields['rule_label'] != $currentLabel &&
        CRM_Civirules_BAO_CiviRulesRule::labelExists($fields['rule_label']) == TRUE) {
        $errors['rule_label'] = E::ts('There is already a rule with this name');
        return $errors;
      }
    } else {
      if (CRM_Civirules_BAO_CiviRulesRule::labelExists($fields['rule_label']) == TRUE) {
        $errors['rule_label'] = E::ts('There is already a rule with this name');
        return $errors;
      }
    }
    return TRUE;
  }

  /**
   * Function to add the form elements
   *
   * @access protected
   */
  protected function createFormElements() {
    $this->add('hidden', 'id', E::ts('RuleId'), array('id' => 'ruleId'));
    if ($this->_action != CRM_Core_Action::DELETE) {
      $this->add('text', 'rule_label', E::ts('Name'), array('size' => CRM_Utils_Type::HUGE), TRUE);
      $this->add('text', 'rule_description', E::ts('Description'), array('size' => 100, 'maxlength' => 256));
      $this->add('wysiwyg', 'rule_help_text', E::ts('Help text with purpose of rule'), array('rows' => 6, 'cols' => 80));
      $this->add('select2', 'rule_tag_id', E::ts('Civirule Tag(s)'), Civi::entity('CiviRulesRuleTag')->getOptions('rule_tag_id'), FALSE,
        ['id' => 'rule_tag_id', 'multiple' => 'multiple', 'class' => 'huge']);
      $this->add('checkbox', 'rule_is_active', E::ts('Enabled'));
      $this->add('text', 'rule_created_date', E::ts('Created Date'));
      $this->add('text', 'rule_created_contact', E::ts('Created By'));

      $triggerList = CiviRulesTrigger::get(FALSE)
        ->addSelect('id', 'label', 'name', 'class_name', 'object_name', 'op')
        ->addOrderBy('label', 'ASC')
        ->addWhere('is_active', '=',TRUE)
        ->execute()
        ->indexBy('id');
      foreach ($triggerList as $id => $detail) {
        $description = '';
        if (!empty($detail['class_name'])) {
          try {
            $description = (new $detail['class_name']($detail))->getHelpText('triggerDescription');
          }
          catch (Throwable $e) {
            // Do nothing, we'll continue without description
          }
        }
        $triggers[] = [
          'id' => $id,
          'text' => $detail['label'],
          'description' => $description,
        ];
      }

      if ($this->getAction() === CRM_Core_Action::ADD) {
        $this->add('select2', 'rule_trigger_select', E::ts('Select Trigger'), $triggers, TRUE, ['class' => 'huge']);
      }
      if ($this->_action == CRM_Core_Action::UPDATE) {
        $this->createUpdateFormElements();
      }
    }
    if ($this->_action == CRM_Core_Action::ADD) {
        $this->addButtons(array(
        array('type' => 'next', 'name' => E::ts('Next'), 'isDefault' => TRUE),
        array('type' => 'cancel', 'name' => E::ts('Cancel'))));
    } else {
      $this->addButtons(array(
        array('type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE),
        [
          'type' => 'upload',
          'name' => E::ts('Save and Done'),
          'subName' => 'done',
        ],
        array('type' => 'next', 'name' => E::ts('Clone'), 'subName' => 'clone', 'icon' => 'fa-creative-commons'),
        array('type' => 'next', 'name' => E::ts('Close'), 'subName' => 'cancel', 'icon' => 'fa-close')));
    }
  }

  /**
   * Function to add the form elements specific for the update action
   */
  protected function createUpdateFormElements() {
    $this->add('text', 'rule_trigger_label', '', array('size' => CRM_Utils_Type::HUGE));
    $this->assign('ruleConditions', $this->getRuleConditions());
  }

  /**
   * Function to set the form title based on action and data coming in
   *
   * @access protected
   */
  protected function setFormTitle() {
    $title = E::ts('CiviRules %1 Rule', [1 => ucfirst(CRM_Core_Action::description($this->_action))]);
    CRM_Utils_System::setTitle($title);
  }

  /**
   * Function to set default values if action is add
   *
   * @param array $defaults
   * @access protected
   */
  protected function setAddDefaults(&$defaults) {
    $defaults['rule_is_active'] = 1;
    $defaults['rule_created_date'] = date('d-m-Y');
    $session = CRM_Core_Session::singleton();
    $defaults['rule_created_contact'] = CRM_Civirules_Utils::getContactName($session->get('userID'));
  }

  /**
   * Function to set default values if action is update
   *
   * @param array $defaults
   * @access protected
   */
  protected function setUpdateDefaults(&$defaults) {
    if (empty($this->ruleId)) {
      return;
    }
    $ruleData = CiviRulesRule::get(FALSE)
      ->addWhere('id', '=', $this->ruleId)
      ->execute()
      ->first();
    if (empty($ruleData)) {
      return;
    }
    $defaults['rule_label'] = $ruleData['label'];
    // get all tags for rule
    $defaultRuleTags = [];
    $ruleTags = \Civi\Api4\CiviRulesRuleTag::get(FALSE)
      ->addWhere('rule_id', '=', $this->ruleId)
      ->execute();
    foreach ($ruleTags as $ruleTag) {
      $defaultRuleTags[] = $ruleTag['rule_tag_id'];
    }
    if (!empty($defaultRuleTags)) {
      $defaults['rule_tag_id'] = $defaultRuleTags;
    }
    if (isset($ruleData['description'])) {
      $defaults['rule_description'] = $ruleData['description'];
    }
    if (isset($ruleData['help_text'])) {
      $defaults['rule_help_text'] = $ruleData['help_text'];
    }
    $defaults['rule_is_active'] = $ruleData['is_active'];
    $defaults['rule_created_date'] = date('d-m-Y', strtotime($ruleData['created_date']));
    $defaults['rule_created_contact'] = CRM_Civirules_Utils::getContactName($ruleData['created_user_id']);
    if (!empty($ruleData['trigger_id'])) {
      $defaults['rule_trigger_label'] = CRM_Civirules_BAO_CiviRulesTrigger::getTriggerLabelWithId($ruleData['trigger_id']);
    }
  }

  /**
   * Function to get the rule conditions for the rule
   *
   * @return array $ruleConditions
   */
  protected function getRuleConditions() {
    $ruleConditions = CiviRulesRuleCondition::get(FALSE)
      ->addWhere('rule_id', '=', $this->ruleId)
      ->addWhere('is_active', '=', 1)
      ->addOrderBy('weight', 'ASC')
      ->addOrderBy('id', 'ASC')
      ->execute()
      ->indexBy('id');
    foreach ($ruleConditions as $ruleConditionId => $ruleCondition) {
      $conditionClass = CRM_Civirules_BAO_CiviRulesCondition::getConditionObjectById($ruleCondition['condition_id']);
      $conditionClass->setRuleConditionData($ruleCondition);
      $ruleConditions[$ruleConditionId]['name'] = CRM_Civirules_BAO_CiviRulesCondition::getConditionLabelWithId($ruleCondition['condition_id']);
      $ruleConditions[$ruleConditionId]['actions'] = $this->setRuleConditionActions($ruleConditionId, $conditionClass);
      $ruleConditions[$ruleConditionId]['formattedConditionParams'] = $conditionClass->userFriendlyConditionParams();
    }
    return $ruleConditions;
  }

  /**
   * Function to set the actions for each rule condition
   *
   * @param int $ruleConditionId
   * @param CRM_Civirules_Condition $condition
   * @return array
   * @access protected
   */
  protected function setRuleConditionActions($ruleConditionId, CRM_Civirules_Condition $condition) {
    $conditionActions = array();

    $editUrl = $condition->getExtraDataInputUrl($ruleConditionId);
    if (!empty($editUrl)) {
      $conditionActions[] = '<a class="action-item" title="Edit" href="'.$editUrl.'">'.E::ts('Edit').'</a>';
    }

    $removeUrl = CRM_Utils_System::url('civicrm/civirule/form/rule_condition', 'reset=1&action=delete&rid='
      .$this->ruleId.'&id='.$ruleConditionId);
    $conditionActions[] = '<a class="action-item" title="Remove" href="'.$removeUrl.'">'.E::ts('Remove').'</a>';
    return $conditionActions;
  }

  /**
   * Function to save rule
   *
   * @param array $formValues
   * @param int $userId
   */
  protected function saveRule(array $formValues, int $userId) {
    if ($this->_action == CRM_Core_Action::ADD) {
      $ruleParams = [
        'created_user_id' => $userId
      ];
    } else {
      $ruleParams = [
        'modified_user_id' => $userId,
        'id' => $formValues['id']
      ];
    }
    $ruleParams['label'] = $formValues['rule_label'] ?? '';
    $ruleParams['description'] = $formValues['rule_description'] ?? '';
    $ruleParams['help_text'] = $formValues['rule_help_text'] ?? '';
    $ruleParams['name'] = CRM_Civirules_Utils::buildNameFromLabel($formValues['rule_label']);
    $ruleParams['is_active'] = $formValues['rule_is_active'] ?? 0;
    $this->ruleId = CiviRulesRule::save(FALSE)
      ->setRecords([$ruleParams])
      ->execute()
      ->first()['id'];
    // first delete all tags for the rule if required then save new ones
    CiviRulesRuleTag::delete(FALSE)
      ->addWhere('rule_id', '=', $this->ruleId)
      ->execute();
    if (!empty($formValues['rule_tag_id'])) {
      foreach (explode(',', $formValues['rule_tag_id']) as $ruleTagId) {
        $ruleTag = [
          'rule_id' => $this->ruleId,
          'rule_tag_id' => $ruleTagId
        ];
        $ruleTags[] = $ruleTag;
      }
      if (!empty($ruleTags)) {
        CiviRulesRuleTag::save(FALSE)
          ->setRecords($ruleTags)
          ->execute();
      }
    }
  }

  /**
   * Function to link a trigger to a rule
   *
   * @param array $formValues
   */
  protected function saveRuleTrigger($formValues) {
    if (isset($formValues['rule_trigger_select'])) {
      CiviRulesRule::update(FALSE)
        ->addValue('trigger_id', $formValues['rule_trigger_select'])
        ->addWhere('id', '=', $this->ruleId)
        ->execute();
    }
  }

  /**
   * Returns the url for redirect
   *
   * @param $triggerId
   * @return bool|string url
   */
  protected function getTriggerRedirect($triggerId) {
    $trigger = CRM_Civirules_BAO_CiviRulesTrigger::getTriggerObjectByTriggerId($triggerId, true);
    $redirectUrl = $trigger->getExtraDataInputUrl($this->ruleId);
    if (!empty($redirectUrl)) {
      return $redirectUrl;
    }
    return false;
  }
}
