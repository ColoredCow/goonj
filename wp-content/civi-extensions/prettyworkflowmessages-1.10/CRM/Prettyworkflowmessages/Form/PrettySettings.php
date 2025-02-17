<?php

use CRM_Prettyworkflowmessages_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Prettyworkflowmessages_Form_PrettySettings extends CRM_Core_Form {
  public function buildQuickForm() {

    // add form elements
    $this->add(
      'select', // field type
      'pretty_workflow_template', // field name
      E::ts('Branding Template'), // field label
      array(' ' => E::ts('Use CiviCRM default (no branding)')) + $this->getMessageTemplates(), // list of options
      TRUE, // is required
      array('class' => 'crm-select2 huge')
    );

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Save'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => E::ts('Cancel'),
      ),
    ));

    parent::buildQuickForm();
  }

  public function setDefaultValues() {
    $defaults = array();

    // get saved value from settings
    $defaults['pretty_workflow_template'] = Civi::settings()->get('pretty_workflow_template');

    return $defaults;
  }

  public function postProcess() {
    $values = $this->exportValues();
    $options = $this->getMessageTemplates();

    // save the form settings
    $this->saveSettings($values);

    if (!empty($options[$values['pretty_workflow_template']])) {
      CRM_Core_Session::setStatus(E::ts('The system workflow messages will be sent using "%1"', array(
        1 => $options[$values['pretty_workflow_template']],
      )), 'Success', 'success');
    } else {
      CRM_Core_Session::setStatus(E::ts('The system workflow messages will be sent using default CiviCRM system template.'), 'Success', 'success');
    }
    parent::postProcess();
  }

  public function saveSettings($params) {
    // return early if the branding is not selected
    if (empty($params['pretty_workflow_template'])) {
      return;
    }

    // save to settings
    Civi::settings()->set('pretty_workflow_template', $params['pretty_workflow_template']);
  }


  public function getMessageTemplates() {
    $messageTemplates = \Civi\Api4\MessageTemplate::get()
      ->addSelect('msg_title')
      ->execute();

    $formattedMessageTemplates = array();
    foreach ($messageTemplates as $template) {
      $formattedMessageTemplates[$template['id']] = $template['msg_title'];
    }

    return $formattedMessageTemplates;
  }
}
