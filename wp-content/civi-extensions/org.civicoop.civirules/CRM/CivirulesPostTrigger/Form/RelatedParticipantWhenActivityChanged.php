<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesPostTrigger_Form_RelatedParticipantWhenActivityChanged extends CRM_CivirulesTrigger_Form_Form {

  public static function getActivityCustomFields() {
    $customGroups = civicrm_api3('CustomGroup', 'get', ['extends' => 'Activity', 'options' => ['limit' => 0]]);
    $activityCustomFields = [];
    foreach($customGroups['values'] as $customGroup) {
      $customFields = civicrm_api3('CustomField', 'get', ['custom_group_id' => $customGroup['id'], 'options' => ['limit' => 0]]);
      foreach($customFields['values'] as $customField) {
        $activityCustomFields[$customField['id']] = $customGroup['title'] . ': ' . $customField['label'];
      }
    }
    return $activityCustomFields;
  }

  /**
   * Overridden parent method to build form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_id');
    $this->add('select', 'event_id_custom_field', ts('Event ID custom field'), array('' => ts('-- please select --')) + self::getActivityCustomFields(), true, [
      'class' => 'crm-select2 huge'
    ]);
    $this->add('select', 'activity_type_id', ts('Limit to Activity type'), array('' => ts('-- please select --')) + CRM_Core_OptionGroup::values('activity_type'), true, [
      'class' => 'crm-select2 huge',
      'multiple' => 'multiple',
    ]);

    $this->addButtons(array(
      array('type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,),
      array('type' => 'cancel', 'name' => ts('Cancel'))));
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = unserialize($this->rule->trigger_params);
    if (isset($data['event_id_custom_field'])) {
      $defaultValues['event_id_custom_field'] = $data['event_id_custom_field'];
    }
    if (isset($data['activity_type_id'])) {
      $defaultValues['activity_type_id'] = $data['activity_type_id'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   *
   * @throws Exception when rule condition not found
   */
  public function postProcess() {
    $this->triggerParams['event_id_custom_field'] = $this->getSubmittedValue('event_id_custom_field');
    $this->triggerParams['activity_type_id'] = [];
    if (isset($this->_submitValues['activity_type_id'])) {
      $this->triggerParams['activity_type_id'] = $this->getSubmittedValue('activity_type_id');
    }
    parent::postProcess();
  }

}
