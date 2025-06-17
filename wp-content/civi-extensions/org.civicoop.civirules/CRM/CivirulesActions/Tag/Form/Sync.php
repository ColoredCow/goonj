<?php

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesActions_Tag_Form_Sync extends CRM_CivirulesActions_Form_Form {

  /**
   * Method to get entity tags
   *
   * @return array
   * @access protected
   */
  protected function getTags() {
    if (CRM_Civirules_Utils::isApi4Active()) {
      $tags = CRM_CivirulesActions_Tag_EntityTag::getApi4Tags('civicrm_contact');
    }
    else {
      $tags = CRM_CivirulesActions_Tag_EntityTag::getApi3Tags('civicrm_contact');
    }
    return $tags;
  }


  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_action_id');
    $this->add('select', 'type', ts('Type'), array('' => ts('-- please select --'), 'sync' => E::ts('Synchronize'), 'copy' => E::ts('Copy')), true,[
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
    ]);
    $this->add('select', 'tag_ids', ts('Tags'), $this->getTags(), true,[
      'multiple' => 'multiple',
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('--- select tag(s) ---'),
    ]);
    $this->add('select', 'rel_type_ids', ts('Related contacts'), CRM_Civirules_Utils::getRelationshipTypes(), true,[
      'multiple' => 'multiple',
      'style' => 'min-width:250px',
      'class' => 'crm-select2 huge',
      'placeholder' => E::ts('--- select relationship type(s) ---'),
    ]);
    $this->addButtons(array(
      array('type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,),
      array('type' => 'cancel', 'name' => ts('Cancel'))));
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
    if (!empty($data['type'])) {
      $defaultValues['type'] = $data['type'];
    }
    if (!empty($data['tag_ids'])) {
      $defaultValues['tag_ids'] = $data['tag_ids'];
    }
    if (!empty($data['rel_type_ids'])) {
      $defaultValues['rel_type_ids'] = $data['rel_type_ids'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submitting
   *
   * @access public
   */
  public function postProcess() {
    $data['type'] = $this->_submitValues['type'];
    $data['tag_ids'] = $this->_submitValues['tag_ids'];
    $data['rel_type_ids'] = $this->_submitValues['rel_type_ids'];
    $this->ruleAction->action_params = serialize($data);
    $this->ruleAction->save();
    parent::postProcess();
  }

}
