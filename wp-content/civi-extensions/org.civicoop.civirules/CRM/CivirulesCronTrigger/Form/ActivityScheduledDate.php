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
    $this->triggerParams['activity_type_id'] = $this->getSubmittedValue('activity_type_id');
    $this->triggerParams['activity_status_id'] = $this->getSubmittedValue('activity_status_id');
    $this->triggerParams['record_type'] = $this->getSubmittedValue('record_type');
    $this->triggerParams['case_activity'] = $this->getSubmittedValue('case_activity');
    $this->triggerParams['interval_unit'] = $this->getSubmittedValue('interval_unit');
    $this->triggerParams['interval'] = $this->getSubmittedValue('interval');
    parent::postProcess();
  }

}
