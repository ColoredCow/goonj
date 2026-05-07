<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_ContributionRecur_Form_ChangeNextScheduleDate extends CRM_CivirulesActions_Form_Form {

 /**
  * Get Schedule Base options
  */
  private function getScheduleOptions() {
    return [
      'today' => 'today',
      '+1 day' => 'tomorrow',
      '+48 hours' => '+2 days',
    ];
  }


  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');

    $statuses = self::getScheduleOptions();
    $this->add('select', 'schedule_option', E::ts('Set Next Scheduled Date relative to'), ['' => E::ts('-- please select --')] + $statuses);
    $this->add('text', 'schedule_date', ts('Set Next Scheduled Date (custom date or format)'), '', FALSE);
    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE,],
      ['type' => 'cancel', 'name' => E::ts('Cancel')]
    ]);
  }

  /**
   * Function to add validation condition rules (overrides parent function)
   *
   * @access public
   */
  public function addRules() {
    $this->addFormRule(['CRM_CivirulesActions_ContributionRecur_Form_ChangeNextScheduleDate', 'validateInputFields']);
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   * @access public
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = $this->ruleAction->unserializeParams();

    if (!empty($data['schedule_option'])) {
      $defaultValues['schedule_option'] = $data['schedule_option'];
    }
    if (!empty($data['schedule_date'])) {
      $defaultValues['schedule_date'] = $data['schedule_date'];
    }
    return $defaultValues;
  }

  /**
   * Method to validate field options
   *
   * @param $fields
   * @return array|bool
   */
  public static function validateInputFields($fields) {
    // Make sure that they didn't choose both options
    if (isset($fields['schedule_option']) && isset($fields['schedule_date'])) {
      $errors['schedule_option'] = E::ts('You cannot have a relative option and a custom date/format');
    }

    // Make sure that we have at least one set
    if (empty($fields['schedule_option']) && empty($fields['schedule_date'])) {
      $errors['schedule_option'] = E::ts('You must have either a relative option or a custom date/format');
      $errors['schedule_date'] = E::ts('You must have either a relative option or a custom date/format');
    }

    return $errors;
  }


  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    $schedule_option = $this->getSubmittedValue('schedule_option');
    if ($schedule_option !== '') {
      $data['schedule_option'] = $schedule_option;
    }
    else {
      $data['schedule_date'] = $this->getSubmittedValue('schedule_date');
    }

    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }

}
