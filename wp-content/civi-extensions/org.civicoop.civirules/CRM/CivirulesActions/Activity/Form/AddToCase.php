<?php
/**
 * Copyright (C) 2022  Jaap Jansma (jaap.jansma@civicoop.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Activity_Form_AddToCase extends CRM_CivirulesActions_Activity_Form_Activity {

  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    parent::buildQuickForm();

    $case_types = [];
    $case_type_api = civicrm_api3('CaseType', 'get', ['options' => ['limit' => 0]]);
    foreach($case_type_api['values'] as $case_type) {
      $case_types[$case_type['id']] = $case_type['title'];
    }

    // add back previously removed fields as not required
    $this->add('select', 'case_type_id', ts('Case type'), ['' => E::ts('-- Please select --')] + $case_types, FALSE);
    $this->add('select', 'case_status_id', ts('Case Status'), ['' => E::ts('-- Any status  --')] + CRM_Core_OptionGroup::values('case_status'), FALSE);
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
    if (!empty($data['case_type_id'])) {
      $defaultValues['case_type_id'] = $data['case_type_id'];
    }
    if (!empty($data['case_status_id'])) {
      $defaultValues['case_status_id'] = $data['case_status_id'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    $data['activity_type_id'] = $this->_submitValues['activity_type_id'];
    $data['status_id'] = $this->_submitValues['status_id'];
    $data['subject'] = $this->_submitValues['subject'];
    $data['details'] = $this->_submitValues['details'];
    $data['case_type_id'] = $this->_submitValues['case_type_id'];
    $data['case_status_id'] = $this->_submitValues['case_status_id'];
    $data["assignee_contact_id"] = explode(',', $this->_submitValues["assignee_contact_id"]);

    $data['activity_date_time'] = 'null';
    if (!empty($this->_submitValues['activity_date_time'])) {
      $scheduledDateClass = CRM_Civirules_Delay_Factory::getDelayClassByName($this->_submitValues['activity_date_time']);
      $scheduledDateClass->setValues($this->_submitValues, 'activity_date_time', $this->rule);
      $data['activity_date_time'] = serialize($scheduledDateClass);
    }

    $data['send_email'] = $this->_submitValues['send_email'];

    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
  }
}
