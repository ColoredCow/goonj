<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesTrigger_Form_Form extends CRM_Core_Form {

  /**
   * @var bool
   */
  protected $ruleId = false;

  /**
   * @var \CRM_Civirules_BAO_CiviRulesRule
   */
  protected $rule;

  /**
   * @var \CRM_Civirules_BAO_CiviRulesTrigger
   */
  protected $trigger;

  /**
   * @var CRM_Civirules_Trigger
   */
  protected $triggerClass;

  /**
   * @var array The trigger params
   */
  protected array $triggerParams = [];

  /**
   * Overridden parent method to perform processing before form is build
   */
  public function preProcess() {
    $this->ruleId = CRM_Utils_Request::retrieve('rule_id', 'Integer');

    $this->rule = new CRM_Civirules_BAO_CiviRulesRule();
    $this->trigger = new CRM_Civirules_BAO_CiviRulesTrigger();

    $this->rule->id = $this->ruleId;
    if (!$this->rule->find(true)) {
      throw new Exception('Civirules could not find rule');
    }

    $this->trigger->id = $this->rule->trigger_id;
    if (!$this->trigger->find(true)) {
      throw new Exception('Civirules could not find trigger');
    }

    $this->triggerClass = CRM_Civirules_BAO_CiviRulesTrigger::getTriggerObjectByTriggerId($this->trigger->id, true);
    $this->triggerClass->setTriggerId($this->trigger->id);
    $this->triggerClass->setRuleId($this->rule->id);
    $this->triggerClass->setTriggerParams($this->rule->trigger_params ?? '');

    parent::preProcess();

    $this->setFormTitle();

    if (method_exists($this, 'getHelpText')) {
      // Old location, should be moved to main trigger class
      $helpText = $this->getHelpText();
    }
    elseif (method_exists($this->triggerClass, 'getHelpText')) {
      // This is the correct location for getHelpText();
      $helpText = $this->triggerClass->getHelpText('triggerParamsHelp');
    }
    $this->assign('ruleTriggerHelp', $helpText ?? '');

    if ($this->supportsMultipleTriggerOps()) {
      $this->add('select2', 'trigger_op', E::ts('Trigger on'), self::getTriggerOptions(), TRUE, ['multiple' => TRUE]);
    }

    //set user context
    $session = CRM_Core_Session::singleton();
    $editUrl = CRM_Utils_System::url('civicrm/civirule/form/rule', 'action=update&id=' . $this->rule->id, TRUE);
    $session->pushUserContext($editUrl);
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   */
  public function setDefaultValues() {
    $defaultValues = [];
    $defaultValues['rule_id'] = $this->ruleId;
    if ($this->supportsMultipleTriggerOps()) {
      $defaultValues['trigger_op'] = $this->triggerClass->getTriggerParams()['trigger_op'] ?? 'create';
    }
    return $defaultValues;
  }

  public function postProcess() {
    if ($this->supportsMultipleTriggerOps()) {
      $this->triggerParams['trigger_op'] = $this->getSubmittedValue('trigger_op');
    }
    $this->rule->trigger_params = serialize($this->triggerParams);
    $this->rule->save();

    $session = CRM_Core_Session::singleton();
    $session->setStatus('Rule '.$this->rule->label.' parameters updated', 'Rule parameters updated', 'success');

    $redirectUrl = CRM_Utils_System::url('civicrm/civirule/form/rule', 'action=update&id=' . $this->rule->id, TRUE);
    CRM_Utils_System::redirect($redirectUrl);
  }

  /**
   * Method to set the form title
   */
  protected function setFormTitle() {
    $title = E::ts('Rule: %1', [1 => $this->rule->label]);
    $this->assign('ruleTriggerHeader', E::ts('Edit "%1" trigger parameters.', [1 => $this->trigger->label]));
    CRM_Utils_System::setTitle($title);
  }

  protected function supportsMultipleTriggerOps(): bool {
    return str_contains($this->trigger->op, '|');
  }

  /**
   * Get the valid trigger options (for Post triggers)
   * @return array[]
   */
  public static function getTriggerOptions(): array {
    return [
      [
        'id' => 'create',
        'text' => E::ts('Create'),
      ],
      [
        'id' => 'edit',
        'text' => E::ts('Edit/Update'),
      ],
    ];
  }

}
