<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesCronTrigger_Form_EventDate extends CRM_CivirulesTrigger_Form_Form {

  /**
   * @return array
   */
  protected function getEventType() {
    return CRM_Civirules_Utils::getEventTypeList();
  }

  /**
   * Overridden parent method to build form
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_id');

    $this->add('select', 'event_type_id', E::ts('Event Type'), [E::ts(' - any -')] + $this->getEventType(), TRUE);
    $this->add('select', 'date_field', E::ts('Date Field'), [
      'start_date' => E::ts('Start date'),
      'end_date' => E::ts('End date')
    ], TRUE);
    $this->add('select', 'offset_unit', E::ts('Offset Unit'), [
      'HOUR' => E::ts('Hour(s)'),
      'DAY' => E::ts('Day(s)'),
      'WEEK' => E::ts('Week(s)'),
      'MONTH' => E::ts('Month(s)'),
      'YEAR' => E::ts('Year(s)'),
    ], FALSE);
    $this->add('select', 'offset_type', E::ts('Offset type'), [
      '+' => E::ts('After'),
      '-' => E::ts('Before'),
    ], FALSE);
    $this->add('text', 'offset', E::ts('Offset'), [
      'class' => 'six',
    ], FALSE);
    $this->add('checkbox', 'enable_offset', E::ts('Give a date offset'), '', FALSE);

    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')]
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
    if (!empty($data['event_type_id'])) {
      $defaultValues['event_type_id'] = $data['event_type_id'];
    }
    if (!empty($data['date_field'])) {
      $defaultValues['date_field'] = $data['date_field'];
    }
    if (!empty($data['offset_unit'])) {
      $defaultValues['offset_unit'] = $data['offset_unit'];
    }
    if (!empty($data['offset_type'])) {
      $defaultValues['offset_type'] = $data['offset_type'];
    }
    if (!empty($data['offset'])) {
      $defaultValues['offset'] = $data['offset'];
      $defaultValues['enable_offset'] = 1;
    }
    if (empty($data['offset'])) {
      $defaultValues['enable_offset'] = 0;
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   */
  public function postProcess() {
    $this->triggerParams['event_type_id'] = $this->getSubmittedValue('event_type_id');
    $this->triggerParams['date_field'] = $this->getSubmittedValue('date_field');
    if ($this->getSubmittedValue('enable_offset')) {
      $this->triggerParams['offset_unit'] = $this->getSubmittedValue('offset_unit');
      $this->triggerParams['offset_type'] = $this->getSubmittedValue('offset_type');
      $this->triggerParams['offset'] = $this->getSubmittedValue('offset');
    } else {
      $this->triggerParams['offset_unit'] = $this->getSubmittedValue('offset_unit');
      $this->triggerParams['offset_type'] = $this->getSubmittedValue('offset_type');
      $this->triggerParams['offset'] = '';
    }
    parent::postProcess();
  }

}
