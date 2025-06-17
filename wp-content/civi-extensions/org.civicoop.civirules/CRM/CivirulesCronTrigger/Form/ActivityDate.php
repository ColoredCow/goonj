<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesCronTrigger_Form_ActivityDate extends CRM_CivirulesCronTrigger_Form_Activity {

  /**
   * Overridden parent method to build form
   */
  public function buildQuickForm() {
    parent::buildQuickForm();
    $this->add('select', 'activity_type_id', ts('Activity Type'), $this->getActivityType(), TRUE);
    $this->add('select', 'activity_status_id', ts('Activity Status'), $this->getActivityStatus(), TRUE);
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->rule->unserializeParams();
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
    parent::postProcess();
  }

}
