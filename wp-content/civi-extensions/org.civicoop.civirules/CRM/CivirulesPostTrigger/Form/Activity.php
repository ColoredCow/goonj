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
   * @access public
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

    $this->add('select', 'record_type', E::ts('Trigger for'),$options, true, ['class' => 'crm-select2 huge']);

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
   * @access public
   */
  public function postProcess() {
    $data['record_type'] = $this->_submitValues['record_type'];
    $this->rule->trigger_params = serialize($data);
    $this->rule->save();

    parent::postProcess();
  }

  /**
   * Returns a help text for this trigger.
   * The help text is shown to the administrator who is configuring the condition.
   *
   * @return string
   */
  protected function getHelpText() {
    return E::ts('When all contacts is selected then the trigger will be fired for every contact. Meaning that trigger might run more than once.')
      . '<br/>'
      . E::ts('When you don\'t want that select the record type for which you want to fire the trigger.')
      . '<br/>'
      . E::ts('The select record type also defines which contact is available in the conditions and actions.');
  }

}
