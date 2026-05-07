<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesCronTrigger_Form_NoCaseActivitySince extends CRM_CivirulesTrigger_Form_Form {

  protected function getCaseTypes() {
    $return = [];
    $caseTypes = civicrm_api3('CaseType', 'Get', ['is_active' => 1]);
    foreach ($caseTypes['values'] as $caseType) {
      $return[$caseType['id']] = $caseType['title'];
    }
    return $return;
  }

  protected function getCaseStatus() {
    $return = [];
    $params = [
      'return' => ["label", "value"],
      'option_group_id' => 'case_status',
      'is_active' => '1',
      'options' => ['limit' => 0, 'sort' => "label ASC"],
    ];

    try {
      $options = civicrm_api3('OptionValue', 'get', $params)['values'];
      foreach ($options as $option) {
        $return[$option['value']] = $option['label'];
      }
    } catch (\Exception $ex) {}
    return $return;
  }

  /**
   * Overridden parent method to build form
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_id');

    $caseTypes = $this->getCaseTypes();
    $this->add('select', 'case_type', E::ts('Case Type'), $caseTypes, FALSE, ['class' => 'crm-select2 huge', 'multiple' => TRUE, 'placeholder' => E::ts('Any case type')]);
    $caseStatus = $this->getCaseStatus();
    $this->add('select', 'case_status', E::ts('Case Status'), $caseStatus, FALSE, ['class' => 'crm-select2 huge', 'multiple' => TRUE, 'placeholder' => E::ts('Any case status')]);
    $activityTypes = CRM_Civirules_Utils::getActivityTypeList();
    $this->add('select', 'activity_type', E::ts('Activity Type'), $activityTypes, FALSE, ['class' => 'crm-select2 huge', 'multiple' => TRUE, 'placeholder' => E::ts('Any activity type')]);
    $activityStatus = CRM_Civirules_Utils::getActivityStatusList();
    $this->add('select', 'activity_status', E::ts('Activity Status'), $activityStatus, FALSE, ['class' => 'crm-select2 huge', 'multiple' => TRUE, 'placeholder' => E::ts('Any activity status')]);

    $this->add('select', 'offset_unit', E::ts('Offset Unit'), [
      'DAY' => E::ts('Day(s)'),
      'WEEK' => E::ts('Week(s)'),
      'MONTH' => E::ts('Month(s)'),
      'YEAR' => E::ts('Year(s)'),
    ], TRUE);
    $this->add('text', 'offset', E::ts('Since'), [
      'class' => 'six',
    ], TRUE);

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
    if (!empty($data['case_type'])) {
      $defaultValues['case_type'] = $data['case_type'];
    }
    if (!empty($data['case_status'])) {
      $defaultValues['case_status'] = $data['case_status'];
    }
    if (!empty($data['activity_type'])) {
      $defaultValues['activity_type'] = $data['activity_type'];
    }
    if (!empty($data['activity_status'])) {
      $defaultValues['activity_status'] = $data['activity_status'];
    }

    $defaultValues['offset_unit'] = 'DAY';
    if (!empty($data['offset_unit'])) {
      $defaultValues['offset_unit'] = $data['offset_unit'];
    }
    if (!empty($data['offset'])) {
      $defaultValues['offset'] = $data['offset'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   */
  public function postProcess() {
    $this->triggerParams['case_type'] = $this->getSubmittedValue('case_type');
    $this->triggerParams['case_status'] = $this->getSubmittedValue('case_status');
    $this->triggerParams['activity_type'] = $this->getSubmittedValue('activity_type');
    $this->triggerParams['activity_status'] = $this->getSubmittedValue('activity_status');
    $this->triggerParams['offset_unit'] = $this->getSubmittedValue('offset_unit');
    $this->triggerParams['offset'] = $this->getSubmittedValue('offset');
    parent::postProcess();
  }

}
