<?php
use CRM_Civirules_ExtensionUtil as E;
/**
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_CivirulesActions_Participant_Form_Register extends CRM_CivirulesActions_Form_Form {

  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');
    $this->addEntityRef('event_id', E::ts("Event"), [
      'entity' => "event",
      'placeholder' => E::ts("- Select Event -"),
      'select' => ['minimumInputLength' => 0],
      'api' => ['params' => [
        'is_active' => TRUE,
        ]]
    ], TRUE);
    $this->addEntityRef('campaign_id', E::ts("Campaign"), [
      'entity' => "campaign",
      'placeholder' => E::ts("- Select Campaign -"),
      'select' => ['minimumInputLength' => 0],
      'api' => ['params' => [
        'is_active' => TRUE,
        ]]
    ], FALSE);
    $this->addEntityRef('participant_role_id', E::ts("Participant Role"), [
      'entity' => "option_value",
      'placeholder' => E::ts("- Select Role -"),
      'select' => ['minimumInputLength' => 0],
      'api' => ['params' => [
        'option_group_id' => 'participant_role',
        'is_active' => TRUE,
        ]]
    ], TRUE);
    $this->addEntityRef('participant_status_id', E::ts("Participant Status"), [
      'entity' => "participant_status_type",
      'placeholder' => E::ts("- Select Status -"),
      'select' => ['minimumInputLength' => 0],
      'api' => ['params' => [
        'is_active' => TRUE,
        ]]
    ], TRUE);
    $this->add('datepicker', 'registration_date', E::ts('Registration Date'), [],FALSE, ['time' => FALSE]);
    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')],
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
    $data = $this->ruleAction->unserializeParams();
    $actionParameters = $this->getActionParameters();
    foreach ($actionParameters as $actionParameter) {
      if (isset($data[$actionParameter]) && !empty($data[$actionParameter])) {
        $defaultValues[$actionParameter] = $data[$actionParameter];
      }
    }
    if (empty($defaultValues['registration_date'])) {
      $registrationDate = new DateTime('now');
      $defaultValues['registration_date'] = $registrationDate->format("d-m-Y");
    }
    return $defaultValues;
  }

  /**
   * Method to get the action parameters
   * @return string[]
   */
  private function getActionParameters() {
    return ['event_id', 'campaign_id', 'participant_role_id', 'participant_status_id', 'registration_date'];
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    $actionParameters = $this->getActionParameters();
    foreach ($actionParameters as $actionParameter) {
      if (isset($this->_submitValues[$actionParameter])) {
        $data[$actionParameter] = $this->_submitValues[$actionParameter];
      }
    }
    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }

}
