<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesCronTrigger_Form_ActivityScheduledDate extends CRM_CivirulesCronTrigger_Form_Activity {

  public function buildQuickForm() {
    parent::buildQuickForm();
    $this->add('select', 'activity_type_id', ts('Activity Type'), $this->getActivityType(), TRUE, [
      'multiple' => TRUE,
      'class' => 'crm-select2'
    ]);
    $this->add('select', 'activity_status_id', ts('Activity Status'), $this->getActivityStatus(), TRUE, [
      'multiple' => TRUE,
      'class' => 'crm-select2'
    ]);

    $this->add('select', 'interval_unit', ts('Interval'), CRM_CivirulesCronTrigger_ActivityScheduledDate::intervals(), TRUE);
    $this->add('text', 'interval', ts('Interval'), [], TRUE);
    $this->addRule('interval', ts('Interval should be a numeric value'), 'numeric');
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = unserialize($this->rule->trigger_params);
    if (!empty($data['activity_type_id'])) {
      $defaultValues['activity_type_id'] = $data['activity_type_id'];
    }
    if (!empty($data['activity_status_id'])) {
      $defaultValues['activity_status_id'] = $data['activity_status_id'];
    }

    if (!empty($data['record_type'])) {
      $defaultValues['record_type'] = $data['record_type'];
    } else {
      $defaultValues['record_type'] = 3; // Default to only targets
    }

    $defaultValues['case_activity'] = $data['case_activity'] ?? FALSE;

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
   *
   * @throws Exception when rule condition not found
   */
  public function postProcess() {
    $data['activity_type_id'] = $this->_submitValues['activity_type_id'];
    $data['activity_status_id'] = $this->_submitValues['activity_status_id'];
    $data['record_type'] = $this->_submitValues['record_type'];
    $data['case_activity'] = $this->_submitValues['case_activity'];
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
    return E::ts('The rule will be triggered for activities of selected types when the scheduled date is X days/weeks/months before or after.')
      . '<br/>'
      . E::ts('If "Trigger for case activities" is "Yes" then this will only trigger for case activities. If it is "No" then it will only trigger for activities that are not linked to a case.');
  }

}
