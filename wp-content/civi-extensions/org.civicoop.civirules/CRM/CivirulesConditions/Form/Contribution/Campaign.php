<?php
/**
 * Class for CiviRules Condition Contribution Campaign Form
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 18 May 2016
 * @license AGPL-3.0
 */

class CRM_CivirulesConditions_Form_Contribution_Campaign extends CRM_CivirulesConditions_Form_Form {

  /**
   * Overridden parent method to build form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');
    $campaignList = array();
    $campaigns = civicrm_api3('Campaign', 'Get', array(
      'is_active' => 1, 'options' => array('limit' => 99999)));
    foreach ($campaigns['values'] as $campaign) {
      $campaignList[$campaign['id']] = $campaign['title'];
    }
    asort($campaignList);
    $this->add('select', 'campaign_id', ts('Campaign(s)'), $campaignList, false,
      array('id' => 'campaign_ids', 'multiple' => 'multiple','class' => 'crm-select2'));
    $this->add('select', 'operator', ts('Operator'), array('is one of', 'is NOT one of', 'has NO campaign', 'has any campaign'), true);

    $this->addButtons(array(
      array('type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,),
      array('type' => 'cancel', 'name' => ts('Cancel'))));
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   * @access public
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->ruleCondition->unserializeParams();
    if (!empty($data['campaign_id'])) {
      $defaultValues['campaign_id'] = $data['campaign_id'];
    }
    if (!empty($data['operator'])) {
      $defaultValues['operator'] = $data['operator'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to validate form data after submission
   *
   * @return bool
   *   true if no errors were found
   * @throws    HTML_QuickForm_Error
   * @access public
   */
  public function validate() {
    $error = parent::validate();
    if (!$error) {
      return FALSE;
    }

    $values = $this->getSubmittedValues();
    if (empty($values['campaign_id']) && $values['operator'] < '2') {
      $this->_errors['campaign_id'] = ts('Please select at least one campaign');
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   * @access public
   */
  public function postProcess() {
    $data['campaign_id'] = $this->_submitValues['campaign_id'];
    $data['operator'] = $this->_submitValues['operator'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();
    parent::postProcess();
  }
}