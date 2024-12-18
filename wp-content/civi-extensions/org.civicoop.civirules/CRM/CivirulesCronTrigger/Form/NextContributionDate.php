<?php
/**
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesCronTrigger_Form_NextContributionDate extends CRM_CivirulesTrigger_Form_Form {

  /**
   * Overridden parent method to build form
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_id');
    $this->add('select', 'interval_unit', ts('Interval'), CRM_CivirulesCronTrigger_NextContributionDate::intervals(), TRUE);
    $this->add('text', 'interval', ts('Interval'), [], TRUE);
    $this->addRule('interval', ts('Interval should be a numeric value'), 'numeric');
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
    $data = unserialize($this->rule->trigger_params);
    if (!empty($data['interval_unit'])) {
      $defaultValues['interval_unit'] = $data['interval_unit'];
    }
    if (!empty($data['interval'])) {
      $defaultValues['interval'] = $data['interval'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   */
  public function postProcess() {
    $data['interval_unit'] = $this->_submitValues['interval_unit'];
    $data['interval'] = $this->_submitValues['interval'];
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
    return E::ts('The rule will be triggered for recurring contributions when the next scheduled contribution date is X days/weeks/months before or after.');
  }

}
