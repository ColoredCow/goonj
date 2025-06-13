<?php

/**
 * Class for CiviRules Group Contact Action Form
 *
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license AGPL-3.0
 */

class CRM_CivirulesActions_Activity_Form_AssignToContact extends CRM_CivirulesActions_Activity_Form_Activity
{
    /**
     * Overridden parent method to build the form
     *
     * @access public
     */
    public function buildQuickForm()
    {
        $this->add('hidden', 'rule_action_id');
        $this->addYesNo('use_contact_trigger', 'Assign to contact from trigger', false, true);
        $attributes = array(
          'multiple' => true,
          'create' => true,
          'api' => array('params' => array('is_deceased' => 0)),
        );
        $this->addEntityRef('assignee_contact_id', ts('Assign to'), $attributes, false);

        $this->addYesNo('send_email', 'Send Email to Assigned Contacts', false, true);

        $this->addButtons(array(
            array('type' => 'next', 'name' => ts('Save'), 'isDefault' => true),
            array('type' => 'cancel', 'name' => ts('Cancel'))));

    }

    /**
     * Overridden parent method to set default values
     *
     * @return array $defaultValues
     * @access public
     */
    public function setDefaultValues()
    {
        $defaultValues = parent::setDefaultValues();
        $data = $this->ruleAction->unserializeParams();

        $defaultValues['use_contact_trigger'] = '0';
        if (!empty($data['use_contact_trigger'])) {
            $defaultValues['use_contact_trigger'] = $data['use_contact_trigger'];
        }

        $defaultValues['send_email'] = '0';
        if (!empty($data['send_email'])) {
            $defaultValues['send_email'] = $data['send_email'];
        }

        return $defaultValues;
    }

    /**
     * Function to add validation action rules (overrides parent function)
     *
     * @access public
     */
    public function addRules()
    {
        parent::addRules();
        $this->addFormRule(array(
            'CRM_CivirulesActions_Activity_Form_AssignToContact',
            'validateActivityAssignedContact',
        ));
    }

    /**
     * Function to validate value of the delay
     *
     * @param array $fields
     * @return array|bool
     * @access public
     * @static
     */
    public static function validateActivityAssignedContact($fields)
    {
        $errors = array();
        if (empty($fields['use_contact_trigger']) || $fields['use_contact_trigger'] === '0') {
            if (count($fields['assignee_contact_id']) < 1 || $fields['assignee_contact_id'] == null) {
                $errors['assignee_contact_id'] = ts("Assign a contact or choose the 'Assign to contact from trigger' option");
            }
        }

        if (count($errors)) {
            return $errors;
        }

        return true;
    }

    /**
     * Overridden parent method to process form data after submitting
     *
     * @access public
     */
    public function postProcess()
    {
        $data['assignee_contact_id'] = null;
        $data['send_email'] = $this->_submitValues['send_email'];
        $data['use_contact_trigger'] = $this->_submitValues['use_contact_trigger'];

        if ($data['use_contact_trigger'] === '0') {
          $data["assignee_contact_id"] = explode(',', $this->_submitValues["assignee_contact_id"]);
        }

        $this->ruleAction->action_params = serialize($data);
        $this->ruleAction->save();
    }
}
