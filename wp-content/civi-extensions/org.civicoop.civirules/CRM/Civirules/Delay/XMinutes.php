<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_Civirules_Delay_XMinutes extends CRM_Civirules_Delay_Delay {

  protected $minuteOffset;

  /**
   * @param \DateTime $date
   * @param \CRM_Civirules_TriggerData_TriggerData $triggerData
   *
   * @return \DateTime
   */
  public function delayTo(DateTime $date, CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $date->modify("+ " . $this->minuteOffset . " minutes");
    return $date;
  }

  /**
   * @return string
   */
  public function getDescription() {
    return E::ts('Delay by a number of minutes');
  }

  /**
   * @return string
   */
  public function getDelayExplanation() {
    return E::ts('Delay by %1 minutes', [1 => $this->minuteOffset]);
  }

  /**
   * @param \CRM_Core_Form $form
   * @param $prefix
   * @param \CRM_Civirules_BAO_CiviRulesRule $rule
   *
   * @return mixed|void
   * @throws \CRM_Core_Exception
   */
  public function addElements(CRM_Core_Form &$form, $prefix, CRM_Civirules_BAO_CiviRulesRule $rule) {
    $form->add('text', $prefix . 'xminutes_minuteOffset', E::ts('Minutes'));
  }

  /**
   * @param $values
   * @param $errors
   * @param $prefix
   * @param \CRM_Civirules_BAO_CiviRulesRule $rule
   *
   * @return void
   */
  public function validate($values, &$errors, $prefix, CRM_Civirules_BAO_CiviRulesRule $rule) {
    if (empty($values[$prefix . 'xminutes_minuteOffset']) || !is_numeric($values[$prefix.'xminutes_minuteOffset'])) {
      $errors[$prefix . 'xminutes_minuteOffset'] = E::ts('You need to provide a number of minutes');
    }
  }

  /**
   * @param $values
   * @param $prefix
   * @param \CRM_Civirules_BAO_CiviRulesRule $rule
   *
   * @return void
   */
  public function setValues($values, $prefix, CRM_Civirules_BAO_CiviRulesRule $rule) {
    $this->minuteOffset = $values[$prefix . 'xminutes_minuteOffset'];
  }

  /**
   * @param $prefix
   * @param \CRM_Civirules_BAO_CiviRulesRule $rule
   *
   * @return array
   */
  public function getValues($prefix, CRM_Civirules_BAO_CiviRulesRule $rule) {
    $values = [];
    $values[$prefix . 'xminutes_minuteOffset'] = $this->minuteOffset;
    return $values;
  }

}
