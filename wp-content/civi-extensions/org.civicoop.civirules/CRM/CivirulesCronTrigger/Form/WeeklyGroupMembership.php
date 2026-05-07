<?php
/**
 * Class for CiviRules Condition Contribution Financial Type Form
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesCronTrigger_Form_WeeklyGroupMembership extends CRM_CivirulesTrigger_Form_Form {

  /**
   * Overridden parent method to build form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_id');
    $group = $this->add('select', 'group_id', E::ts('Groups'), CRM_Civirules_Utils::getGroupList(), TRUE);
    $group->setMultiple(TRUE);

    $this->add('select', 'week_day', E::ts('Day of the week'), CRM_Utils_Date::getFullWeekdayNames(), TRUE);
    $this->add('text', 'not_before_time', E::ts('Not before time (in 24 hours format hh:mm)'), ['size' => CRM_Utils_Type::FOUR], false);
    $this->add('text', 'not_after_time', E::ts('Not after time (in 24 hours format hh:mm)'), ['size' => CRM_Utils_Type::FOUR], false);

    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')]
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
    $data = unserialize($this->rule->trigger_params);
    if (!empty($data['group_id'])) {
      if (!is_array($data['group_id'])) {
        $data['group_id'] = [$data['group_id']];
      }
      $defaultValues['group_id'] = $data['group_id'];
    }
    if (!empty($data['week_day'])) {
      $defaultValues['week_day'] = $data['week_day'];
    }
    if (!empty($data['not_before_time'])) {
      $defaultValues['not_before_time'] = $data['not_before_time'];
    }
    if (!empty($data['not_after_time'])) {
      $defaultValues['not_after_time'] = $data['not_after_time'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   */
  public function postProcess() {
    $this->triggerParams['group_id'] = $this->getSubmittedValue('group_id');
    $this->triggerParams['week_day'] = $this->getSubmittedValue('week_day');
    $this->triggerParams['not_before_time'] = $this->getSubmittedValue('not_before_time');
    $this->triggerParams['not_after_time'] = $this->getSubmittedValue('not_after_time');
    parent::postProcess();
  }
}
