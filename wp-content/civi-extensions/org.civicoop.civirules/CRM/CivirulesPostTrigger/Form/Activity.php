<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesPostTrigger_Form_Activity extends CRM_CivirulesTrigger_Form_Form {

  /**
   * Overridden parent method to build form
   *
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_id');
    $result = civicrm_api3('ActivityContact', 'getoptions', [
      'field' => 'record_type_id',
    ]);
    $options[0] = E::ts('All contacts');
    foreach($result['values'] as $val => $opt) {
      $options[$val] = $opt;
    }

    $this->add('select', 'record_type', E::ts('Trigger for'), $options, true, ['class' => 'crm-select2 huge']);

    $this->addButtons([
      ['type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,],
      ['type' => 'cancel', 'name' => ts('Cancel')]
    ]);
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    if (isset($this->rule->trigger_params)) {
      $data = unserialize($this->rule->trigger_params);
      // Default to all record types. This creates backwards compatibility.
      $defaultValues['record_type'] = $data['record_type'] ?? 0;
    }

    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   */
  public function postProcess() {
    $this->triggerParams['record_type'] = $this->getSubmittedValue('record_type');
    parent::postProcess();
  }

}
