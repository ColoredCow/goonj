<?php
use CRM_Civirules_ExtensionUtil as E;

/**
 * Class to process form for action to end/delete relationship
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 26 Aug 2021
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

class CRM_CivirulesActions_Relationship_Form_End extends CRM_CivirulesActions_Form_Form{

  /**
   * Overridden parent method to build the form
   *
   * @throws CRM_Core_Exception
   */
  public function buildForm() {
    parent::buildForm();
    $this->add('hidden', 'rule_action_id');
    $this->addEntityRef('relationship_type_id', E::ts('Relationship Type'), [
      'entity' => 'relationshipType'
    ]);
    $this->add('select', 'operation', E::ts('End or Delete Relationship'), ['Disable', 'Delete'], TRUE);
    $this->add('datepicker', 'end_date', E::ts('Relationship End Date (only if disable)'), ['placeholder' => E::ts('End date')],FALSE, ['time' => FALSE]);

    $this->addButtons([
      ['type' => 'next', 'name' => E::ts('Save'), 'isDefault' => TRUE],
      ['type' => 'cancel', 'name' => E::ts('Cancel')]]);
  }

  /**
   * Method to set the default values
   *
   * @return array
   */
  public function setDefaultValues() {
    $defaultValues =  parent::setDefaultValues();
    $data = $this->ruleAction->unserializeParams();
    if (!empty($data['relationship_type_id'])){
      $defaultValues['relationship_type_id'] = $data['relationship_type_id'];
    }
    if (!empty($data['end_date'])){
      $defaultValues['end_date'] = $data['end_date'];
    }
    if (!empty($data['operation'])){
      $defaultValues['operation'] = $data['operation'];
    }
    else {
      $defaultValues['operation'] = 0;
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process submitted form
   */
  public function postProcess()   {
    $data['relationship_type_id'] = $this->_submitValues['relationship_type_id'];
    $data['operation'] = $this->_submitValues['operation'];
    if (isset($this->_submitValues['end_date'])) {
      $data['end_date'] = $this->_submitValues['end_date'];
    }
    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }
}
