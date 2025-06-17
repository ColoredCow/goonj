<?php

use CRM_Civirules_ExtensionUtil as E;
/**
 * Class for CiviRules Condition Contribution Donor Is Recurring
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license AGPL-3.0
 */
class CRM_CivirulesConditions_Form_ContributionRecur_DonorIsRecurring extends CRM_CivirulesConditions_Form_Form {

  /**
   * Returns a help text for this condition.
   * The help text is shown to the administrator who is configuring the condition.
   *
   * @return string
   */
  public function getHelpText() {
    return E::ts('This condition checks if the contact has any recurring contributions with no end-date or and end-date later than today. It does NOT check the status of a recurring contribution and does not work with test entities.');
  }

  /**
   * Overridden parent method to build form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');
    $this->addElement('checkbox', 'has_recurring', ts('Donor has recurring contributions?'));
    $this->addButtons(array(
      array('type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,),
      array('type' => 'cancel', 'name' => ts('Cancel'))));
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->ruleCondition->unserializeParams();
    if (!empty($data['has_recurring'])) {
      $defaultValues['has_recurring'] = $data['has_recurring'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   */
  public function postProcess() {
    if (isset($this->_submitValues['has_recurring'])) {
      $data['has_recurring'] = $this->_submitValues['has_recurring'];
    } else {
      $data['has_recurring'] = 0;
    }
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();

    parent::postProcess();
  }

}
