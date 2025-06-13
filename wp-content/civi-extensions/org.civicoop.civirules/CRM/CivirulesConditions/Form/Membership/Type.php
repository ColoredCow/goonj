<?php
/**
 * Class for CiviRules Condition Membership Type Form
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesConditions_Form_Membership_Type extends CRM_CivirulesConditions_Form_Form {

  /**
   * Overridden parent method to build for,
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');

    $membershipTypes = CRM_Civirules_Utils::getMembershipTypes();
    $membershipTypes[0] = E::ts('- select -');
    asort($membershipTypes);
    $this->add('select', 'membership_type_id', E::ts('Membership Type'), $membershipTypes, FALSE);
    $this->add('select', 'operator', E::ts('Operator'), [E::ts('equals'), E::ts('is not equal to'), E::ts('is one of'), E::ts('is NOT one of')], TRUE);
    $this->add('select', 'membership_type_ids', E::ts('Membership Types'), $membershipTypes, FALSE, [
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => ts('- Select membership type -'),
      'multiple' => TRUE,
    ]);

    $this->addButtons([
      ['type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,],
      ['type' => 'cancel', 'name' => ts('Cancel')]
    ]);

    $this->addFormRule([__CLASS__, 'formRule']);
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
    if (!empty($data['membership_type_id'])) {
      $defaultValues['membership_type_id'] = $data['membership_type_id'];
    }
    if (!empty($data['membership_type_ids'])) {
      $defaultValues['membership_type_ids'] = $data['membership_type_ids'];
    }
    if (!empty($data['operator'])) {
      $defaultValues['operator'] = $data['operator'];
    }
    return $defaultValues;
  }

  /**
   * Global validation rules for the form.
   *
   * @param array $fields
   *   Posted values of the form.
   *
   * @return array
   *   list of errors to be posted back to the form
   */
  public static function formRule($fields) {
    if (empty($fields['membership_type_id']) && empty($fields['membership_type_ids'])) {
      $errors['membership_type_id'] = E::ts('Membership Type is a required field.');
    }
    return $errors ?? [];
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   * @access public
   */
  public function postProcess() {
    $data['membership_type_id'] = $this->_submitValues['membership_type_id'];
    $data['membership_type_ids'] = $this->_submitValues['membership_type_ids'];
    $data['operator'] = $this->_submitValues['operator'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();
    parent::postProcess();
  }
}
