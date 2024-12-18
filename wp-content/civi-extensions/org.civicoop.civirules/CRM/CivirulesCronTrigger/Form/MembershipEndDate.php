<?php
/**
 * @author Jaap Jansma (CiviCooP) <jaap.jansma@civicoop.org>
 * @license http://www.gnu.org/licenses/agpl-3.0.html
 */

use CRM_Civirules_ExtensionUtil as E;

class CRM_CivirulesCronTrigger_Form_MembershipEndDate extends CRM_CivirulesTrigger_Form_Form {

  /**
   * Overridden parent method to build form
   */
  public function buildQuickForm() {
    $this->add('hidden', 'rule_id');

    $this->add('select', 'membership_type_id', ts('Membership Type'),
      $this->getMembershipTypesWithIntervals(), TRUE, [
        'multiple' => TRUE,
        'class' => 'crm-select2'
      ]
    );
    $this->add('select', 'interval_unit', ts('Interval'), CRM_CivirulesCronTrigger_MembershipEndDate::intervals(), TRUE);
    $this->add('text', 'interval', ts('Interval'), [], TRUE);
    $this->addRule('interval', ts('Interval should be a numeric value'), 'numeric');

    $this->addButtons([
      ['type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,],
      ['type' => 'cancel', 'name' => ts('Cancel')]
    ]);
  }

  /**
   * Method to get the membership types
   * @param bool $onlyActive
   * @return array
   */
  public function getMembershipTypesWithIntervals($onlyActive = TRUE) {
    $return = [];
    if ($onlyActive) {
      $params = ['is_active' => 1];
    } else {
      $params = [];
    }
    $params['options'] = ['limit' => 0, 'sort' => "name ASC"];
    try {
      $membershipTypes = civicrm_api3("MembershipType", "Get", $params);
      foreach ($membershipTypes['values'] as $membershipType) {
        $return[$membershipType['id']] = E::ts('%1 (every %2 %3)', [
          1 => $membershipType['name'],
          2 => $membershipType['duration_interval'],
          3 => $membershipType['duration_unit'],
        ]);
      }
    }
    catch (CiviCRM_API3_Exception $ex) {}
    return $return;
  }

  /**
   * Overridden parent method to set default values
   *
   * @return array $defaultValues
   */
  public function setDefaultValues() {
    $defaultValues = parent::setDefaultValues();
    $data = unserialize($this->rule->trigger_params);
    if (!empty($data['membership_type_id'])) {
      $defaultValues['membership_type_id'] = $data['membership_type_id'];
    }
    if (!empty($data['interval_unit'])) {
      $defaultValues['interval_unit'] = $data['interval_unit'];
    }
    if (!empty($data['interval'])) {
      $defaultValues['interval'] = $data['interval'];
    }
    return $defaultValues;
  }

  /**
   * Overridden parent method to process form data after submission
   */
  public function postProcess() {
    $data['membership_type_id'] = $this->_submitValues['membership_type_id'];
    $data['interval_unit'] = $this->_submitValues['interval_unit'];
    $data['interval'] = $this->_submitValues['interval'];
    $this->rule->trigger_params = serialize($data);
    $this->rule->save();

    parent::postProcess();
  }

  /**
   * Returns a help text for this trigger.
   * The help text is shown to the administrator who is configuring the condition.
   *
   * @return string
   */
  protected function getHelpText() {
    return E::ts('The rule will be triggered for memberships of selected membership types when the end date is X days/weeks/months before or after.');
  }

}
