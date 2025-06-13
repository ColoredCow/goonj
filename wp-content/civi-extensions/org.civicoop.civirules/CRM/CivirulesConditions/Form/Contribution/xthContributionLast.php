<?php

/**
 * Class for CiviRules Condition xth Contribution in the last interval Form
 *
 * @author Sandor Semsey <sandor@es-progress.hu>
 * @date 16 Feb 2023
 * @license AGPL-3.0
 */
class CRM_CivirulesConditions_Form_Contribution_xthContributionLast extends CRM_CivirulesConditions_Form_Form {

  /**
   * Overridden parent method to build form
   *
   * @access public
   * @throws \Exception
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');
    $this->add('select', 'operator', ts('Operator'), CRM_Civirules_Utils::getGenericComparisonOperatorOptions(), TRUE);
    $this->add('select', 'financial_type', ts('of Financial Type(s)'), CRM_Civirules_Utils::getFinancialTypes(), TRUE,
      ['id' => 'financial_type_ids', 'multiple' => 'multiple', 'class' => 'crm-select2']);
    $this->add('text', 'number_contributions', ts('Number of Contributions'), [], TRUE);
    $this->addRule('number_contributions','Number of Contributions must be a whole number','numeric');
    $this->addRule('number_contributions','Number of Contributions must be a whole number','nopunctuation');
    $status = CRM_Civirules_Utils_OptionGroup::getActiveValues(CRM_Civirules_Utils::getOptionGroupIdWithName('contribution_status'));
    $this->add('select', 'contribution_status', ts('Contribution status'), $status, TRUE, ['multiple' => 'multiple', 'class' => 'crm-select2']);
    $this->add('text', 'interval', ts('in the last'), [], TRUE);
    $this->add('select', 'interval_unit', ts('interval'), self::getIntervalUnits(), TRUE);
    $this->addButtons([
      ['type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => ts('Cancel')]
    ]);
  }

  /**
   * Return available time interval units
   *
   * @return array
   */
  public static function getIntervalUnits(): array
  {
    return [
      'days' => ts('days'),
      'months' => ts('months'),
      'years' => ts('years'),
    ];
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
    if (!empty($data['number_contributions'])) {
      $defaultValues['number_contributions'] = $data['number_contributions'];
    }
    if (!empty($data['financial_type'])) {
      $defaultValues['financial_type'] = $data['financial_type'];
    }
    if (!empty($data['operator'])) {
      $defaultValues['operator'] = $data['operator'];
    }
    if (!empty($data['contribution_status'])) {
      $defaultValues['contribution_status'] = $data['contribution_status'];
    }
    if (!empty($data['interval'])) {
      $defaultValues['interval'] = $data['interval'];
    }
    if (!empty($data['interval_unit'])) {
      $defaultValues['interval_unit'] = $data['interval_unit'];
    }
    return $defaultValues;
  }

  /**
   * Function to add validation condition rules (overrides parent function)
   *
   * @access public
   */
  public function addRules() {
    $this->addFormRule(array('CRM_CivirulesConditions_Form_Contribution_xthContributionLast', 'validateCompareZero'));
  }

  /**
   * Method to validate if the operator works with value zero
   *
   * @param $fields
   * @return array|bool
   */
  public static function validateCompareZero($fields) {
    // zero in number only allowed if operator greater than
    if (isset($fields['operator']) && isset($fields['number_contributions'])) {
      if ($fields['number_contributions'] == 0 && $fields['operator'] != 1) {
        $errors['number_contributions'] = ts('Comparing value 0 with anything but greater than makes no sense');
        return $errors;
      }
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
    $data['contribution_status'] = $this->_submitValues['contribution_status'];
    $data['interval'] = $this->_submitValues['interval'];
    $data['interval_unit'] = $this->_submitValues['interval_unit'];
    $data['number_contributions'] = $this->_submitValues['number_contributions'];
    $data['operator'] = $this->_submitValues['operator'];
    $data['financial_type'] = $this->_submitValues['financial_type'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();
    parent::postProcess();
  }
}
