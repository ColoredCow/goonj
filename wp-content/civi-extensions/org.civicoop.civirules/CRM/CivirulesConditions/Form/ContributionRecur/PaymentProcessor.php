<?php
/**
 * Class for CiviRules Condition Membership recurring form
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesConditions_Form_ContributionRecur_PaymentProcessor extends CRM_CivirulesConditions_Form_Form {

  /**
   * Method to get operators
   *
   * @return array
   * @access protected
   */
  protected function getOperators() {
    return CRM_CivirulesConditions_ContributionRecur_PaymentProcessor::getOperatorOptions();
  }

  /**
   * Overridden parent method to build form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_condition_id');

    $paymentProcessors = CRM_Civirules_Utils::getPaymentProcessors();
    $paymentProcessorID = $this->add('select', 'payment_processor_id', ts('Payment Processor'), $paymentProcessors, TRUE);
    $paymentProcessorID->setMultiple(TRUE);
    $this->add('select', 'payment_processor_id_operator', ts('Operator'), $this->getOperators(), TRUE);

    $this->addButtons([
      ['type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,],
      ['type' => 'cancel', 'name' => ts('Cancel')]
    ]);
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
    if (!empty($data['payment_processor_id'])) {
      $defaultValues['payment_processor_id'] = $data['payment_processor_id'];
    }
    if (!empty($data['payment_processor_id_operator'])) {
      $defaultValues['payment_processor_id_operator'] = $data['payment_processor_id_operator'];
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
    $data['payment_processor_id'] = $this->_submitValues['payment_processor_id'];
    $data['payment_processor_id_operator'] = $this->_submitValues['payment_processor_id_operator'];
    $this->ruleCondition->condition_params = serialize($data);
    $this->ruleCondition->save();
    parent::postProcess();
  }

  /**
   * Returns a help text for this condition.
   * The help text is shown to the administrator who is configuring the condition.
   *
   * @return string
   */
  public function getHelpText() {
    $help = E::ts('This condition works with Membership (checks the linked recur), Contribution (checks the linked recur) and Recurring Contribution triggers.');
    $help .= '<ul><li>' . E::ts('IS ONE OF will match a Membership/Contribution/Recurring Contribution that has a payment processor in the list.');
    $help .= '</li><li>' . E::ts('IS NOT ONE OF will match a Membership/Contribution that does not have a linked recurring contribution OR a Membership/Contribution/Recurring Contribution that: Has a recurring contribution with no payment processor; Has a recurring contribution that has a payment processor not in the list.');
    $help .= '</ul>';
    return $help;
  }
}
