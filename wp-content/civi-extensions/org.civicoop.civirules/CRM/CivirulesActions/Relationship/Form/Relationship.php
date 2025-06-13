<?php


class CRM_CivirulesActions_Relationship_Form_Relationship extends CRM_CivirulesActions_Form_Form{


  public function buildForm()
  {
    parent::buildForm();
    $this->add('hidden', 'rule_action_id');

    $this->addEntityRef('relationship_type_id', ts('relationship type'), [
      'entity' => 'relationshipType'
    ]);
    $this->addEntityRef('contact_id_b', ts('contact'),[
      'entity' => 'contact'
    ]);

    $this->addCheckBox('reverse_relationship', ts('Reverse Relationship'), [
      'Swap contact_a and contact_b' => $defaultValues['reverse_relationship'],
    ]);

    $this->addButtons(array(
      array('type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,),
      array('type' => 'cancel', 'name' => ts('Cancel'))));

  }

  public function setDefaultValues()
  {
    $defaultValues =  parent::setDefaultValues();
    $data = $this->ruleAction->unserializeParams();
    if (!empty($data['relationship_type_id'])){
      $defaultValues['relationship_type_id'] = $data['relationship_type_id'];
    }
    if (!empty($data['contact_id_b'])){
      $defaultValues['contact_id_b'] = $data['contact_id_b'];
    }
    if (isset($data['reverse_relationship'])) {
      $defaultValues['reverse_relationship'] = $data['reverse_relationship'];
    } else {
      $defaultValues['reverse_relationship'] = 0;
    }
    return $defaultValues;
  }

  public function postProcess()
  {
    $data['relationship_type_id'] = $this->_submitValues['relationship_type_id'];
    $data['contact_id_b'] = $this->_submitValues['contact_id_b'];
    $data['reverse_relationship'] = $this->_submitValues['reverse_relationship'];
    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }
}
