<?php
/**
 * Class for CiviRules Condition Membership Type Form
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesConditions_Form_Membership_ActiveMembership extends CRM_CivirulesConditions_Form_Form {

  /**
   * Overridden parent method to build form
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');

    $membershipTypes = CRM_Civirules_Utils::getMembershipTypes();
    asort($membershipTypes);
    $this->add('select', 'membership_type_id', E::ts('Membership Type(s)'), $membershipTypes, TRUE, [
      'multiple' => TRUE,
      'class' => 'crm-select2',
      'placeholder' => E::ts('- Select -'),
    ]);

    $this->add('checkbox', 'negate', E::ts('Negate'));

    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')]
    ]);
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->ruleCondition->unserializeParams();
    if (!empty($data['membership_type_id'])) {
      $defaultValues['membership_type_id'] = $data['membership_type_id'];
    }
    if (!empty($data['negate'])) {
      $defaultValues['negate'] = $data['negate'];
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
    $data['membership_type_id'] = $this->getSubmittedValue('membership_type_id');
    $data['negate'] = $this->getSubmittedValue('negate');
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();
    parent::postProcess();
  }
}
