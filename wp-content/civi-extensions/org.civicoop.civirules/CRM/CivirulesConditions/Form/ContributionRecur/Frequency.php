<?php
use CRM_Civirules_ExtensionUtil as E;
/**
 * Class for CiviRules Condition Contribution Frequency
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_CivirulesConditions_Form_ContributionRecur_Frequency extends CRM_CivirulesConditions_Form_Form {

  /**
   * Overridden parent method to build form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');
    $frequencyUnits = CRM_Civirules_Utils::getFrequencyUnits();
    asort($frequencyUnits);
    $this->add('select', 'frequency_unit', E::ts('Frequency Unit'), $frequencyUnits, TRUE,
      ['id' => 'frequency_unit_id','class' => 'crm-select2']);
    $this->add('text', 'frequency_interval', E::ts("Interval"), [], TRUE);
    $this->addRule('frequency_interval', "The interval has to be an integer.", "numeric");
    $this->addRule('frequency_interval', "The interval has to be an integer.", "nopunctuation");
    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE,],
      ['type' => 'cancel', 'name' => E::ts('Cancel')]]);
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
    if (!empty($data['frequency_unit'])) {
      $defaultValues['frequency_unit'] = $data['frequency_unit'];
    }
    if (!empty($data['frequency_interval'])) {
      $defaultValues['frequency_interval'] = $data['frequency_interval'];
    }
    else {
      $defaultValues['frequency_interval'] = 1;
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   * @access public
   */
  public function postProcess() {
    $data['frequency_unit'] = $this->_submitValues['frequency_unit'];
    $data['frequency_interval'] = $this->_submitValues['frequency_interval'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();
    parent::postProcess();
  }
}
