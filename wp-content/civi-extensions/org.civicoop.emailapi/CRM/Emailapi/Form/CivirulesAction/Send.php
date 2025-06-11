<?php

use CRM_Emailapi_ExtensionUtil as E;
/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Emailapi_Form_CivirulesAction_Send extends CRM_CivirulesActions_Form_Form {

  protected bool $hasCase;

  /**
   * Overridden parent method to do pre-form building processing
   *
   * @throws Exception when action or rule action not found
   * @access public
   */
  public function preProcess() {
    parent::preProcess();

    $providedEntities = $this->triggerClass->getProvidedEntities();
    $this->hasCase = isset($providedEntities['Case']);
  }

  /**
   * Method to get location types
   */
  protected function getLocationTypes() : array {
    $return = ['' => E::ts('-- please select --')];
    $return += \Civi\Api4\LocationType::get(FALSE)
      ->addWhere('is_active', '=', TRUE)
      ->addOrderBy('display_name', 'ASC')
      ->execute()
      ->indexBy('id')
      ->column('display_name');
    return $return;
  }


  /**
   * Function to get from email addresses
   *
   * @return array $return
   * @access protected
   */
  protected function getFromEmails() {
    $return = ['' => E::ts('-- please select --')];

    try {
      $fromEmails = \Civi\Api4\OptionValue::get(FALSE)
        ->addSelect('value', 'label')
        ->addWhere('option_group_id:name', '=', 'from_email_address')
        ->addWhere('is_active', '=', TRUE)
        ->addOrderBy('value', 'ASC')
        ->execute();

      foreach ($fromEmails as $optionValue) {
        $return[(int) $optionValue['value']] = htmlspecialchars($optionValue['label']);
      }
    }
    catch (CRM_Core_Exception $ex) {
    }
    ksort($return);
    return $return;
  }

  public function buildQuickForm() {
    $this->setFormTitle();
    $this->registerRule('emailList', 'callback', 'emailList', 'CRM_Utils_Rule');
    $this->add('hidden', 'rule_action_id');
    $this->add('text', 'from_name', E::ts('From Name'));
    $this->add('text', 'from_email', E::ts('From Email'));
    $this->addRule("from_email", E::ts('Email is not valid.'), 'email');
    $this->add('advcheckbox','alternative_receiver', E::ts('Send to Alternative Email Address'));
    $this->add('text', 'alternative_receiver_address', E::ts('Alternative Email Address'));
    $this->addRule("alternative_receiver_address", E::ts('Email is not valid.'), 'email');
    $this->add('text', 'cc', E::ts('Cc to'));
    $this->addRule("cc", E::ts('Email is not valid.'), 'emailList');
    $this->add('text', 'bcc', E::ts('Bcc to'));
    $this->addRule("bcc", E::ts('Email is not valid.'), 'emailList');
    $this->addEntityRef('template_id', E::ts('Message Template'),[
      'entity' => 'MessageTemplate',
      'api' => [
        'label_field' => 'msg_title',
        'search_field' => 'msg_title',
        'params' => [
          'is_active' => 1,
          'workflow_id' => ['IS NULL' => 1],
        ]
      ],
      'placeholder' => E::ts(' - select - ')
    ], TRUE);
    $this->add('checkbox','disable_smarty', E::ts('Disable Smarty'));
    $this->add('select', 'location_type_id', E::ts('Location Type (if you do not want primary email address)'), $this->getLocationTypes());
    if ($this->hasCase) {
      $this->add('checkbox','file_on_case', E::ts('File Email on Case'));
    }
    $this->assign('has_case', $this->hasCase);

    $this->add('select', 'from_email_option', E::ts('From Email Address'), $this->getFromEmails());

    // add buttons
    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE,],
      ['type' => 'cancel', 'name' => E::ts('Cancel')]
    ]);
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   * @access public
   */
  public function setDefaultValues() {
    $defaultValues = [];
    $defaultValues['rule_action_id'] = $this->ruleActionId;
    $data = $this->unserializeParams();
    if (!empty($data['from_name'])) {
      $defaultValues['from_name'] = $data['from_name'];
    }
    if (!empty($data['from_email'])) {
      $defaultValues['from_email'] = $data['from_email'];
    }
    if (!empty($data['template_id'])) {
      $defaultValues['template_id'] = $data['template_id'];
    }
    if (!empty($data['location_type_id'])) {
      $defaultValues['location_type_id'] = $data['location_type_id'];
    }
    if (!empty($data['disable_smarty'])) {
      $defaultValues['disable_smarty'] = $data['disable_smarty'];
    }
    if (!empty($data['alternative_receiver_address'])) {
      $defaultValues['alternative_receiver_address'] = $data['alternative_receiver_address'];
      $defaultValues['alternative_receiver'] = TRUE;
    }
    if (!empty($data['cc'])) {
      $defaultValues['cc'] = $data['cc'];
    }
    if (!empty($data['bcc'])) {
      $defaultValues['bcc'] = $data['bcc'];
    }
    $defaultValues['file_on_case'] = FALSE;
    if (!empty($data['file_on_case'])) {
      $defaultValues['file_on_case'] = TRUE;
    }

    if (!empty($data['from_email_option'])) {
      $defaultValues['from_email_option'] = $data['from_email_option'];
    }

    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess($data = []) {
    $data['from_name'] = $this->getSubmittedValue('from_name');
    $data['from_email'] = $this->getSubmittedValue('from_email');
    $data['template_id'] = $this->getSubmittedValue('template_id');
    $data['disable_smarty'] = $this->getSubmittedValue('disable_smarty') ?? FALSE;
    $data['location_type_id'] = $this->getSubmittedValue('location_type_id');
    $data['from_email_option'] = $this->getSubmittedValue('from_email_option');
    if (!empty($this->getSubmittedValue('location_type_id'))) {
      $data['alternative_receiver_address'] = '';
    }
    else {
      $data['alternative_receiver_address'] = '';
      if (!empty($this->getSubmittedValue('alternative_receiver') && !empty($this->getSubmittedValue('alternative_receiver_address')))) {
        $data['alternative_receiver_address'] = $this->getSubmittedValue('alternative_receiver_address');
      }
    }
    $data['cc'] = '';
    if (!empty($this->getSubmittedValue('cc'))) {
      $data['cc'] = $this->getSubmittedValue('cc');
    }
    $data['bcc'] = '';
    if (!empty($this->getSubmittedValue('bcc'))) {
      $data['bcc'] = $this->getSubmittedValue('bcc');
    }
    $data['file_on_case'] = FALSE;
    if (!empty($this->getSubmittedValue('file_on_case'))) {
      $data['file_on_case'] = TRUE;
    }

    $ruleAction = new CRM_Civirules_BAO_RuleAction();
    $ruleAction->id = $this->ruleActionId;
    $ruleAction->action_params = serialize($data);
    $ruleAction->save();

    parent::postProcess();

    $redirectUrl = CRM_Utils_System::url('civicrm/civirule/form/rule', 'action=update&id='.$this->ruleAction->rule_id, TRUE);
    CRM_Core_Session::singleton()->pushUserContext($redirectUrl);
  }

  /**
   * CRM_Civirules_BAO_CiviRulesRuleAction::unserializeParams() does not exist in CiviRules < 3.17
   *
   * @return array
   */
  protected function unserializeParams(): array {
    if (method_exists($this->ruleAction, 'unserializeParams')) {
      return $this->ruleAction->unserializeParams();
    }
    else {
      // Our version of the same function if it doesn't exist
      if (!empty($this->ruleAction->action_params) && !is_array($this->ruleAction->action_params)) {
        return unserialize($this->ruleAction->action_params);
      }
      return [];
    }
  }

}
